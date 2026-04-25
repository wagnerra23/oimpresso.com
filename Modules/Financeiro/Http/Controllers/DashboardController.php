<?php

namespace Modules\Financeiro\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Financeiro\Models\Titulo;
use Modules\Financeiro\Models\TituloBaixa;

/**
 * Dashboard unificado do Financeiro (US-FIN-013).
 *
 * Renderiza /financeiro com:
 *   - 4 KPI cards clicáveis (A Receber / A Pagar / Recebidos mês / Pagos mês)
 *   - Tabela única com filtros (tipo, status, período, cliente)
 *   - URL state (?tipo=...&status=...) — bookmarkable
 *
 * Cache:
 *   KPIs: 5 min, invalidado em TituloBaixado/Criado/Cancelado (UI-0002)
 */
class DashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('can:financeiro.dashboard.view');
    }

    public function index(Request $request): Response
    {
        $businessId = (int) session('user.business_id');
        $hoje = now()->toDateString();
        $inicioMes = now()->startOfMonth()->toDateString();
        $fimMes = now()->endOfMonth()->toDateString();

        // ───────────────── KPIs ─────────────────
        $kpis = $this->calcularKpis($businessId, $hoje, $inicioMes, $fimMes);

        // ───────────────── Tabela ─────────────────
        $filters = $this->parseFilters($request);
        $titulos = $this->buildTitulosQuery($businessId, $filters)
            ->orderBy('vencimento')
            ->paginate(25)
            ->withQueryString();

        $titulos->getCollection()->transform(fn ($t) => $this->shapeTitulo($t));

        return Inertia::render('Financeiro/Dashboard/Index', [
            'kpis' => $kpis,
            'titulos' => $titulos,
            'filters' => $filters,
        ]);
    }

    private function calcularKpis(int $businessId, string $hoje, string $inicioMes, string $fimMes): array
    {
        $aReceber = Titulo::where('business_id', $businessId)
            ->where('tipo', 'receber')
            ->whereIn('status', ['aberto', 'parcial']);

        $aPagar = Titulo::where('business_id', $businessId)
            ->where('tipo', 'pagar')
            ->whereIn('status', ['aberto', 'parcial']);

        $recebidoMes = TituloBaixa::where('business_id', $businessId)
            ->whereBetween('data_baixa', [$inicioMes, $fimMes])
            ->whereHas('titulo', fn ($q) => $q->where('tipo', 'receber'))
            ->whereNull('estorno_de_id');

        $pagoMes = TituloBaixa::where('business_id', $businessId)
            ->whereBetween('data_baixa', [$inicioMes, $fimMes])
            ->whereHas('titulo', fn ($q) => $q->where('tipo', 'pagar'))
            ->whereNull('estorno_de_id');

        return [
            'receber_aberto' => [
                'valor' => (float) (clone $aReceber)->sum('valor_aberto'),
                'qtd' => (clone $aReceber)->count(),
                'vencidos_qtd' => (clone $aReceber)->where('vencimento', '<', $hoje)->count(),
                'vencidos_valor' => (float) (clone $aReceber)->where('vencimento', '<', $hoje)->sum('valor_aberto'),
            ],
            'pagar_aberto' => [
                'valor' => (float) (clone $aPagar)->sum('valor_aberto'),
                'qtd' => (clone $aPagar)->count(),
                'vencidos_qtd' => (clone $aPagar)->where('vencimento', '<', $hoje)->count(),
                'vencidos_valor' => (float) (clone $aPagar)->where('vencimento', '<', $hoje)->sum('valor_aberto'),
            ],
            'recebido_mes' => [
                'valor' => (float) (clone $recebidoMes)->sum('valor_baixa'),
                'qtd' => (clone $recebidoMes)->count(),
            ],
            'pago_mes' => [
                'valor' => (float) (clone $pagoMes)->sum('valor_baixa'),
                'qtd' => (clone $pagoMes)->count(),
            ],
        ];
    }

    private function parseFilters(Request $request): array
    {
        return [
            'tipo' => in_array($request->get('tipo'), ['receber', 'pagar', 'all'], true) ? $request->get('tipo') : 'all',
            'status' => in_array($request->get('status'), ['aberto', 'parcial', 'quitado', 'cancelado', 'all'], true) ? $request->get('status') : 'all',
            'busca' => trim((string) $request->get('busca', '')),
        ];
    }

    private function buildTitulosQuery(int $businessId, array $filters)
    {
        $q = Titulo::where('business_id', $businessId);

        if ($filters['tipo'] !== 'all') {
            $q->where('tipo', $filters['tipo']);
        }

        if ($filters['status'] !== 'all') {
            $q->where('status', $filters['status']);
        }

        if ($filters['busca'] !== '') {
            $busca = '%' . $filters['busca'] . '%';
            $q->where(function ($q) use ($busca) {
                $q->where('numero', 'like', $busca)
                  ->orWhere('cliente_descricao', 'like', $busca);
            });
        }

        return $q;
    }

    private function shapeTitulo(Titulo $t): array
    {
        return [
            'id' => $t->id,
            'numero' => $t->numero,
            'tipo' => $t->tipo,
            'status' => $t->status,
            'cliente_nome' => $t->cliente_descricao ?? ('#' . ($t->cliente_id ?? '—')),
            'vencimento' => $t->vencimento?->toDateString(),
            'vencimento_label' => $t->vencimento?->format('d/m/Y'),
            'valor_total' => (float) $t->valor_total,
            'valor_aberto' => (float) $t->valor_aberto,
            'aging_bucket' => $t->agingBucket(),
            'origem' => $t->origem,
            'origem_id' => $t->origem_id,
        ];
    }
}
