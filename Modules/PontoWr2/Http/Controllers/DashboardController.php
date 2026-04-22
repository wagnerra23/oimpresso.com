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
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn ($m) => [
                'id'         => $m->id,
                'tipo'       => $m->tipo,
                'momento'    => $m->momento?->format('Y-m-d H:i'),
                'origem'     => $m->origem,
                'colaborador'=> [
                    'nome' => optional(optional($m->colaborador)->user)->first_name ?? '—',
                ],
                'rep'        => [
                    'identificador' => optional($m->rep)->identificador,
                    'tipo'          => optional($m->rep)->tipo,
                ],
            ]);

        return Inertia::render('Ponto/Dashboard/Index', [
            'kpis'              => $kpis,
            'aprovacoes'        => $aprovacoes,
            'atividade_recente' => $atividadeRecente,
            'serie_7dias'       => $serie7d,
        ]);
    }
}
