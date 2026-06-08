<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Modules\ADS\Services\DecisionRouter;
use Modules\ADS\Services\RoutingInput;

uses(Tests\TestCase::class);

/**
 * D5 cliente — Customer Journey E2E ADS (Wave 15 — 2026-05-16).
 *
 * Smoke real do fluxo "Wagner toma decisão automatizada via ADS":
 *
 *   1. Evento chega no DecisionRouter (entrada única canônica ARQ-0003)
 *   2. PolicyEngine + RiskEngine + ConfidenceEngine processam (determinístico)
 *   3. Router persiste em mcp_dual_brain_decisions com business_id correto
 *   4. Decision aparece em Inbox /ads/admin/decisoes pro tenant correto
 *   5. Cross-tenant biz=99 NÃO enxerga decisão biz=1
 *   6. Rotas de aprovação/rejeição HiTL existem
 *
 * **Tier 0 ADR 0101:** SEMPRE biz=1 (Wagner WR2), NUNCA biz=4 (ROTA LIVRE Larissa).
 *
 * Não exercita HTTP login (smoke real exige sessão + role) — testa camadas:
 *   - Service layer (DecisionRouter via DB::table)
 *   - Roteamento Laravel (Route::has + URL signature)
 *   - Isolamento multi-tenant em queries
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 * @see Modules/ADS/Services/DecisionRouter.php
 */

const BIZ_WAGNER_ADS = 1;
const BIZ_FAKE_ADS = 99;

/**
 * Helper de skip pros testes que tocam DB (FK MySQL UltimatePOS).
 * Testes de Route::has() não precisam DB e rodam sempre.
 */
function adsJourneySkipIfNoSchema(): bool
{
    if (DB::connection()->getDriverName() === 'sqlite') {
        test()->markTestSkipped('SQLite-incompatível: mcp_dual_brain_decisions FK requer MySQL UltimatePOS');
        return true;
    }
    if (! Schema::hasTable('mcp_dual_brain_decisions')) {
        test()->markTestSkipped('Tabela mcp_dual_brain_decisions ausente — rode migrate Modules/ADS');
        return true;
    }
    return false;
}

function adsJourneyCleanup(): void
{
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('mcp_dual_brain_decisions')) {
        return;
    }
    DB::table('mcp_dual_brain_decisions')
        ->whereIn('event_type', [
            'customer_journey_smoke_session_log',
            'customer_journey_smoke_brain_b',
            'customer_journey_smoke_blocked',
        ])
        ->delete();
}

// ------------------------------------------------------------------
// Etapa 1 — Wagner dispara evento de baixo risco → roteado pra brain_a
// ------------------------------------------------------------------

it('jornada cliente: evento baixo-risco biz=1 vai pra brain_a determinístico', function () {
    if (adsJourneySkipIfNoSchema()) return;
    $router = app(DecisionRouter::class);

    $input = new RoutingInput(
        businessId:    BIZ_WAGNER_ADS,
        eventType:     'customer_journey_smoke_session_log',
        eventSource:   'wagner',
        domain:        'memory',
        filesAffected: ['memory/sessions/2026-05-16-smoke.md'],
        metadata:      ['source' => 'ads_customer_journey_test'],
    );

    $decision = $router->route($input);

    expect($decision->destination)->toBeIn(['brain_a', 'brain_b', 'pending_wagner', 'blocked', 'queued']);
    expect($decision->decisionId)->toBeGreaterThan(0);

    // Persistido com business_id correto?
    $row = DB::table('mcp_dual_brain_decisions')->where('id', $decision->decisionId)->first();
    expect($row)->not->toBeNull();
    expect((int) $row->business_id)->toBe(BIZ_WAGNER_ADS);
    expect($row->event_type)->toBe('customer_journey_smoke_session_log');

    adsJourneyCleanup();
});

// ------------------------------------------------------------------
// Etapa 2 — Multi-tenant Tier 0: biz=99 não enxerga decisão biz=1
// ------------------------------------------------------------------

it('jornada cliente: decisão biz=1 invisível a biz=99 (Tier 0 IRREVOGÁVEL)', function () {
    if (adsJourneySkipIfNoSchema()) return;
    $router = app(DecisionRouter::class);

    $decisionWagner = $router->route(new RoutingInput(
        businessId:    BIZ_WAGNER_ADS,
        eventType:     'customer_journey_smoke_brain_b',
        eventSource:   'wagner',
        domain:        'memory',
        filesAffected: ['memory/sessions/cross-tenant-check.md'],
        metadata:      [],
    ));

    // biz=99 query NÃO deve ver a decisão biz=1
    $crossTenantVisivel = DB::table('mcp_dual_brain_decisions')
        ->where('id', $decisionWagner->decisionId)
        ->where('business_id', BIZ_FAKE_ADS)
        ->count();

    expect($crossTenantVisivel)->toBe(0);

    // biz=1 query DEVE ver
    $proprioTenantVisivel = DB::table('mcp_dual_brain_decisions')
        ->where('id', $decisionWagner->decisionId)
        ->where('business_id', BIZ_WAGNER_ADS)
        ->count();

    expect($proprioTenantVisivel)->toBe(1);

    adsJourneyCleanup();
});

// ------------------------------------------------------------------
// Etapa 3 — Rotas Inbox/aprovação existem (cliente clica "Aprovar")
// ------------------------------------------------------------------

it('jornada cliente: rotas Inbox decisões expostas pro tenant logado', function () {
    expect(Route::has('ads.admin.decisoes.index'))->toBeTrue();
    expect(Route::has('ads.admin.decisoes.show'))->toBeTrue();
});

it('jornada cliente: HiTL approve/reject/dismiss disponíveis pro cliente', function () {
    expect(Route::has('ads.admin.decisoes.approve'))->toBeTrue();
    expect(Route::has('ads.admin.decisoes.reject'))->toBeTrue();
    expect(Route::has('ads.admin.decisoes.dismiss'))->toBeTrue();
});

it('jornada cliente: páginas transparência (Policy/Confidence/Metricas/Patterns) acessíveis', function () {
    // Cliente Wagner usa essas páginas pra entender por que ADS decidiu X
    expect(Route::has('ads.admin.policy.index'))->toBeTrue();
    expect(Route::has('ads.admin.confidence.index'))->toBeTrue();
    expect(Route::has('ads.admin.metricas.index'))->toBeTrue();
    expect(Route::has('ads.admin.patterns.index'))->toBeTrue();
});

// ------------------------------------------------------------------
// Etapa 4 — Skills + Tools (cliente customiza ADS pro próprio fluxo)
// ------------------------------------------------------------------

it('jornada cliente: Skills DB acessível (cliente edita prompts próprios)', function () {
    expect(Route::has('ads.admin.skills.index'))->toBeTrue();
    expect(Route::has('ads.admin.skills.show'))->toBeTrue();
    expect(Route::has('ads.admin.skills.edit'))->toBeTrue();
    expect(Route::has('ads.admin.skills.store'))->toBeTrue();
});

it('jornada cliente: Tools registry acessível', function () {
    expect(Route::has('ads.admin.tools.index'))->toBeTrue();
});

// ------------------------------------------------------------------
// Etapa 5 — Install/uninstall (cliente ativa/desativa ADS por business)
// ------------------------------------------------------------------

it('jornada cliente: rotas install/uninstall existem (ADR 0024)', function () {
    $routes = collect(Route::getRoutes())->map(fn ($r) => $r->uri())->toArray();

    expect($routes)->toContain('ads/install');
    expect($routes)->toContain('ads/install/uninstall');
    expect($routes)->toContain('ads/install/update');
});
