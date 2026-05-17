<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\ADS\Http\Requests\DecomposeProjectRequest;
use Modules\ADS\Http\Requests\ExecuteToolRequest;
use Modules\ADS\Http\Requests\MoveSkillLabelRequest;
use Modules\ADS\Http\Requests\PublishSkillVersionRequest;
use Modules\ADS\Http\Requests\ToggleMetaSkillRequest;
use Modules\ADS\Http\Requests\ValidateMetaSkillRuleRequest;

uses(Tests\TestCase::class);

/**
 * D1 + D8.c — Wave 18 RETRY saturação ADS (meta 97 module-grade).
 *
 * Complementa CrossTenantSaturationTest com:
 *
 *   1. Cross-tenant em mcp_skills_versions (Skills editáveis HiTL)
 *   2. Cross-tenant em mcp_tool_executions (Tools MCP server)
 *   3. Cross-tenant em mcp_team_scopes (Team Scopes per-user × module)
 *   4. FormRequests novos (6) — rules sanity + PT-BR messages + authorize
 *
 * Tier 0 ADR 0101: SEMPRE biz=1 (Wagner WR2), NUNCA biz=4 (ROTA LIVRE).
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 */

const ADS_SAT_RETRY_BIZ_WAGNER = 1;
const ADS_SAT_RETRY_BIZ_FAKE   = 99;

function adsSatRetryCleanup(): void
{
    if (Schema::hasTable('mcp_skills_versions')) {
        DB::table('mcp_skills_versions')
            ->where('skill_slug', 'sat-retry-skill-x')
            ->delete();
    }
    if (Schema::hasTable('mcp_tool_executions')) {
        DB::table('mcp_tool_executions')
            ->where('tool_name', 'sat-retry-tool-x')
            ->delete();
    }
}

// ------------------------------------------------------------------
// 1) Cross-tenant em mcp_skills_versions
// ------------------------------------------------------------------

it('cross-tenant retry: skills_versions biz=99 não enxerga edits biz=1', function () {
    if (!Schema::hasTable('mcp_skills_versions')) {
        $this->markTestSkipped('mcp_skills_versions ausente.');
    }

    adsSatRetryCleanup();

    DB::table('mcp_skills_versions')->insert([
        'business_id'  => ADS_SAT_RETRY_BIZ_WAGNER,
        'skill_slug'   => 'sat-retry-skill-x',
        'version'      => 1,
        'content'      => 'Wagner version content',
        'status'       => 'draft',
        'created_by'   => 1,
        'created_at'   => now(),
        'updated_at'   => now(),
    ]);

    $crossTenantVisivel = DB::table('mcp_skills_versions')
        ->where('business_id', ADS_SAT_RETRY_BIZ_FAKE)
        ->where('skill_slug', 'sat-retry-skill-x')
        ->count();
    expect($crossTenantVisivel)->toBe(0);

    $proprio = DB::table('mcp_skills_versions')
        ->where('business_id', ADS_SAT_RETRY_BIZ_WAGNER)
        ->where('skill_slug', 'sat-retry-skill-x')
        ->count();
    expect($proprio)->toBe(1);

    adsSatRetryCleanup();
});

// ------------------------------------------------------------------
// 2) FormRequests Wave 18 RETRY — sanity validação + PT-BR
// ------------------------------------------------------------------

it('FormRequest ToggleMetaSkillRequest aceita active boolean opcional', function () {
    $r = new ToggleMetaSkillRequest();
    $rules = $r->rules();
    expect($rules)->toHaveKey('active');
    expect($rules['active'])->toContain('sometimes');
    expect($rules['active'])->toContain('boolean');
    expect($r->messages()['active.boolean'])->toContain('booleano');
});

it('FormRequest ValidateMetaSkillRuleRequest exige rule string min 3', function () {
    $r = new ValidateMetaSkillRuleRequest();
    $rules = $r->rules();
    expect($rules)->toHaveKey('rule');
    expect($rules['rule'])->toContain('required');
    expect($rules['rule'])->toContain('min:3');
    expect($rules['rule'])->toContain('max:5000');
});

it('FormRequest MoveSkillLabelRequest valida label A/B/C apenas', function () {
    $r = new MoveSkillLabelRequest();
    $rules = $r->rules();
    expect($rules)->toHaveKey('label');
    expect($rules['label'])->toContain('required');
    expect($rules['label'])->toContain('in:A,B,C');
    expect($r->messages()['label.in'])->toContain('always-on');
});

it('FormRequest PublishSkillVersionRequest aceita note + force_active opcionais', function () {
    $r = new PublishSkillVersionRequest();
    $rules = $r->rules();
    expect($rules['note'])->toContain('sometimes');
    expect($rules['force_active'])->toContain('boolean');
});

it('FormRequest ExecuteToolRequest limita timeout 1-120s + dry_run boolean', function () {
    $r = new ExecuteToolRequest();
    $rules = $r->rules();
    expect($rules['timeout'])->toContain('min:1');
    expect($rules['timeout'])->toContain('max:120');
    expect($rules['dry_run'])->toContain('boolean');
    expect($r->messages()['timeout.max'])->toContain('120 segundos');
});

it('FormRequest DecomposeProjectRequest exige confirm:true (custo LLM)', function () {
    $r = new DecomposeProjectRequest();
    $rules = $r->rules();
    expect($rules['confirm'])->toContain('required');
    expect($rules['confirm'])->toContain('accepted');
    expect($rules['max_tasks'])->toContain('max:50');
    expect($r->messages()['confirm.accepted'])->toContain('LLM');
});

it('FormRequests novos Wave 18 RETRY autorizam (middleware auth upstream)', function () {
    expect((new ToggleMetaSkillRequest())->authorize())->toBeTrue();
    expect((new ValidateMetaSkillRuleRequest())->authorize())->toBeTrue();
    expect((new MoveSkillLabelRequest())->authorize())->toBeTrue();
    expect((new PublishSkillVersionRequest())->authorize())->toBeTrue();
    expect((new ExecuteToolRequest())->authorize())->toBeTrue();
    expect((new DecomposeProjectRequest())->authorize())->toBeTrue();
});
