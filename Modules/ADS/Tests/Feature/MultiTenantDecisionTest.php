<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

/**
 * Testa isolamento multi-tenant Tier 0 da Decision Memory do ADS (ARQ-0009).
 *
 * ADR 0093: decisões PolicyEngine/RiskEngine/Router NUNCA podem vazar entre business.
 * Cenário crítico: Brain B aprovou refund biz=1; biz=99 NÃO pode enxergar decisão.
 *
 * NUNCA usar biz=4 (ROTA LIVRE — Larissa produção) — ADR 0101.
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 * @see memory/requisitos/ADS/adr/arq/ARQ-0009-decision-memory-schema.md
 */

// Guard SQLite + table presence
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

it('decisão biz=1 NÃO aparece com filtro biz=99', function () {
    $id = DB::table('mcp_dual_brain_decisions')->insertGetId([
        'business_id'      => BIZ_WAGNER,
        'event_type'       => 'lang_file_pt_br',
        'event_source'     => 'brain_a',
        'domain'           => 'i18n',
        'risk_score'       => 0.150,
        'confidence_score' => 0.850,
        'policy_applied'   => 'ALLOW_BRAIN_A',
        'destination'      => 'brain_a',
        'hitl_level'       => 1,
        'brain_used'       => 'brain_a',
        'outcome'          => 'success',
        'created_at'       => now(),
    ]);

    $resultado = DB::table('mcp_dual_brain_decisions')
        ->where('id', $id)
        ->where('business_id', BIZ_FICTICIO)
        ->get();

    expect($resultado)->toHaveCount(0);
})->afterEach(function () {
    DB::table('mcp_dual_brain_decisions')
        ->where('event_type', 'lang_file_pt_br')
        ->where('business_id', BIZ_WAGNER)
        ->delete();
});

it('decisão biz=1 aparece com filtro biz=1', function () {
    $id = DB::table('mcp_dual_brain_decisions')->insertGetId([
        'business_id'      => BIZ_WAGNER,
        'event_type'       => 'session_log_creation',
        'event_source'     => 'brain_a',
        'domain'           => 'memory',
        'risk_score'       => 0.100,
        'confidence_score' => 0.900,
        'policy_applied'   => 'ALLOW_BRAIN_A',
        'destination'      => 'brain_a',
        'hitl_level'       => 1,
        'brain_used'       => 'brain_a',
        'outcome'          => 'success',
        'created_at'       => now(),
    ]);

    $resultado = DB::table('mcp_dual_brain_decisions')
        ->where('id', $id)
        ->where('business_id', BIZ_WAGNER)
        ->get();

    expect($resultado)->toHaveCount(1);
    expect($resultado->first()->event_type)->toBe('session_log_creation');
})->afterEach(function () {
    DB::table('mcp_dual_brain_decisions')
        ->where('event_type', 'session_log_creation')
        ->where('business_id', BIZ_WAGNER)
        ->delete();
});

it('REQUIRE_HUMAN_REVIEW biz=1 não aparece em fila pending biz=99', function () {
    // Wagner aprovou deploy production no biz=1 → biz=99 NÃO deve ver pending
    DB::table('mcp_dual_brain_decisions')->insert([
        'business_id'      => BIZ_WAGNER,
        'event_type'       => 'production_deploy',
        'event_source'     => 'wagner',
        'domain'           => 'deploy',
        'risk_score'       => 0.950,
        'confidence_score' => 0.500,
        'policy_applied'   => 'REQUIRE_HUMAN_REVIEW',
        'destination'      => 'pending_wagner',
        'hitl_level'       => 4,
        'brain_used'       => 'none',
        'outcome'          => 'cancelled',
        'created_at'       => now(),
    ]);

    $pendentesBiz99 = DB::table('mcp_dual_brain_decisions')
        ->where('business_id', BIZ_FICTICIO)
        ->where('destination', 'pending_wagner')
        ->count();

    expect($pendentesBiz99)->toBe(0);
})->afterEach(function () {
    DB::table('mcp_dual_brain_decisions')
        ->where('event_type', 'production_deploy')
        ->where('business_id', BIZ_WAGNER)
        ->delete();
});
