<?php

namespace Modules\Financeiro\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Financeiro\Http\Controllers\Concerns\RendersMockCowork;
use Modules\Financeiro\Models\Titulo;
use Modules\Financeiro\Models\TituloBaixa;

/**
 * Relatórios gerenciais do Financeiro (US-FIN-014b).
 *
 * Entrega 2 visões em uma tela com tabs:
 *   1) Fluxo de Caixa Projetado vs Realizado (semana/mês)
 *   2) Resumo do Mês (KPIs)
 *
 * DRE foi extraída pra DreController + DreService (`/financeiro/dre`) em
 * 2026-05-20 (PR D wave reaplicação canon — Q8b Wagner aprovou). Ver
 * memory/requisitos/Financeiro/dre-visual-comparison.md (status approved).
 *
 * Multi-tenant: BusinessScope nos models filtra por session('user.business_id').
 *
 * Pattern: ADR 0029 (Inertia + React + UPos), espelha DashboardController.
 */
class RelatoriosController extends Controller
{
    use RendersMockCowork;

    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('can:financeiro.relatorios.view');
    }

    public function index(Request $request): Response|\Illuminate\Http\Response
    {
        if ($mock = $this->tryRenderMockCowork()) {
            return $mock;
        }

        $businessId = (int) session('user.business_id');
        $filters = $this->parseFilters($request);

        return Inertia::render('Financeiro/Relatorios/Index', [
            'filters'  => $filters,
            'fluxo'    => $this->montarFluxo($businessId, $filters),
            'resumo'   => $this->montarResumo($businessId, $filters),
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $businessId = (int) session('user.business_id');
        $filters = $this->parseFilters($request);
        // DRE removida 2026-05-20 (PR D wave reaplicação canon).
        // DRE export agora vive em DreController::exportCsv. Default cai pra fluxo.
        $tipo = $request->get('tipo', 'fluxo');

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="relatorio_' . $tipo . '_' . now()->format('Ymd_His') . '.csv"',
        ];

        return response()->streamDownload(function () use ($businessId, $filters, $tipo) {
            $out = fopen('php://output', 'w');
            // BOM UTF-8 pra Excel BR abrir certo
            fwrite($out, "\xEF\xBB\xBF");

            switch ($tipo) {
                case 'resumo':
                    $this->csvResumo($out, $businessId, $filters);
                    break;
                case 'fluxo':
                default:
                    $this->csvFluxo($out, $businessId, $filters);
            }

            fclose($out);
        }, 'relatorio.csv', $headers);
    }

    // ───────────────────── Filters ─────────────────────

    private function parseFilters(Request $request): array
    {
        $hoje = Carbon::today();

        $de = $this->parseDate($request->get('data_de'), $hoje->copy()->subMonthsNoOverflow(3)->startOfMonth());
        $ate = $this->parseDate($request->get('data_ate'), $hoje->copy()->endOfMonth());

        if ($de->gt($ate)) {
            [$de, $ate] = [$ate, $de];
        }

        return [
            'data_de'  => $de->toDateString(),
            'data_ate' => $ate->toDateString(),
        ];
    }

    private function parseDate(?string $s, Carbon $fallback): Carbon
    {
        if (! $s) {
            return $fallback;
        }
        try {
            return Carbon::parse($s);
        } catch (\Throwable $e) {
            return $fallback;
        }
    }

    // ───────────────────── DRE: removida 2026-05-20 (PR D wave reaplicação canon) ─────────────────────
    // `montarDre()` e `csvDre()` removidos. DRE agora vive em DreController + DreService
    // (`/financeiro/dre`) com hierarquia clássica (header/item/subtotal/highlight).
    // Ver: memory/requisitos/Financeiro/dre-visual-comparison.md (status approved Wagner 2026-05-20).

    // ───────────────────── Fluxo ─────────────────────

    /**
     * Fluxo de caixa: projetado (a vencer) vs realizado (baixas).
     * Granularidade: semana — agrupa por week('vencimento') e week('data_baixa').
     */
    private function montarFluxo(int $businessId, array $filters): array
    {
        $de = $filters['data_de'];
        $ate = $filters['data_ate'];

        // Lista de semanas entre de..ate (label = "DD/MM" do segundo-feira)
        $semanas = [];
        $cur = Carbon::parse($de)->startOfWeek();
        $end = Carbon::parse($ate)->endOfWeek();
        $guard = 0;
        while ($cur->lte($end) && $guard < 53) {
            $semanas[$cur->toDateString()] = [
                'semana_inicio' => $cur->toDateString(),
                'semana_label'  => $cur->format('d/m'),
                'projetado_receber' => 0.0,
                'projetado_pagar'   => 0.0,
                'realizado_receber' => 0.0,
                'realizado_pagar'   => 0.0,
            ];
            $cur->addWeek();
            $guard++;
        }

        // Projetado: titulos abertos/parciais com vencimento dentro do range
        $proj = Titulo::query()
            ->where('business_id', $businessId)
            ->whereIn('status', ['aberto', 'parcial'])
            ->whereBetween('vencimento', [$de, $ate])
            ->get(['tipo', 'vencimento', 'valor_aberto']);

        foreach ($proj as $t) {
            if (! $t->vencimento) {
                continue;
            }
            $weekKey = $t->vencimento->copy()->startOfWeek()->toDateString();
            if (! isset($semanas[$weekKey])) {
                continue;
            }
            $field = $t->tipo === 'receber' ? 'projetado_receber' : 'projetado_pagar';
            $semanas[$weekKey][$field] += (float) $t->valor_aberto;
        }

        // Realizado: baixas no range (não-estornos), agrupando pelo tipo do titulo
        $real = TituloBaixa::query()
            ->join('fin_titulos', 'fin_titulo_baixas.titulo_id', '=', 'fin_titulos.id')
            ->where('fin_titulo_baixas.business_id', $businessId)
            ->whereNull('fin_titulo_baixas.estorno_de_id')
            ->whereBetween('fin_titulo_baixas.data_baixa', [$de, $ate])
            ->select(
                'fin_titulos.tipo as tipo',
                'fin_titulo_baixas.data_baixa as data_baixa',
                'fin_titulo_baixas.valor_baixa as valor_baixa'
            )
            ->get();

        foreach ($real as $b) {
            $data = Carbon::parse($b->data_baixa);
            $weekKey = $data->copy()->startOfWeek()->toDateString();
            if (! isset($semanas[$weekKey])) {
                continue;
            }
            $field = $b->tipo === 'receber' ? 'realizado_receber' : 'realizado_pagar';
            $semanas[$weekKey][$field] += (float) $b->valor_baixa;
        }

        $linhas = array_values($semanas);

        $totais = array_reduce($linhas, function ($acc, $s) {
            $acc['projetado_receber'] += $s['projetado_receber'];
            $acc['projetado_pagar']   += $s['projetado_pagar'];
            $acc['realizado_receber'] += $s['realizado_receber'];
            $acc['realizado_pagar']   += $s['realizado_pagar'];
            return $acc;
        }, ['projetado_receber' => 0.0, 'projetado_pagar' => 0.0, 'realizado_receber' => 0.0, 'realizado_pagar' => 0.0]);

        $totais['saldo_projetado'] = $totais['projetado_receber'] - $totais['projetado_pagar'];
        $totais['saldo_realizado'] = $totais['realizado_receber'] - $totais['realizado_pagar'];

        return [
            'semanas' => $linhas,
            'totais'  => $totais,
        ];
    }

    // ───────────────────── Resumo ─────────────────────

    private function montarResumo(int $businessId, array $filters): array
    {
        $hoje = now()->toDateString();
        $de = $filters['data_de'];
        $ate = $filters['data_ate'];

        $receber = Titulo::query()
            ->where('business_id', $businessId)
            ->where('tipo', 'receber')
            ->whereIn('status', ['aberto', 'parcial']);

        $pagar = Titulo::query()
            ->where('business_id', $businessId)
            ->where('tipo', 'pagar')
            ->whereIn('status', ['aberto', 'parcial']);

        $vencidosReceber = (clone $receber)->where('vencimento', '<', $hoje);
        $vencidosPagar = (clone $pagar)->where('vencimento', '<', $hoje);

        $recebidoPeriodo = TituloBaixa::query()
            ->where('business_id', $businessId)
            ->whereNull('estorno_de_id')
            ->whereBetween('data_baixa', [$de, $ate])
            ->whereHas('titulo', fn ($q) => $q->where('tipo', 'receber'));

        $pagoPeriodo = TituloBaixa::query()
            ->where('business_id', $businessId)
            ->whereNull('estorno_de_id')
            ->whereBetween('data_baixa', [$de, $ate])
            ->whereHas('titulo', fn ($q) => $q->where('tipo', 'pagar'));

        $totReceberAberto = (float) (clone $receber)->sum('valor_aberto');
        $totPagarAberto = (float) (clone $pagar)->sum('valor_aberto');
        $totRecebidoPeriodo = (float) (clone $recebidoPeriodo)->sum('valor_baixa');
        $totPagoPeriodo = (float) (clone $pagoPeriodo)->sum('valor_baixa');

        return [
            'periodo' => [
                'de'  => $de,
                'ate' => $ate,
            ],
            'a_receber' => [
                'valor'         => $totReceberAberto,
                'qtd'           => (clone $receber)->count(),
                'vencidos_qtd'  => (clone $vencidosReceber)->count(),
                'vencidos_valor'=> (float) (clone $vencidosReceber)->sum('valor_aberto'),
            ],
            'a_pagar' => [
                'valor'         => $totPagarAberto,
                'qtd'           => (clone $pagar)->count(),
                'vencidos_qtd'  => (clone $vencidosPagar)->count(),
                'vencidos_valor'=> (float) (clone $vencidosPagar)->sum('valor_aberto'),
            ],
            'recebido_periodo' => [
                'valor' => $totRecebidoPeriodo,
                'qtd'   => (clone $recebidoPeriodo)->count(),
            ],
            'pago_periodo' => [
                'valor' => $totPagoPeriodo,
                'qtd'   => (clone $pagoPeriodo)->count(),
            ],
            'saldo_aberto'    => $totReceberAberto - $totPagarAberto,
            'saldo_periodo'   => $totRecebidoPeriodo - $totPagoPeriodo,
        ];
    }

    // ───────────────────── CSV writers ─────────────────────
    // csvDre() removido 2026-05-20 (PR D wave reaplicação canon). Export DRE
    // agora vive em DreController::exportCsv (`/financeiro/dre/export-csv`).

    private function csvFluxo($out, int $businessId, array $filters): void
    {
        $fluxo = $this->montarFluxo($businessId, $filters);

        fputcsv($out, ['Fluxo de Caixa — ' . $filters['data_de'] . ' a ' . $filters['data_ate']]);
        fputcsv($out, []);
        fputcsv($out, ['Semana', 'Projetado Receber', 'Projetado Pagar', 'Realizado Receber', 'Realizado Pagar']);
        foreach ($fluxo['semanas'] as $s) {
            fputcsv($out, [
                $s['semana_inicio'],
                $s['projetado_receber'],
                $s['projetado_pagar'],
                $s['realizado_receber'],
                $s['realizado_pagar'],
            ]);
        }
        $t = $fluxo['totais'];
        fputcsv($out, ['TOTAL', $t['projetado_receber'], $t['projetado_pagar'], $t['realizado_receber'], $t['realizado_pagar']]);
    }

    private function csvResumo($out, int $businessId, array $filters): void
    {
        $r = $this->montarResumo($businessId, $filters);

        fputcsv($out, ['Resumo do Período — ' . $r['periodo']['de'] . ' a ' . $r['periodo']['ate']]);
        fputcsv($out, []);
        fputcsv($out, ['Indicador', 'Quantidade', 'Valor']);
        fputcsv($out, ['A receber (aberto)',     $r['a_receber']['qtd'], $r['a_receber']['valor']]);
        fputcsv($out, ['  vencidos',              $r['a_receber']['vencidos_qtd'], $r['a_receber']['vencidos_valor']]);
        fputcsv($out, ['A pagar (aberto)',       $r['a_pagar']['qtd'], $r['a_pagar']['valor']]);
        fputcsv($out, ['  vencidos',              $r['a_pagar']['vencidos_qtd'], $r['a_pagar']['vencidos_valor']]);
        fputcsv($out, ['Recebido no período',    $r['recebido_periodo']['qtd'], $r['recebido_periodo']['valor']]);
        fputcsv($out, ['Pago no período',        $r['pago_periodo']['qtd'], $r['pago_periodo']['valor']]);
        fputcsv($out, ['Saldo em aberto',        '', $r['saldo_aberto']]);
        fputcsv($out, ['Saldo do período',       '', $r['saldo_periodo']]);
    }
}
