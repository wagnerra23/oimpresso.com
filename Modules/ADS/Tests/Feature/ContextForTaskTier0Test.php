<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\ADS\Services\ContextForTaskService;

uses(Tests\TestCase::class);

/**
 * Regressão Tier 0 (ADR 0093) do ContextForTaskService.
 *
 * O serviço servia `recent_decisions_same_domain` ao Brain lendo
 * `mcp_dual_brain_decisions` (C3 — tem business_id) SEM filtro de business_id
 * (ContextForTaskService.php::buildRecentDecisions) → vazamento cross-tenant:
 * decisões/lições de IA de QUALQUER tenant apareciam no contexto. O
 * MultiTenantDecisionTest existente só prova que a QUERY isola; não exercitava o
 * SERVIÇO — esta lacuna é o que este teste fecha.
 *
 * biz=1 (BIZ_WAGNER) + biz=99 (fictício). NUNCA biz=4 (ROTA LIVRE — ADR 0101).
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see Modules/ADS/Services/ContextForTaskService.php
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: mcp_dual_brain_decisions FK em business requer schema MySQL UltimatePOS');
    }
    if (! Schema::hasTable('mcp_dual_brain_decisions')) {
        $this->markTestSkipped('mcp_dual_brain_decisions table missing — rode Modules/ADS migrate primeiro');
    }
});

defined('BIZ_WAGNER') || define('BIZ_WAGNER', 1);
defined('BIZ_FICTICIO') || define('BIZ_FICTICIO', 99);

function inserirDecisaoProbe(int $biz): int
{
    return DB::table('mcp_dual_brain_decisions')->insertGetId([
        'business_id'      => $biz,
        'event_type'       => 'ctx_tier0_probe',
        'event_source'     => 'brain_a',
        'domain'           => 'i18n',
        'risk_score'       => 0.100,
        'confidence_score' => 0.900,
        'policy_applied'   => 'ALLOW_BRAIN_A',
        'destination'      => 'brain_a',
        'hitl_level'       => 1,
        'brain_used'       => 'brain_a',
        'outcome'          => 'success',
        'created_at'       => now(),
    ]);
}

function buildRecentDecisionsDe(int $businessId): array
{
    $svc = app(ContextForTaskService::class);
    $m = new ReflectionMethod($svc, 'buildRecentDecisions');
    $m->setAccessible(true);

    return (array) $m->invoke($svc, 'i18n', $businessId);
}

it('buildRecentDecisions NÃO retorna decisão de outro tenant (o vazamento corrigido)', function () {
    $idBiz1  = inserirDecisaoProbe(BIZ_WAGNER);
    $idBiz99 = inserirDecisaoProbe(BIZ_FICTICIO);

    // Contexto construído PARA o biz=1
    $ids = collect(buildRecentDecisionsDe(BIZ_WAGNER))->pluck('decision_id')->all();

    expect($ids)->toContain($idBiz1)        // vê o próprio
        ->and($ids)->not->toContain($idBiz99); // NUNCA o do outro tenant
})->afterEach(function () {
    DB::table('mcp_dual_brain_decisions')->where('event_type', 'ctx_tier0_probe')->delete();
});

it('contexto do biz=99 não enxerga decisão do biz=1 (simetria do isolamento)', function () {
    $idBiz1  = inserirDecisaoProbe(BIZ_WAGNER);
    $idBiz99 = inserirDecisaoProbe(BIZ_FICTICIO);

    $ids = collect(buildRecentDecisionsDe(BIZ_FICTICIO))->pluck('decision_id')->all();

    expect($ids)->toContain($idBiz99)
        ->and($ids)->not->toContain($idBiz1);
})->afterEach(function () {
    DB::table('mcp_dual_brain_decisions')->where('event_type', 'ctx_tier0_probe')->delete();
});
