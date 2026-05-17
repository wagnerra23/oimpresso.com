<?php

namespace Modules\Ponto\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Ponto\Entities\ApuracaoDia;
use Modules\Ponto\Entities\Colaborador;
use Modules\Ponto\Entities\Marcacao;
use Modules\Ponto\Services\ReportService;

class EspelhoController extends Controller
{
    protected $reports;

    public function __construct(ReportService $reports)
    {
        $this->reports = $reports;
    }

    public function index(Request $request): Response
    {
        $businessId = session('business.id') ?: $request->user()->business_id;
        $mes = $request->input('mes', now()->format('Y-m'));

        // Wave 26 D6 Inertia::defer DEFAULT — paginate() heavy vira closure lazy
        // (RUNBOOK-inertia-defer-pattern.md). Filters UI state (`mes`) permanece eager.
        return Inertia::render('Ponto/Espelho/Index', [
            'colaboradores' => Inertia::defer(fn () => $this->buildColaboradoresPagina($businessId)),
            'mes'           => $mes,
        ]);
    }

    /**
     * Paginação 25 colaboradores ativos — eager `user`. Wave 26 extraído pra
     * closure `Inertia::defer` (pattern Dashboard Wave 25 replicado).
     */
    private function buildColaboradoresPagina(int $businessId)
    {
        $paginated = Colaborador::where('business_id', $businessId)
            ->where('controla_ponto', true)
            ->whereNull('desligamento')
            ->with('user:id,first_name,last_name')
            ->orderBy('matricula')
            ->paginate(25)
            ->withQueryString();

        $paginated->getCollection()->transform(fn ($c) => [
            'id'        => $c->id,
            'matricula' => $c->matricula,
            'cpf'       => $c->cpf,
            'nome'      => trim(optional($c->user)->first_name . ' ' . optional($c->user)->last_name) ?: '—',
            'email'     => optional($c->user)->email,
        ]);

        return $paginated;
    }

    public function show(Request $request, $colaboradorId): Response
    {
        $businessId = session('business.id') ?: $request->user()->business_id;
        $mes = $request->input('mes', now()->format('Y-m'));
        [$ano, $mesNum] = explode('-', $mes);

        $colaborador = Colaborador::where('business_id', $businessId)
            ->with(['user:id,first_name,last_name,email', 'escalaAtual'])
            ->findOrFail($colaboradorId);

        // Wave 26 D6 Inertia::defer — sums + loop construção `linhas` (até 31 dias)
        // viram closures lazy. Cabeçalho `colaborador` eager (já materializado acima
        // pra findOrFail validar tenant).
        return Inertia::render('Ponto/Espelho/Show', [
            'colaborador' => [
                'id'        => $colaborador->id,
                'matricula' => $colaborador->matricula,
                'cpf'       => $colaborador->cpf,
                'nome'      => trim(optional($colaborador->user)->first_name . ' ' . optional($colaborador->user)->last_name) ?: '—',
                'email'     => optional($colaborador->user)->email,
                'admissao'  => optional($colaborador->admissao)->format('Y-m-d'),
                'escala'    => optional($colaborador->escalaAtual)->nome,
            ],
            'mes'    => $mes,
            'totais' => Inertia::defer(fn () => $this->buildTotaisEspelho((int) $colaboradorId, (int) $ano, (int) $mesNum)),
            'linhas' => Inertia::defer(fn () => $this->buildLinhasEspelho((int) $colaboradorId, (int) $ano, (int) $mesNum)),
        ]);
    }

