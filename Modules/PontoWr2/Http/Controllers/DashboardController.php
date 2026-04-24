<?php

namespace Modules\PontoWr2\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\PontoWr2\Entities\ApuracaoDia;
use Modules\PontoWr2\Entities\Colaborador;
use Modules\PontoWr2\Entities\Intercorrencia;
use Modules\PontoWr2\Entities\Marcacao;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $businessId = session('business.id') ?: $request->user()->business_id;
        $hoje = now()->toDateString();

        $kpis = [
            'colaboradores_ativos' => Colaborador::where('business_id', $businessId)
                ->where('controla_ponto', true)
                ->whereNull('desligamento')
                ->count(),
            'presentes_agora' => Marcacao::where('business_id', $businessId)
                ->whereDate('momento', $hoje)
                ->where('tipo', Marcacao::TIPO_ENTRADA)
                ->distinct('colaborador_config_id')
                ->count('colaborador_config_id'),
            'atrasos_hoje' => ApuracaoDia::where('business_id', $businessId)
                ->where('data', $hoje)
                ->where('atraso_minutos', '>', config('pontowr2.clt.tolerancia_maxima_diaria_minutos', 10))
                ->count(),
            'faltas_hoje' => ApuracaoDia::where('business_id', $businessId)
                ->where('data', $hoje)
                ->where('falta_minutos', '>', 0)
                ->count(),
            'he_mes_minutos' => (int) ApuracaoDia::where('business_id', $businessId)
                ->whereMonth('data', now()->month)
                ->sum(\DB::raw('he_diurna_minutos + he_noturna_minutos')),
            'aprovacoes_pendentes' => Intercorrencia::where('business_id', $businessId)
                ->pendentes()
                ->count(),
        ];

        // Série dos últimos 7 dias — marcações + horas extras por dia
        $inicioSerie = Carbon::today()->subDays(6);
        $raw = ApuracaoDia::where('business_id', $businessId)
            ->whereBetween('data', [$inicioSerie->toDateString(), $hoje])
            ->selectRaw('data, SUM(realizada_trabalhada_minutos) as trabalhado, SUM(he_diurna_minutos + he_noturna_minutos) as he')
            ->groupBy('data')
            ->orderBy('data')
            ->get()
            ->keyBy(fn ($r) => (string) $r->data);

        $serie7d = [];
        for ($i = 0; $i < 7; $i++) {
            $d = $inicioSerie->copy()->addDays($i);
            $key = $d->toDateString();
            $row = $raw[$key] ?? null;
            $serie7d[] = [
                'data'       => $d->toDateString(),
                'label'      => $d->isoFormat('ddd D'),
                'trabalhado' => $row ? (int) $row->trabalhado : 0,
                'he'         => $row ? (int) $row->he : 0,
            ];
        }

        $aprovacoes = Intercorrencia::where('business_id', $businessId)
            ->pendentes()
            ->with(['colaborador.user'])
            ->orderByDesc('prioridade')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn ($i) => [
                'id'             => $i->id,
                'tipo'           => $i->tipo,
                'prioridade'     => $i->prioridade,
                'data_inicio'    => $i->data_inicio?->format('Y-m-d'),
                'data_fim'       => $i->data_fim?->format('Y-m-d'),
                'justificativa'  => $i->justificativa,
                'estado'         => $i->estado,
                'created_at'     => $i->created_at?->diffForHumans(),
                'colaborador'    => [
                    'id'   => optional($i->colaborador)->id,
                    'nome' => optional(optional($i->colaborador)->user)->first_name ?? '—',
                    'matricula' => optional($i->colaborador)->matricula,
                ],
            ]);

        $atividadeRecente = Marcacao::where('business_id', $businessId)
            ->with(['colaborador.user', 'rep'])
            ->whereDate('created_at', $hoje)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(fn ($m) => [
                'id'         => $m->id,
                'tipo'       => $m->tipo,
                'momento'    => $m->momento?->format('H:i'),
                'momento_completo' => $m->momento?->format('Y-m-d H:i:s'),
                'origem'     => $m->origem,
                'tempo'      => $m->created_at?->diffForHumans(['short' => true]),
                'colaborador'=> [
                    'id'   => optional($m->colaborador)->id,
                    'nome' => optional(optional($m->colaborador)->user)->first_name ?? '—',
                    'matricula' => optional($m->colaborador)->matricula,
                ],
                'rep'        => [
                    'identificador' => optional($m->rep)->identificador,
                    'tipo'          => optional($m->rep)->tipo,
                ],
            ]);

        // Presença ao vivo: colaboradores que controlam ponto + seu status hoje
        $presenca = $this->calcularPresenca($businessId, $hoje);

        // Anomalias/alertas do dia (atrasos, saídas sem retorno, aprovações paradas >24h)
        $alertas = $this->coletarAlertas($businessId, $hoje);

        return Inertia::render('Ponto/Dashboard/Index', [
            'kpis'              => $kpis,
            'aprovacoes'        => $aprovacoes,
            'atividade_recente' => $atividadeRecente,
            'serie_7dias'       => $serie7d,
            'presenca_agora'    => $presenca,
            'alertas'           => $alertas,
            'server_time'       => now()->format('H:i'),
        ]);
    }

    /**
     * Status ao vivo por colaborador: presente / atrasado / ausente / saiu.
     * Baseado nas marcações do dia + escala esperada.
     */
    private function calcularPresenca(int $businessId, string $hoje): array
    {
        $colaboradores = Colaborador::where('business_id', $businessId)
            ->where('controla_ponto', true)
            ->whereNull('desligamento')
            ->with(['user'])
            ->orderBy('id')
            ->limit(50)
            ->get();

        if ($colaboradores->isEmpty()) {
            return [];
        }

        $marcacoesHoje = Marcacao::where('business_id', $businessId)
            ->whereDate('momento', $hoje)
            ->whereIn('colaborador_config_id', $colaboradores->pluck('id'))
            ->orderBy('momento')
            ->get()
            ->groupBy('colaborador_config_id');

        return $colaboradores->map(function ($c) use ($marcacoesHoje) {
            $marcs = $marcacoesHoje->get($c->id, collect());
            $entrada = $marcs->firstWhere('tipo', Marcacao::TIPO_ENTRADA);
            $saida   = $marcs->where('tipo', Marcacao::TIPO_SAIDA)->last();
            $ultima  = $marcs->last();

            // Status:
            // - saiu: tem saída e essa saída é a última marcação
            // - presente: tem entrada e a última não é saída (pode ser entrada após intervalo)
            // - atrasado: já passou do horário esperado (simplificado: >08:15) e não entrou
            // - ausente: não tem nenhuma marcação hoje
            $status = 'ausente';
            if ($saida && $ultima && $ultima->id === $saida->id) {
                $status = 'saiu';
            } elseif ($entrada) {
                $status = 'presente';
            } elseif (now()->format('H:i') > '08:15') {
                $status = 'atrasado';
            }

            $nome = optional($c->user)->first_name ?? 'Colab';
            $sobrenome = optional($c->user)->last_name ?? '';
            $iniciais = strtoupper(mb_substr($nome, 0, 1) . mb_substr($sobrenome, 0, 1));

            return [
                'id'        => $c->id,
                'nome'      => trim($nome . ' ' . $sobrenome),
                'matricula' => $c->matricula,
                'iniciais'  => $iniciais ?: '?',
                'status'    => $status,
                'entrada'   => $entrada?->momento?->format('H:i'),
                'saida'     => $status === 'saiu' ? $saida?->momento?->format('H:i') : null,
                'ultima'    => $ultima?->momento?->format('H:i'),
                'marcacoes' => $marcs->count(),
            ];
        })->toArray();
    }

    /**
     * Lista de anomalias do dia — coisas que pedem atenção do gestor.
     */
    private function coletarAlertas(int $businessId, string $hoje): array
    {
        $alertas = [];

        // Atrasos > tolerância (hoje)
        $tolerancia = config('pontowr2.clt.tolerancia_maxima_diaria_minutos', 10);
        $atrasados = ApuracaoDia::where('business_id', $businessId)
            ->where('data', $hoje)
            ->where('atraso_minutos', '>', $tolerancia)
            ->with(['colaborador.user'])
            ->limit(5)
            ->get();
        foreach ($atrasados as $a) {
            $alertas[] = [
                'tipo'    => 'atraso',
                'titulo'  => "Atraso de {$a->atraso_minutos}min",
                'subtitulo' => optional(optional($a->colaborador)->user)->first_name ?? 'Colaborador',
                'acao_label' => 'Ver espelho',
                'acao_href'  => "/ponto/espelho/" . optional($a->colaborador)->id,
                'severidade' => 'warning',
            ];
        }

        // Aprovações paradas > 24h
        $paradas = Intercorrencia::where('business_id', $businessId)
            ->pendentes()
            ->where('created_at', '<', now()->subDay())
            ->with(['colaborador.user'])
            ->limit(5)
            ->get();
        foreach ($paradas as $p) {
            $alertas[] = [
                'tipo'    => 'aprovacao_parada',
                'titulo'  => "Aprovação parada há " . $p->created_at->diffForHumans(['short' => true]),
                'subtitulo' => optional(optional($p->colaborador)->user)->first_name ?? 'Colaborador',
                'acao_label' => 'Aprovar',
                'acao_href'  => "/ponto/intercorrencias/{$p->id}",
                'severidade' => 'danger',
            ];
        }

        // Faltas hoje
        $faltas = ApuracaoDia::where('business_id', $businessId)
            ->where('data', $hoje)
            ->where('falta_minutos', '>', 0)
            ->with(['colaborador.user'])
            ->limit(5)
            ->get();
        foreach ($faltas as $f) {
            $alertas[] = [
                'tipo'    => 'falta',
                'titulo'  => "Falta de {$f->falta_minutos}min",
                'subtitulo' => optional(optional($f->colaborador)->user)->first_name ?? 'Colaborador',
                'acao_label' => 'Justificar',
                'acao_href'  => "/ponto/intercorrencias/create?colaborador=" . optional($f->colaborador)->id,
                'severidade' => 'danger',
            ];
        }

        return $alertas;
    }
}
