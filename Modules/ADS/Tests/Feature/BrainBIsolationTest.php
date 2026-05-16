<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

/**
 * Testa isolamento Brain B per-business — cost/audit/trace separado.
 *
 * ARQ-0002: Brain B = Sonnet/Opus chamado via Anthropic API (caro).
 * Cenário crítico: cost_usd biz=1 NÃO pode contar pra agregação biz=99
 * (cobrança de IA per-tenant futura — ADR 0094 §3 tiered cost).
 *
 * @see memory/requisitos/ADS/adr/arq/ARQ-0002-dual-brain-papeis.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: mcp_dual_brain_decisions FK em business requer schema MySQL UltimatePOS');
    }
    if (! Schema::hasTable('mcp_dual_brain_decisions')) {
        $this->markTestSkipped('mcp_dual_brain_decisions table missing — rode Modules/ADS migrate primeiro');
    }
});

const BIZ_WAGNER = 1;
const BIZ_FICTICIO = 99;

it('Brain B trace biz=1 não contribui pra cost agregado biz=99', function () {
    DB::table('mcp_dual_brain_decisions')->insert([
        'business_id'         => BIZ_WAGNER,
        'event_type'          => 'lgpd_data_handling',
        'event_source'        => 'brain_a',
        'domain'              => 'security',
        'risk_score'          => 0.700,
        'confidence_score'    => 0.600,
        'policy_applied'      => 'REQUIRE_BRAIN_B',
        'destination'         => 'brain_b',
        'hitl_level'          => 3,
        'brain_used'          => 'brain_b',
        'model_used'          => 'claude-sonnet-4-6',
        'tokens_used'         => 8500,
        'cost_usd'            => 0.085000,
        'execution_ms'        => 4200,
        'outcome'             => 'success',
        'created_at'          => now(),
    ]);

    $custoBiz99 = DB::table('mcp_dual_brain_decisions')
        ->where('business_id', BIZ_FICTICIO)
        ->where('brain_used', 'brain_b')
        ->sum('cost_usd');

    expect((float) $custoBiz99)->toBe(0.0);
})->afterEach(function () {
    DB::table('mcp_dual_brain_decisions')
        ->where('event_type', 'lgpd_data_handling')
        ->where('business_id', BIZ_WAGNER)
        ->delete();
});

it('Brain B audit trail (tokens/model) biz=1 isolado de biz=99', function () {
    DB::table('mcp_dual_brain_decisions')->insert([
        'business_id'      => BIZ_WAGNER,
        'event_type'       => 'db_schema_change',
        'event_source'     => 'brain_a',
        'domain'           => 'schema',
        'risk_score'       => 0.800,
        'confidence_score' => 0.550,
        'policy_applied'   => 'REQUIRE_BRAIN_B',
        'destination'      => 'brain_b',
        'hitl_level'       => 3,
        'brain_used'       => 'brain_b',
        'model_used'       => 'claude-opus-4-7',
        'tokens_used'      => 15000,
        'cost_usd'         => 0.450000,
        'outcome'          => 'success',
        'created_at'       => now(),
    ]);

    // Audit de outro tenant não vê
    $tracesBiz99 = DB::table('mcp_dual_brain_decisions')
        ->where('business_id', BIZ_FICTICIO)
        ->where('brain_used', 'brain_b')
        ->get();

    expect($tracesBiz99)->toHaveCount(0);

    // Audit do biz dono vê
    $tracesBiz1 = DB::table('mcp_dual_brain_decisions')
        ->where('business_id', BIZ_WAGNER)
        ->where('event_type', 'db_schema_change')
        ->get();

    expect($tracesBiz1)->toHaveCount(1);
    expect($tracesBiz1->first()->model_used)->toBe('claude-opus-4-7');
})->afterEach(function () {
    DB::table('mcp_dual_brain_decisions')
        ->where('event_type', 'db_schema_change')
        ->where('business_id', BIZ_WAGNER)
        ->delete();
});

it('Brain B wagner_modified output não vaza pra biz adjacente', function () {
    // Wagner modificou output do Brain B no biz=1 — auditoria sensível
    DB::table('mcp_dual_brain_decisions')->insert([
        'business_id'         => BIZ_WAGNER,
        'event_type'          => 'security_rule_change',
        'event_source'        => 'wagner',
        'domain'              => 'security',
        'risk_score'          => 0.750,
        'confidence_score'    => 0.700,
        'policy_applied'      => 'REQUIRE_BRAIN_B',
        'destination'         => 'brain_b',
        'hitl_level'          => 3,
        'brain_used'          => 'brain_b',
        'model_used'          => 'claude-sonnet-4-6',
        'instruction_generated' => 'ajustar rate limit ADS biz=1',
        'wagner_modified_to'  => 'ajustar rate limit ADS biz=1 + log alert',
        'outcome'             => 'wagner_modified',
        'created_at'          => now(),
    ]);

    $vazamento = DB::table('mcp_dual_brain_decisions')
        ->where('business_id', BIZ_FICTICIO)
        ->whereNotNull('wagner_modified_to')
        ->get();

    expect($vazamento)->toHaveCount(0);
})->afterEach(function () {
    DB::table('mcp_dual_brain_decisions')
        ->where('event_type', 'security_rule_change')
        ->where('business_id', BIZ_WAGNER)
        ->delete();
});
