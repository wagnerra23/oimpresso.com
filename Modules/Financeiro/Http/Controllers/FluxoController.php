<?php

namespace Modules\Financeiro\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Util\OtelHelper;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Financeiro\Services\FluxoCaixaService;
use Modules\Financeiro\Services\FluxoRealizadoService;

/**
 * Tela /financeiro/fluxo — Fluxo de caixa (Cockpit V2) com tabs Projetado + Realizado.
 *
 * Origem: protótipo Cowork "Fluxo de Caixa" aprovado por [W] 2026-05-09 +
 * decisões Q1-Q4 aprovadas por [W] 2026-05-14 (memory/requisitos/Financeiro/
 * fluxo-visual-comparison.md).
 *
 * Fase 3 deprecação legacy (2026-05-21): adiciona tab "Realizado" pra absorver
 * o Cash Flow legacy do core UltimatePOS (`AccountController::cashFlow()`,
 * `/account/cash-flow` → 301 → `/financeiro/fluxo` desde PR #1283). A tab
 * "Projetado" preserva 100% o comportamento anterior (default).
 *
 * Persona-foco: Eliana [E] (financeiro escritório) + Wagner [W] (dono).
 * Stories: US-FIN-014 (fluxo-caixa-projetado), US-FIN-014c (fluxo-realizado).
 *
 * Read-only: NÃO faz mutação. Toda agregação acontece nos Services.
 *
 * Multi-tenant Tier 0 (ADR 0093 IRREVOGÁVEL):
 *  - business_id lido de session('user.business_id')
 *  - middleware can:financeiro.dashboard.view
 *  - Services recebem businessId explicitamente como 1º arg
 */
class FluxoController extends Controller
{

    public function __construct(
        private FluxoCaixaService $service,
        private FluxoRealizadoService $realizadoService,
    ) {
        $this->middleware('auth');
        $this->middleware('can:financeiro.dashboard.view');
    }

    public function index(Request $request): Response|\Illuminate\Http\Response
    {

        $businessId = (int) session('user.business_id');
        $tab = $this->resolveTab($request);
        $dias = $this->resolveDias($request);
        $meses = $this->resolveMeses($request);

        // Wave 17 D9 — projeção 35d agrega Titulo + TituloBaixa + ContaBancaria.saldo_cached;
        // pode ser pesada em business com volume alto. Span ajuda a detectar regressão.
        return OtelHelper::spanBiz('financeiro.fluxo.render', function () use ($businessId, $tab, $dias, $meses) {
            // Projetado: sempre presente no payload (default; props no shape canon
            // pra preservar testes existentes e Pest GUARDs do charter).
            // closure D-14: projeção 35d é por business, não muda com a troca de tab —
            // como closures memoizadas, o partial reload (only: tab/realizado) pula a
            // agregação inteira do projetar(). Load cheio roda 1× só.
            $projetadoCache = null;
            $projetado = function () use (&$projetadoCache, $businessId, $dias): array {
                return $projetadoCache ??= $this->service->projetar($businessId, $dias);
            };

            // Realizado: só carrega quando tab=realizado pra evitar query custosa
            // em renders que não vão exibir. Estrutura ausente quando não carregada.
            $realizado = $tab === 'realizado'
                ? $this->realizadoService->buscar($businessId, $meses)
                : null;

            return Inertia::render('Financeiro/Fluxo/Index', [
                'saldo_hoje'    => fn () => $projetado()['saldo_hoje'],
                'saldo_30d'     => fn () => $projetado()['saldo_30d'],
                'pior_dia'      => fn () => $projetado()['pior_dia'],
                'margem_minima' => fn () => $projetado()['margem_minima'],
                'conta'         => fn () => $projetado()['conta'],
                'dias'          => fn () => $projetado()['dias'],
                'tab' => $tab,
                'realizado' => $realizado,
            ]);
        }, ['op' => 'render', 'tab' => $tab, 'dias' => $dias, 'meses' => $meses]);
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

    /**
     * Fase 3: tab=projetado (default) ou tab=realizado. Qualquer outro valor
     * cai pro default — clamp defensivo (anti-injection na querystring).
     */
    private function resolveTab(Request $request): string
    {
        $tab = (string) $request->query('tab', 'projetado');

        return in_array($tab, ['projetado', 'realizado'], true)
            ? $tab
            : 'projetado';
    }

    /**
     * Fase 3: janela do Realizado em meses. Default 12; clamp 1..36.
     */
    private function resolveMeses(Request $request): int
    {
        $meses = (int) $request->integer('meses', 12);

        return max(1, min(36, $meses));
    }
}