    /**
     * Totalizadores mensais (8 sums + 1 count). Wave 26 extraído pra closure lazy.
     *
     * @return array<string,int>
     */
    private function buildTotaisEspelho(int $colaboradorId, int $ano, int $mesNum): array
    {
        $apuracoes = ApuracaoDia::where('colaborador_config_id', $colaboradorId)
            ->whereYear('data', $ano)
            ->whereMonth('data', $mesNum)
            ->get();

        return [
            'trabalhado'    => (int) $apuracoes->sum('realizada_trabalhada_minutos'),
            'atraso'        => (int) $apuracoes->sum('atraso_minutos'),
            'falta'         => (int) $apuracoes->sum('falta_minutos'),
            'he_diurna'     => (int) $apuracoes->sum('he_diurna_minutos'),
            'he_noturna'    => (int) $apuracoes->sum('he_noturna_minutos'),
            'adicional_not' => (int) $apuracoes->sum('adicional_noturno_minutos'),
            'bh_credito'    => (int) $apuracoes->sum('banco_horas_credito_minutos'),
            'bh_debito'     => (int) $apuracoes->sum('banco_horas_debito_minutos'),
            'divergencias'  => $apuracoes->where('tem_divergencia', true)->count(),
        ];
    }

    /**
     * Linhas dia-a-dia do espelho (até 31). Wave 26 extraído pra closure lazy.
     *
     * @return array<int,array<string,mixed>>
     */
    private function buildLinhasEspelho(int $colaboradorId, int $ano, int $mesNum): array
    {
        $apuracoes = ApuracaoDia::where('colaborador_config_id', $colaboradorId)
            ->whereYear('data', $ano)
            ->whereMonth('data', $mesNum)
            ->orderBy('data')
            ->get();

        $marcacoes = Marcacao::where('colaborador_config_id', $colaboradorId)
            ->whereYear('momento', $ano)
            ->whereMonth('momento', $mesNum)
            ->whereNotIn('origem', [Marcacao::ORIGEM_ANULACAO])
            ->orderBy('momento')
            ->get();

        $marcacoesPorDia = $marcacoes->groupBy(fn ($m) => $m->momento->toDateString());
        $apuracoesPorData = $apuracoes->keyBy(fn ($a) => (string) $a->data);

        $inicio = Carbon::createFromDate($ano, $mesNum, 1)->startOfMonth();
        $fim = $inicio->copy()->endOfMonth();
        $cursor = $inicio->copy();
        $linhas = [];

        while ($cursor <= $fim) {
            $dataStr = $cursor->toDateString();
            $a = $apuracoesPorData[$dataStr] ?? null;
            $mgs = $marcacoesPorDia[$dataStr] ?? collect();

            $linhas[] = [
                'data'      => $dataStr,
                'dow'       => $cursor->locale('pt_BR')->isoFormat('ddd'),
                'dia'       => $cursor->day,
                'is_weekend'=> $cursor->isWeekend(),
                'trabalhado'=> $a ? (int) $a->realizada_trabalhada_minutos : 0,
                'atraso'    => $a ? (int) $a->atraso_minutos : 0,
                'falta'     => $a ? (int) $a->falta_minutos : 0,
                'he'        => $a ? ((int) $a->he_diurna_minutos + (int) $a->he_noturna_minutos) : 0,
                'divergencia' => $a ? (bool) $a->tem_divergencia : false,
                'marcacoes' => $mgs->map(fn ($m) => [
                    'hora'   => $m->momento->format('H:i'),
                    'tipo'   => $m->tipo,
                    'origem' => $m->origem,
                ])->values()->toArray(),
            ];
            $cursor->addDay();
        }

        return $linhas;
    }

    public function imprimir(Request $request, $colaboradorId)
    {
        $businessId = session('business.id') ?: $request->user()->business_id;
        $mes = $request->input('mes', now()->format('Y-m'));

        $colaborador = Colaborador::where('business_id', $businessId)
            ->with(['user', 'escalaAtual'])
            ->findOrFail($colaboradorId);

        $pdf = $this->reports->espelhoPdf($colaborador, $mes);
        $nome = $this->reports->espelhoPdfNome($colaborador, $mes);

        // stream() abre inline no browser; download() força save
        return $pdf->stream($nome);
    }
}
