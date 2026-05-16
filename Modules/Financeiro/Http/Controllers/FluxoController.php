<?php

namespace Modules\Financeiro\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Util\OtelHelper;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Financeiro\Services\FluxoCaixaService;

/**
 * Tela /financeiro/fluxo — Fluxo de caixa projetado (Cockpit V2).
 *
 * Origem: protótipo Cowork "Fluxo de Caixa" aprovado por [W] 2026-05-09 +
 * decisões Q1-Q4 aprovadas por [W] 2026-05-14 (memory/requisitos/Financeiro/
 * fluxo-visual-comparison.md).
 *
 * Persona-foco: Eliana [E] (financeiro escritório) + Wagner [W] (dono).
 * Stories: US-FIN-014 (fluxo-caixa-projetado).
 *
 * Read-only: NÃO faz mutação. Toda agregação acontece em FluxoCaixaService.
 *
 * Multi-tenant Tier 0 (ADR 0093 IRREVOGÁVEL):
 *  - business_id lido de session('user.business_id')
 *  - middleware can:financeiro.dashboard.view (granularidade financeiro.fluxo.view
 *    fica pra evolução; F1 reusa dashboard.view — mesma persona)
 *  - Service recebe businessId explicitamente como 1º arg
 */
class FluxoController extends Controller
{
    public function __construct(private FluxoCaixaService $service)
    {
        $this->middleware('auth');
        $this->middleware('can:financeiro.dashboard.view');
    }

    public function index(Request $request): Response
    {
        $businessId = (int) session('user.business_id');
        $dias = $this->resolveDias($request);

        // Wave 17 D9 — projeção 35d agrega Titulo + TituloBaixa + ContaBancaria.saldo_cached;
        // pode ser pesada em business com volume alto. Span ajuda a detectar regressão.
        return OtelHelper::spanBiz('financeiro.fluxo.projetar', function () use ($businessId, $dias) {
            $shape = $this->service->projetar($businessId, $dias);
            return Inertia::render('Financeiro/Fluxo/Index', $shape);
        }, ['op' => 'projetar', 'dias' => $dias]);
    }

    /**
     * Q2 aprovada: 35 dias fixo em F1. Querystring ?dias=N aceita 7..60 pra
     * evolução opt-in sem virar dropdown ainda (F2 entrega UI controle).
     */
    private function resolveDias(Request $request): int
    {
        $dias = (int) $request->integer('dias', 35);

        return max(7, min(60, $dias));
    }
}
