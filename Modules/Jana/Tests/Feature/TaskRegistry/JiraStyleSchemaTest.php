<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Jana\Entities\Mcp\McpCycle;
use Modules\Jana\Entities\Mcp\McpCycleGoal;
use Modules\Jana\Entities\Mcp\McpEpic;
use Modules\Jana\Entities\Mcp\McpInboxNotification;
use Modules\Jana\Entities\Mcp\McpProject;
use Modules\Jana\Entities\Mcp\McpTask;
use Modules\Jana\Entities\Mcp\McpTaskDependency;

/**
 * ADR 0070 — smoke test do schema Jira-style.
 *
 * Valida:
 *   - Migrations rodam sem erro
 *   - Entities CRUD básico funciona
 *   - allocateNextIdentifier gera identifier sequencial atômico
 *   - Scopes McpTask::triage / active / overdue funcionam
 *   - Inbox notify cria row
 *   - Cycle.progressPercent calcula
 */

uses(Tests\TestCase::class, DatabaseTransactions::class);

it('cria projeto + alloc identifier sequencial', function () {
    $proj = McpProject::create([
        'key' => 'TEST',
        'name' => 'Test Project',
        'status' => 'active',
        'next_task_number' => 1,
    ]);

    expect($proj->allocateNextIdentifier())->toBe('TEST-1');
    expect($proj->allocateNextIdentifier())->toBe('TEST-2');
    expect($proj->allocateNextIdentifier())->toBe('TEST-3');
    expect($proj->fresh()->next_task_number)->toBe(4);
});

it('cria epic e cycle ligados ao project', function () {
    $proj = McpProject::create(['key' => 'COPI', 'name' => 'Copiloto', 'status' => 'active']);

    $epic = McpEpic::create([
        'project_id' => $proj->id,
        'key' => 'COPI-EP-001',
        'title' => 'Memória do Copiloto',
        'status' => 'active',
    ]);
    $cycle = McpCycle::create([
        'project_id' => $proj->id,
        'key' => 'CYCLE-01',
        'start_date' => '2026-04-29',
        'end_date' => '2026-05-12',
        'goal' => 'Test goal',
        'status' => 'active',
    ]);

    expect($epic->project->id)->toBe($proj->id);
    expect($cycle->project->id)->toBe($proj->id);
    expect($cycle->isActive())->toBeTrue();
    expect($cycle->progressPercent())->toBeFloat();
});

it('cria task com identifier humano e scopes funcionam', function () {
    $proj = McpProject::create(['key' => 'COPI', 'name' => 'Copiloto', 'status' => 'active']);
    $cycle = McpCycle::create([
        'project_id' => $proj->id,
        'key' => 'CYCLE-01',
        'start_date' => '2026-04-29',
        'end_date' => '2026-05-12',
        'status' => 'active',
    ]);

    $id = $proj->allocateNextIdentifier();
    $task = McpTask::create([
        'task_id' => $id,
        'identifier' => $id,
        'project_id' => $proj->id,
        'cycle_id' => $cycle->id,
        'module' => 'COPI',
        'title' => 'Test task',
        'status' => 'doing',
        'type' => 'story',
        'owner' => 'wagner',
        'priority' => 'p0',
        'estimate_unit' => 'points',
        'source_path' => 'ad-hoc',
        'parsed_at' => now(),
    ]);

    expect($task->getDisplayIdAttribute())->toBe($id);
    expect(McpTask::active()->count())->toBe(1);
    expect(McpTask::open()->count())->toBe(1);
    expect(McpTask::triage()->count())->toBe(0); // tem owner+priority
});

it('triage scope pega task sem owner', function () {
    $proj = McpProject::create(['key' => 'COPI', 'name' => 'Copiloto', 'status' => 'active']);
    $id = $proj->allocateNextIdentifier();

    McpTask::create([
        'task_id' => $id,
        'identifier' => $id,
        'project_id' => $proj->id,
        'module' => 'COPI',
        'title' => 'Sem owner',
        'status' => 'todo',
        'owner' => null,
        'priority' => 'p2',
        'estimate_unit' => 'points',
        'source_path' => 'ad-hoc',
        'parsed_at' => now(),
    ]);

    expect(McpTask::triage()->count())->toBe(1);
});

