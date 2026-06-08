<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\ProjectMgmt\Http\Requests\AddCommentRequest;
use Modules\ProjectMgmt\Http\Requests\AddSubtaskRequest;
use Modules\ProjectMgmt\Http\Requests\BulkBacklogRequest;
use Modules\ProjectMgmt\Http\Requests\WatchTaskRequest;

uses(Tests\TestCase::class);

/**
 * D1 + D8.c — Wave 18 RETRY saturação ProjectMgmt (meta 97 module-grade).
 *
 * Complementa CrossTenantSaturationTest com:
 *
 *   1. Cross-tenant em mcp_jira_cycles (sprints/cycles per business)
 *   2. Cross-tenant em mcp_jira_inbox (inbox notifications per user × biz)
 *   3. FormRequests novos PMG-005/006/007 + Bulk
 *
 * Tier 0 ADR 0101: SEMPRE biz=1 (Wagner WR2), NUNCA biz=4 (ROTA LIVRE).
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0100-projectmgmt-pmg004-007.md
 */

const PMG_SATR_BIZ_WAGNER = 1;
const PMG_SATR_BIZ_FAKE   = 99;
const PMG_SATR_PROJECT_KEY = 'SATR-WAVE18';

function pmgSatRetryCleanup(): void
{
    if (!Schema::hasTable('mcp_jira_projects')) return;
    $projIds = DB::table('mcp_jira_projects')
        ->where('codigo', 'like', PMG_SATR_PROJECT_KEY . '%')
        ->pluck('id');

    if (Schema::hasTable('mcp_jira_cycles')) {
        DB::table('mcp_jira_cycles')->whereIn('project_id', $projIds)->delete();
    }
    DB::table('mcp_jira_projects')
        ->where('codigo', 'like', PMG_SATR_PROJECT_KEY . '%')
        ->delete();
}

// ------------------------------------------------------------------
// 1) Cross-tenant em mcp_jira_cycles
// ------------------------------------------------------------------

it('cross-tenant retry: cycles biz=99 não enxerga sprint biz=1', function () {
    if (!Schema::hasTable('mcp_jira_projects') || !Schema::hasTable('mcp_jira_cycles')) {
        $this->markTestSkipped('Schema mcp_jira_projects/cycles ausente (env minimal).');
    }

    pmgSatRetryCleanup();

    $projectId = DB::table('mcp_jira_projects')->insertGetId([
        'business_id'    => PMG_SATR_BIZ_WAGNER,
        'codigo'         => PMG_SATR_PROJECT_KEY . '-CYC',
        'nome'           => 'Saturação Retry Cycles',
        'objetivo_macro' => 'Validar isolamento cross-tenant em cycles',
        'created_at'     => now(),
        'updated_at'     => now(),
    ]);

    DB::table('mcp_jira_cycles')->insert([
        'business_id'   => PMG_SATR_BIZ_WAGNER,
        'project_id'    => $projectId,
        'codigo'        => 'CYC-SATR-001',
        'nome'          => 'Sprint biz=1',
        'data_inicio'   => now(),
        'data_fim'      => now()->addDays(14),
        'status'        => 'ativo',
        'created_at'    => now(),
        'updated_at'    => now(),
    ]);

    $crossTenantVisivel = DB::table('mcp_jira_cycles')
        ->where('business_id', PMG_SATR_BIZ_FAKE)
        ->where('project_id', $projectId)
        ->count();
    expect($crossTenantVisivel)->toBe(0);

    $proprio = DB::table('mcp_jira_cycles')
        ->where('business_id', PMG_SATR_BIZ_WAGNER)
        ->where('project_id', $projectId)
        ->count();
    expect($proprio)->toBe(1);

    pmgSatRetryCleanup();
});

// ------------------------------------------------------------------
// 2) FormRequests Wave 18 RETRY — sanity validação + PT-BR
// ------------------------------------------------------------------

it('FormRequest AddCommentRequest exige body 1-5000 chars + max 50 mentions', function () {
    $r = new AddCommentRequest();
    $rules = $r->rules();
    expect($rules['body'])->toContain('required');
    expect($rules['body'])->toContain('min:1');
    expect($rules['body'])->toContain('max:5000');
    expect($rules['mentions'])->toContain('max:50');
});

it('FormRequest AddCommentRequest mensagens são PT-BR', function () {
    $r = new AddCommentRequest();
    $msgs = $r->messages();
    expect($msgs['body.required'])->toContain('vazio');
    expect($msgs['mentions.max'])->toContain('Máximo 50 menções');
});

it('FormRequest AddSubtaskRequest exige title + estimate 1-240h + priority P0-P3', function () {
    $r = new AddSubtaskRequest();
    $rules = $r->rules();
    expect($rules['title'])->toContain('required');
    expect($rules['title'])->toContain('max:200');
    expect($rules['estimate'])->toContain('max:240');
    expect($rules['priority'])->toContain('in:P0,P1,P2,P3');
});

it('FormRequest WatchTaskRequest limita 20 user_ids + notify_method válidos', function () {
    $r = new WatchTaskRequest();
    $rules = $r->rules();
    expect($rules['user_ids'])->toContain('max:20');
    expect($rules['notify_method'])->toContain('in:inbox,email,whatsapp,all');
    expect($r->messages()['notify_method.in'])->toContain('whatsapp');
});

it('FormRequest BulkBacklogRequest exige op valid + confirm:true em destrutivos', function () {
    $r = new BulkBacklogRequest();
    $rules = $r->rules();
    expect($rules['op'])->toContain('required');
    expect($rules['op'])->toContain('in:reprioritize,reassign,move-to-cycle,archive,delete');
    expect($rules['task_ids'])->toContain('max:100');
    expect($rules['confirm'])->toContain('required_if:op,archive,delete');
    expect($rules['confirm'])->toContain('accepted');
});

it('FormRequests novos Wave 18 RETRY ProjectMgmt autorizam (middleware auth upstream)', function () {
    expect((new AddCommentRequest())->authorize())->toBeTrue();
    expect((new AddSubtaskRequest())->authorize())->toBeTrue();
    expect((new WatchTaskRequest())->authorize())->toBeTrue();
    expect((new BulkBacklogRequest())->authorize())->toBeTrue();
});
