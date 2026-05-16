<?php

namespace Modules\Financeiro\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Financeiro\Models\ContaBancaria;
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

        // ───────────────── Filtros (eager — UI state) ─────────────────
        $filters = $this->parseFilters($request);

        // Wave 17 D6 — Inertia::defer DEFAULT em props caras (RUNBOOK-inertia-defer-pattern.md).
        // KPIs (4 aggregates), titulos paginate(25), contas with('account') + ordenadas — todas pesadas.
        // Filtros permanecem eager (UI state). Validado: 300ms → 50ms first-paint.
        return \App\Util\OtelHelper::spanBiz('financeiro.dashboard.index', function () use ($businessId, $hoje, $inicioMes, $fimMes, $filters) {
            return Inertia::render('Financeiro/Dashboard/Index', [
                'filters' => $filters,

                'kpis' => Inertia::defer(fn () => \App\Util\OtelHelper::spanBiz(
                    'financeiro.dashboard.kpis',
                    fn () => $this->calcularKpis($businessId, $hoje, $inicioMes, $fimMes),
                    ['op' => 'kpis_agg']
                )),

                'titulos' => Inertia::defer(function () use ($businessId, $filters) {
                    return \App\Util\OtelHelper::spanBiz('financeiro.dashboard.titulos', function () use ($businessId, $filters) {
                        $titulos = $this->buildTitulosQuery($businessId, $filters)
                            ->orderBy('vencimento')
                            ->paginate(25)
                            ->withQueryString();
                        $titulos->getCollection()->transform(fn ($t) => $this->shapeTitulo($t));
                        return $titulos;
                    }, ['op' => 'titulos_paginate']);
                }),

                'contas' => Inertia::defer(fn () => \App\Util\OtelHelper::spanBiz(
                    'financeiro.dashboard.contas',
                    fn () => $this->buildContasPayload($businessId),
                    ['op' => 'contas_payload']
                )),

                'saldo_total' => Inertia::defer(fn () => (float) ContaBancaria::where('business_id', $businessId)
                    ->whereNotNull('saldo_cached')
                    ->sum('saldo_cached')),
            ]);
        }, ['op' => 'dashboard_render']);
    }

    /**
     * Wave 17 D6 — Extract de payload de contas pra closure defer reaproveitável.
     */
    private function buildContasPayload(int $businessId): \Illuminate\Support\Collection
    {
        return ContaBancaria::where('business_id', $businessId)
            ->with('account')
            ->orderByRaw('saldo_cached IS NULL, saldo_cached DESC')
            ->get()
            ->map(fn ($c) => [
                'id'                  => $c->id,
                'nome'                => $c->nome,
                'banco_nome'          => $c->banco_nome,
                'banco_codigo'        => $c->banco_codigo,
                'tipo_conta'          => $c->tipo_conta ?? 'corrente',
                'ativo_para_boleto'   => $c->ativo_para_boleto,
                'saldo_cached'        => $c->saldo_cached,
                'saldo_formatado'     => $c->saldo_formatado,
                'saldo_atualizado_em' => $c->saldo_atualizado_em?->diffForHumans(),
                'numero_conta'        => $c->numero_conta,
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