it('cria cycle goal e relação inversa funciona', function () {
    $proj = McpProject::create(['key' => 'COPI', 'name' => 'Copiloto', 'status' => 'active']);
    $cycle = McpCycle::create([
        'project_id' => $proj->id,
        'key' => 'CYCLE-01',
        'start_date' => '2026-04-29',
        'end_date' => '2026-05-12',
        'status' => 'active',
    ]);

    McpCycleGoal::create([
        'cycle_id' => $cycle->id,
        'description' => 'Larissa responde 3 perguntas',
        'metric_name' => 'larissa',
        'target_value' => 'true',
        'achieved_value' => 'true',
        'status' => 'done',
        'sort_order' => 1,
    ]);

    expect($cycle->goals)->toHaveCount(1);
    expect($cycle->goals->first()->status)->toBe('done');
});

it('inbox notify cria row e unread scope filtra', function () {
    McpInboxNotification::notify(
        userId: 1,
        type: 'mention',
        taskId: 'COPI-1',
        actorId: 2,
        body: 'Mencionou você',
    );
    McpInboxNotification::notify(
        userId: 1,
        type: 'assigned',
        taskId: 'COPI-2',
    );

    expect(McpInboxNotification::forUser(1)->unread()->count())->toBe(2);

    $first = McpInboxNotification::first();
    $first->markRead();

    expect(McpInboxNotification::forUser(1)->unread()->count())->toBe(1);
});

it('task dependency cria relação tipada', function () {
    $proj = McpProject::create(['key' => 'COPI', 'name' => 'Copiloto', 'status' => 'active']);
    $a = McpTask::create([
        'task_id' => 'COPI-1',
        'identifier' => 'COPI-1',
        'project_id' => $proj->id,
        'module' => 'COPI',
        'title' => 'A',
        'status' => 'todo',
        'priority' => 'p2',
        'estimate_unit' => 'points',
        'source_path' => 'ad-hoc',
        'parsed_at' => now(),
    ]);
    $b = McpTask::create([
        'task_id' => 'COPI-2',
        'identifier' => 'COPI-2',
        'project_id' => $proj->id,
        'module' => 'COPI',
        'title' => 'B',
        'status' => 'todo',
        'priority' => 'p2',
        'estimate_unit' => 'points',
        'source_path' => 'ad-hoc',
        'parsed_at' => now(),
    ]);

    McpTaskDependency::create([
        'task_id' => $a->task_id,
        'depends_on_task_id' => $b->task_id,
        'type' => 'blocks',
        'created_by' => 'wagner',
    ]);

    expect($a->dependencies()->count())->toBe(1);
    expect($b->blocks()->count())->toBe(1);
});

it('task started_at + completed_at autopopulam via TaskCrudService update', function () {
    $proj = McpProject::create(['key' => 'COPI', 'name' => 'Copiloto', 'status' => 'active']);
    $task = McpTask::create([
        'task_id' => 'COPI-1',
        'identifier' => 'COPI-1',
        'project_id' => $proj->id,
        'module' => 'COPI',
        'title' => 'Test lifecycle',
        'status' => 'todo',
        'priority' => 'p1',
        'estimate_unit' => 'points',
        'source_path' => 'ad-hoc',
        'parsed_at' => now(),
    ]);

    $svc = new \Modules\Jana\Services\TaskRegistry\TaskCrudService();
    $svc->update('COPI-1', ['status' => 'doing'], 'wagner');
    expect($task->fresh()->started_at)->not->toBeNull();

    $svc->update('COPI-1', ['status' => 'done'], 'wagner');
    expect($task->fresh()->completed_at)->not->toBeNull();
});
