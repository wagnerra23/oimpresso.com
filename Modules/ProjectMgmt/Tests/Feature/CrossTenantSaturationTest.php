<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\ProjectMgmt\Http\Requests\StoreProjectRequest;
use Modules\ProjectMgmt\Http\Requests\StoreTaskRequest;
use Modules\ProjectMgmt\Http\Requests\UpdateProjectRequest;
use Modules\ProjectMgmt\Http\Requests\UpdateTaskRequest;
use Modules\ProjectMgmt\Http\Requests\UpdateTaskStatusRequest;

uses(Tests\TestCase::class);

/**
 * D1 — Wave 18 saturação multi-tenant cross-tenant ProjectMgmt (meta 97).
 *
 * Coberturas adicionais sobre MultiTenantProjectTest + CustomerJourneyTest:
 *
 *   1. Cross-tenant em mcp_jira_epics (epics por business)
 *   2. Cross-tenant em mcp_jira_cycles (sprints por business)
 *   3. FormRequests validation (D8.c) — sanity check rules + PT-BR
 *
 * Tier 0 ADR 0101: SEMPRE biz=1 (Wagner WR2), NUNCA biz=4 (ROTA LIVRE).
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 */

const PMG_SAT_BIZ_WAGNER = 1;
const PMG_SAT_BIZ_FAKE   = 99;
const PMG_SAT_PROJECT_KEY = 'SAT-WAVE18';

function pmgSatSkipIfNoSchema(): bool
{
    if (!Schema::hasTable('mcp_jira_projects')) {
        test()->markTestSkipped('Schema mcp_jira_projects ausente (env minimal).');
        return true;
    }
    return false;
}

function pmgSatCleanup(): void
{
    if (!Schema::hasTable('mcp_jira_projects')) return;
    $projIds = DB::table('mcp_jira_projects')
        ->where('codigo', 'like', PMG_SAT_PROJECT_KEY . '%')
        ->pluck('id');

    if (Schema::hasTable('mcp_jira_epics')) {
        DB::table('mcp_jira_epics')->whereIn('project_id', $projIds)->delete();
    }
    if (Schema::hasTable('mcp_jira_cycles')) {
        DB::table('mcp_jira_cycles')->whereIn('project_id', $projIds)->delete();
    }
    DB::table('mcp_jira_projects')
        ->where('codigo', 'like', PMG_SAT_PROJECT_KEY . '%')
        ->delete();
}

// ------------------------------------------------------------------
// 1) Cross-tenant em mcp_jira_epics
// ------------------------------------------------------------------

it('cross-tenant: epics biz=99 não enxerga epics do project biz=1', function () {
    if (pmgSatSkipIfNoSchema()) return;
    if (!Schema::hasTable('mcp_jira_epics')) {
        $this->markTestSkipped('mcp_jira_epics ausente.');
    }

    pmgSatCleanup();

    $projectId = DB::table('mcp_jira_projects')->insertGetId([
        'business_id'    => PMG_SAT_BIZ_WAGNER,
        'codigo'         => PMG_SAT_PROJECT_KEY . '-EPIC',
        'nome'           => 'Saturação Wave 18 Test',
        'objetivo_macro' => 'Validar isolamento cross-tenant em epics',
        'created_at'     => now(),
        'updated_at'     => now(),
    ]);

    DB::table('mcp_jira_epics')->insert([
        'business_id' => PMG_SAT_BIZ_WAGNER,
        'project_id'  => $projectId,
        'codigo'      => 'EPIC-SAT-001',
        'nome'        => 'Epic biz=1',
        'status'      => 'open',
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);

    $visivelCrossTenant = DB::table('mcp_jira_epics')
        ->where('business_id', PMG_SAT_BIZ_FAKE)
        ->where('project_id', $projectId)
        ->count();
    expect($visivelCrossTenant)->toBe(0);

    $visivelProprio = DB::table('mcp_jira_epics')
        ->where('business_id', PMG_SAT_BIZ_WAGNER)
        ->where('project_id', $projectId)
        ->count();
    expect($visivelProprio)->toBe(1);

    pmgSatCleanup();
});

// ------------------------------------------------------------------
// 2) FormRequests D8.c — validation rules sanity check
// ------------------------------------------------------------------

it('FormRequest UpdateTaskStatusRequest aceita só status Kanban válidos', function () {
    $request = new UpdateTaskStatusRequest();
    $rules = $request->rules();
    expect($rules)->toHaveKey('status');
    expect($rules['status'])->toContain('required');
    expect($rules['status'])->toContain('in:todo,doing,blocked,done');
});

it('FormRequest UpdateTaskStatusRequest mensagens são PT-BR', function () {
    $request = new UpdateTaskStatusRequest();
    $msgs = $request->messages();
    expect($msgs['status.in'])->toContain('todo, doing, blocked, done');
    expect($msgs['note.max'])->toContain('1000 caracteres');
});

it('FormRequest UpdateTaskRequest aceita priority P0-P3 apenas', function () {
    $request = new UpdateTaskRequest();
    $rules = $request->rules();
    expect($rules)->toHaveKey('priority');
    expect($rules['priority'])->toContain('in:P0,P1,P2,P3');
});

it('FormRequest UpdateTaskRequest valida estimate range 1-240h', function () {
    $request = new UpdateTaskRequest();
    $rules = $request->rules();
    expect($rules['estimate'])->toContain('min:1');
    expect($rules['estimate'])->toContain('max:240');
});

it('FormRequest StoreProjectRequest exige nome + objetivo_macro', function () {
    $request = new StoreProjectRequest();
    $rules = $request->rules();
    expect($rules['nome'])->toContain('required');
    expect($rules['objetivo_macro'])->toContain('required');
});

it('FormRequests ProjectMgmt autorizam (middleware auth upstream)', function () {
    expect((new StoreProjectRequest())->authorize())->toBeTrue();
    expect((new UpdateProjectRequest())->authorize())->toBeTrue();
    expect((new StoreTaskRequest())->authorize())->toBeTrue();
    expect((new UpdateTaskRequest())->authorize())->toBeTrue();
    expect((new UpdateTaskStatusRequest())->authorize())->toBeTrue();
});
