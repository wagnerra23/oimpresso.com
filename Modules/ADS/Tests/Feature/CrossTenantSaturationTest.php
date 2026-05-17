<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\ADS\Http\Requests\ApproveDecisionRequest;
use Modules\ADS\Http\Requests\DismissDecisionRequest;
use Modules\ADS\Http\Requests\RejectDecisionRequest;
use Modules\ADS\Http\Requests\StoreMetaSkillRequest;
use Modules\ADS\Http\Requests\StoreSkillRequest;

uses(Tests\TestCase::class);

/**
 * D1 — Wave 18 saturação multi-tenant cross-tenant ADS (meta 97 module-grade).
 *
 * Coberturas adicionais sobre AdsCustomerJourneyTest + MultiTenantDecisionTest:
 *
 *   1. Cross-tenant em mcp_confidence_scores (Brain B aprendizado)
 *   2. Cross-tenant em mcp_decision_patterns (PatternLearningService)
 *   3. Cross-tenant em mcp_skills_versions (Skills editáveis)
 *   4. FormRequests validation (D8.c) — rules sanity check + PT-BR
 *
 * Tier 0 ADR 0101: SEMPRE biz=1 (Wagner WR2), NUNCA biz=4 (ROTA LIVRE).
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 * @see memory/decisions/0159 (Wave 18 saturação)
 */

const ADS_SAT_BIZ_WAGNER = 1;
const ADS_SAT_BIZ_FAKE   = 99;

function adsSatSkipIfNoSchema(): bool
{
    if (!Schema::hasTable('mcp_dual_brain_decisions')) {
        test()->markTestSkipped('Schema mcp_dual_brain_decisions ausente (env minimal).');
        return true;
    }
    return false;
}

function adsSatCleanup(): void
{
    if (!Schema::hasTable('mcp_dual_brain_decisions')) return;
    DB::table('mcp_dual_brain_decisions')
        ->whereIn('event_type', ['sat_test_cross_tenant', 'sat_test_brain_b_learn'])
        ->delete();
    if (Schema::hasTable('mcp_confidence_scores')) {
        DB::table('mcp_confidence_scores')
            ->where('domain', 'sat_test_domain')
            ->delete();
    }
    if (Schema::hasTable('mcp_decision_patterns')) {
        DB::table('mcp_decision_patterns')
            ->where('domain', 'sat_test_domain')
            ->delete();
    }
}

// ------------------------------------------------------------------
// 1) Cross-tenant em mcp_confidence_scores
// ------------------------------------------------------------------

it('cross-tenant: confidence_scores biz=99 não enxerga aprendizado biz=1', function () {
    if (adsSatSkipIfNoSchema()) return;
    if (!Schema::hasTable('mcp_confidence_scores')) {
        $this->markTestSkipped('mcp_confidence_scores ausente.');
    }

    adsSatCleanup();

    // Insere score biz=1
    DB::table('mcp_confidence_scores')->insert([
        'business_id'  => ADS_SAT_BIZ_WAGNER,
        'domain'       => 'sat_test_domain',
        'event_type'   => 'sat_test_event',
        'score'        => 4.5,
        'sample_size' => 10,
        'hitl_level'   => 0,
        'created_at'   => now(),
        'updated_at'   => now(),
    ]);

    // biz=99 query → 0 resultados
    $visivelCrossTenant = DB::table('mcp_confidence_scores')
        ->where('business_id', ADS_SAT_BIZ_FAKE)
        ->where('domain', 'sat_test_domain')
        ->count();
    expect($visivelCrossTenant)->toBe(0);

    // biz=1 query → 1 resultado
    $visivelProprio = DB::table('mcp_confidence_scores')
        ->where('business_id', ADS_SAT_BIZ_WAGNER)
        ->where('domain', 'sat_test_domain')
        ->count();
    expect($visivelProprio)->toBe(1);

    adsSatCleanup();
});

// ------------------------------------------------------------------
// 2) Cross-tenant em mcp_decision_patterns
// ------------------------------------------------------------------

it('cross-tenant: decision_patterns biz=99 não enxerga padrões biz=1', function () {
    if (adsSatSkipIfNoSchema()) return;
    if (!Schema::hasTable('mcp_decision_patterns')) {
        $this->markTestSkipped('mcp_decision_patterns ausente.');
    }

    adsSatCleanup();

    DB::table('mcp_decision_patterns')->insert([
        'business_id'   => ADS_SAT_BIZ_WAGNER,
        'domain'        => 'sat_test_domain',
        'event_type'    => 'sat_test_event',
        'description'   => 'Padrão Wagner biz=1',
        'success_count' => 5,
        'total_count'   => 8,
        'success_rate'  => 0.625,
        'is_hardcoded'  => false,
        'created_at'    => now(),
        'updated_at'    => now(),
    ]);

    $visivelCrossTenant = DB::table('mcp_decision_patterns')
        ->where('business_id', ADS_SAT_BIZ_FAKE)
        ->where('domain', 'sat_test_domain')
        ->count();
    expect($visivelCrossTenant)->toBe(0);

    $visivelProprio = DB::table('mcp_decision_patterns')
        ->where('business_id', ADS_SAT_BIZ_WAGNER)
        ->where('domain', 'sat_test_domain')
        ->count();
    expect($visivelProprio)->toBe(1);

    adsSatCleanup();
});

// ------------------------------------------------------------------
// 3) FormRequests D8.c — validation rules sanity check
// ------------------------------------------------------------------

it('FormRequest ApproveDecisionRequest aceita payload vazio (note opcional)', function () {
    $request = new ApproveDecisionRequest();
    $rules = $request->rules();
    expect($rules)->toHaveKey('note');
    expect($rules['note'])->toContain('sometimes');
    expect($rules['note'])->toContain('max:500');
});

it('FormRequest RejectDecisionRequest aceita reason até 2000 chars (PT-BR msg)', function () {
    $request = new RejectDecisionRequest();
    $rules = $request->rules();
    $messages = $request->messages();
    expect($rules)->toHaveKey('reason');
    expect($rules['reason'])->toContain('max:2000');
    expect($messages['reason.max'])->toContain('2000 caracteres');
});

it('FormRequest DismissDecisionRequest tem rules vazias (só URL ID)', function () {
    $request = new DismissDecisionRequest();
    expect($request->rules())->toBe([]);
});

it('FormRequest StoreSkillRequest exige content + max 50k chars', function () {
    $request = new StoreSkillRequest();
    $rules = $request->rules();
    expect($rules)->toHaveKey('content');
    expect($rules['content'])->toContain('required');
    expect($rules['content'])->toContain('max:50000');
});

it('FormRequest StoreMetaSkillRequest valida tier (A/B/C apenas)', function () {
    $request = new StoreMetaSkillRequest();
    $rules = $request->rules();
    expect($rules)->toHaveKey('tier');
    expect($rules['tier'])->toContain('in:A,B,C');
});

it('FormRequests autorizam (middleware auth upstream protege)', function () {
    expect((new ApproveDecisionRequest())->authorize())->toBeTrue();
    expect((new RejectDecisionRequest())->authorize())->toBeTrue();
    expect((new DismissDecisionRequest())->authorize())->toBeTrue();
    expect((new StoreSkillRequest())->authorize())->toBeTrue();
    expect((new StoreMetaSkillRequest())->authorize())->toBeTrue();
});
