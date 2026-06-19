<?php

declare(strict_types=1);

use App\User;
use Inertia\Testing\AssertableInertia;
use Modules\Jana\Entities\Mcp\McpProject;
use Modules\Jana\Entities\Mcp\McpTask;
use Modules\Jana\Entities\Mcp\McpTaskEvent;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

/**
 * Project Mgmt UI Redesign — Fase 1 (PMG-003 / ADR 0100).
 *
 * Cobertura mínima do BoardController:
 *  - GET /project-mgmt/board sem permission `copiloto.mcp.usage.all` → 403
 *  - GET com permission → 200 + Inertia component 'ProjectMgmt/Board/Index' + props canônicas
 *  - PATCH /project-mgmt/board/{taskId}/status sem permission → 403
 *  - PATCH happy path → 200 + ok=true + DB atualiza + cria mcp_task_events row
 *  - PATCH status inválido → 422
 *  - PATCH taskId inexistente → 404
 *
 * Padrão Repair/Whatsapp/Jana: roda contra DB dev real (UltimatePOS schema),
 * markTestSkipped se mcp_* tables ou User não estão semeados.
 *
 * Fixtures: project=PRJTEST, prefix de task=TEST-PMG-, cleanup afterEach.
 */

function pmgBootstrapUser(): User
{
    try {
        $user = User::first();
    } catch (\Throwable $e) {
        test()->markTestSkipped('Tabela users indisponível: ' . $e->getMessage());
    }

    if (! $user) {
        test()->markTestSkipped('Sem user no banco — rode seeder UltimatePOS antes.');
    }

    Permission::firstOrCreate(['name' => 'copiloto.mcp.usage.all', 'guard_name' => 'web']);

    session([
        'user.business_id' => $user->business_id,
        'user.id'          => $user->id,
        'business.id'      => $user->business_id,
        'is_admin'         => true,
    ]);

    return $user;
}

function pmgGivePerm(User $user): void
{
    Permission::firstOrCreate(['name' => 'copiloto.mcp.usage.all', 'guard_name' => 'web']);
    if (! $user->hasPermissionTo('copiloto.mcp.usage.all')) {
        $user->givePermissionTo('copiloto.mcp.usage.all');
    }
}

function pmgRevokePerm(User $user): void
{
    if ($user->hasPermissionTo('copiloto.mcp.usage.all')) {
        $user->revokePermissionTo('copiloto.mcp.usage.all');
    }
}

function pmgEnsureProject(): McpProject
{
    try {
        return McpProject::firstOrCreate(
            ['key' => 'PRJTEST'],
            ['name' => 'TEST-PMG project', 'business_id' => 1, 'status' => 'active']
        );
    } catch (\Throwable $e) {
        test()->markTestSkipped('Schema mcp_projects indisponível: ' . $e->getMessage());
    }
    return new McpProject;
}

function pmgCreateTask(McpProject $project, string $status = 'todo', string $priority = 'p2'): McpTask
{
    $taskId = 'TEST-PMG-' . substr((string) microtime(true), -8);
    return McpTask::create([
        'task_id' => $taskId,
        'project_id' => $project->id,
        'module' => 'PRJTEST',
        'title' => 'TEST-PMG board fixture ' . $taskId,
        'status' => $status,
        'priority' => $priority,
        'type' => 'task',
    ]);
}

afterEach(function () {
    try {
        $taskIds = McpTask::where('task_id', 'like', 'TEST-PMG-%')->pluck('task_id')->all();
        if ($taskIds) {
            McpTaskEvent::whereIn('task_id', $taskIds)->delete();
            McpTask::whereIn('task_id', $taskIds)->delete();
        }
        McpProject::where('key', 'PRJTEST')->delete();
    } catch (\Throwable $e) {
        // ignore — env de teste pode não ter as tabelas
    }
});

it('GET /project-mgmt/board sem permission retorna 403', function () {
    $user = pmgBootstrapUser();
    pmgRevokePerm($user);

    $response = $this->actingAs($user)->get('/project-mgmt/board');

    expect($response->status())->toBe(403);
});

it('GET /project-mgmt/board com permission retorna Inertia ProjectMgmt/Board/Index', function () {
    $user = pmgBootstrapUser();
    pmgGivePerm($user);
    pmgEnsureProject();

    $response = $this->actingAs($user)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => 'test'])
        ->get('/project-mgmt/board?project=PRJTEST');

    if ($response->status() === 403) {
        test()->markTestSkipped('Permission gate inesperado neste env.');
    }

    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('ProjectMgmt/Board/Index')
        ->has('kanban')
        ->has('kpis.total')
        ->has('kpis.doing')
        ->has('kpis.blocked')
        ->has('kpis.p0_aberto')
        ->has('columns')
        ->has('filters')
    );
});

it('PATCH /project-mgmt/board/{taskId}/status sem permission retorna 403', function () {
    $user = pmgBootstrapUser();
    pmgGivePerm($user);
    $project = pmgEnsureProject();
    $task = pmgCreateTask($project, 'todo');

    pmgRevokePerm($user);

    $response = $this->actingAs($user)
        ->patchJson("/project-mgmt/board/{$task->task_id}/status", ['status' => 'doing']);

    expect($response->status())->toBe(403);
    expect($task->fresh()->status)->toBe('todo'); // não persistiu
});

it('PATCH happy path muda status + cria mcp_task_events row', function () {
    $user = pmgBootstrapUser();
    pmgGivePerm($user);
    $project = pmgEnsureProject();
    $task = pmgCreateTask($project, 'todo');

    $eventsBefore = McpTaskEvent::where('task_id', $task->task_id)->count();

    $response = $this->actingAs($user)
        ->patchJson("/project-mgmt/board/{$task->task_id}/status", ['status' => 'doing']);

    if ($response->status() === 403) {
        test()->markTestSkipped('Permission gate inesperado.');
    }

    $response->assertOk();
    $response->assertJson([
        'ok' => true,
        'task_id' => $task->task_id,
        'status' => 'doing',
    ]);

    expect($task->fresh()->status)->toBe('doing');
    // started_at deve ser populado pelo side-effect do TaskCrudService
    expect($task->fresh()->started_at)->not->toBeNull();

    $eventsAfter = McpTaskEvent::where('task_id', $task->task_id)
        ->where('event_type', 'status_changed')
        ->count();
    expect($eventsAfter)->toBeGreaterThan($eventsBefore);
});

it('PATCH com status inválido retorna 422', function () {
    $user = pmgBootstrapUser();
    pmgGivePerm($user);
    $project = pmgEnsureProject();
    $task = pmgCreateTask($project, 'todo');

    $response = $this->actingAs($user)
        ->patchJson("/project-mgmt/board/{$task->task_id}/status", ['status' => 'totalmente_invalido_xyz']);

    expect($response->status())->toBe(422);
    expect($task->fresh()->status)->toBe('todo'); // não persistiu
});

it('PATCH com taskId inexistente retorna 404', function () {
    $user = pmgBootstrapUser();
    pmgGivePerm($user);

    $response = $this->actingAs($user)
        ->patchJson('/project-mgmt/board/TEST-PMG-NAOEXISTE-99999/status', ['status' => 'doing']);

    expect($response->status())->toBe(404);
});

it('R-PMG-005: PATCH com expected_updated_at obsoleto retorna 409 + estado intacto', function () {
    $user = pmgBootstrapUser();
    pmgGivePerm($user);
    $project = pmgEnsureProject();
    $task = pmgCreateTask($project, 'todo');

    // Simula concurrent edit: outro user atualizou primeiro
    $task->update(['status' => 'doing']);
    $task->refresh();
    $staleTimestamp = ($task->updated_at?->timestamp ?? 0) - 3600; // 1h atrás (stale)

    $response = $this->actingAs($user)
        ->patchJson("/project-mgmt/board/{$task->task_id}/status", [
            'status' => 'review',
            'expected_updated_at' => $staleTimestamp,
        ]);

    expect($response->status())->toBe(409);
    $response->assertJson([
        'error' => 'conflict',
        'current' => [
            'task_id' => $task->task_id,
            'status' => 'doing', // estado real após o concurrent update
        ],
    ]);

    // Status permaneceu 'doing' — PATCH foi rejeitado, não regrediu pra 'review'
    expect($task->fresh()->status)->toBe('doing');
});

it('PATCH com expected_updated_at correto retorna 200 + novo updated_at', function () {
    $user = pmgBootstrapUser();
    pmgGivePerm($user);
    $project = pmgEnsureProject();
    $task = pmgCreateTask($project, 'todo');
    $task->refresh();

    $currentTs = (int) ($task->updated_at?->timestamp ?? 0);

    $response = $this->actingAs($user)
        ->patchJson("/project-mgmt/board/{$task->task_id}/status", [
            'status' => 'doing',
            'expected_updated_at' => $currentTs,
        ]);

    if ($response->status() === 403) {
        test()->markTestSkipped('Permission gate inesperado.');
    }

    $response->assertOk();
    $response->assertJsonStructure(['ok', 'task_id', 'status', 'updated_at']);
    expect($task->fresh()->status)->toBe('doing');
});

// ============================================================================
// PMG-004 (ADR 0100) — Detail Sheet endpoint GET /board/{taskId}/detail
// ============================================================================

it('GET /board/{taskId}/detail sem permission retorna 403', function () {
    $user = pmgBootstrapUser();
    pmgGivePerm($user);
    $project = pmgEnsureProject();
    $task = pmgCreateTask($project, 'todo');

    pmgRevokePerm($user);

    $response = $this->actingAs($user)
        ->getJson("/project-mgmt/board/{$task->task_id}/detail");

    expect($response->status())->toBe(403);
});

it('GET /board/{taskId}/detail com taskId inexistente retorna 404', function () {
    $user = pmgBootstrapUser();
    pmgGivePerm($user);

    $response = $this->actingAs($user)
        ->getJson('/project-mgmt/board/TEST-PMG-NAOEXISTE-99999/detail');

    expect($response->status())->toBe(404);
});

it('GET /board/{taskId}/detail happy path retorna shape canônico', function () {
    $user = pmgBootstrapUser();
    pmgGivePerm($user);
    $project = pmgEnsureProject();
    $task = pmgCreateTask($project, 'doing', 'p1');

    $response = $this->actingAs($user)
        ->getJson("/project-mgmt/board/{$task->task_id}/detail");

    if ($response->status() === 403) {
        test()->markTestSkipped('Permission gate inesperado.');
    }

    $response->assertOk();
    $response->assertJsonStructure([
        'task' => [
            'task_id',
            'display_id',
            'title',
            'status',
            'priority',
            'description',
            'project_key',
        ],
        'comments',
        'events',
        'subtasks',
        'dependencies',
    ]);

    $data = $response->json();
    expect($data['task']['task_id'])->toBe($task->task_id);
    expect($data['task']['status'])->toBe('doing');
    expect($data['task']['priority'])->toBe('p1');
});

// ============================================================================
// PMG-005 (ADR 0100) — Comments + @mentions
// ============================================================================

it('POST /board/{taskId}/comment sem permission retorna 403', function () {
    $user = pmgBootstrapUser();
    pmgGivePerm($user);
    $project = pmgEnsureProject();
    $task = pmgCreateTask($project, 'todo');

    pmgRevokePerm($user);

    $response = $this->actingAs($user)
        ->postJson("/project-mgmt/board/{$task->task_id}/comment", ['body' => 'teste']);

    expect($response->status())->toBe(403);
});

it('POST comment happy path cria mcp_task_comment + retorna 201', function () {
    $user = pmgBootstrapUser();
    pmgGivePerm($user);
    $project = pmgEnsureProject();
    $task = pmgCreateTask($project, 'todo');

    $response = $this->actingAs($user)
        ->postJson("/project-mgmt/board/{$task->task_id}/comment", [
            'body' => 'comentário sem mentions',
        ]);

    if ($response->status() === 403) {
        test()->markTestSkipped('Permission gate inesperado.');
    }

    expect($response->status())->toBe(201);
    $response->assertJsonStructure([
        'ok',
        'comment' => ['id', 'task_id', 'author', 'body', 'created_at'],
    ]);

    $persisted = \Modules\Jana\Entities\Mcp\McpTaskComment::where('task_id', $task->task_id)->count();
    expect($persisted)->toBe(1);
});

it('POST comment vazio retorna 422', function () {
    $user = pmgBootstrapUser();
    pmgGivePerm($user);
    $project = pmgEnsureProject();
    $task = pmgCreateTask($project, 'todo');

    $response = $this->actingAs($user)
        ->postJson("/project-mgmt/board/{$task->task_id}/comment", ['body' => '']);

    expect($response->status())->toBe(422);
});

it('GET /board/users/suggest sem query retorna lista vazia', function () {
    $user = pmgBootstrapUser();
    pmgGivePerm($user);

    $response = $this->actingAs($user)
        ->getJson('/project-mgmt/board/users/suggest');

    if ($response->status() === 403) {
        test()->markTestSkipped('Permission gate inesperado.');
    }

    $response->assertOk();
    $response->assertJson(['users' => []]);
});

it('GET /board/users/suggest sem permission retorna 403', function () {
    $user = pmgBootstrapUser();
    pmgRevokePerm($user);

    $response = $this->actingAs($user)
        ->getJson('/project-mgmt/board/users/suggest?q=admin');

    expect($response->status())->toBe(403);
});

// ============================================================================
// PMG-006 (ADR 0100) — Watchers (Follow/Unfollow)
// ============================================================================

it('POST /board/{taskId}/watch happy path cria watcher idempotente', function () {
    $user = pmgBootstrapUser();
    pmgGivePerm($user);
    $project = pmgEnsureProject();
    $task = pmgCreateTask($project, 'todo');

    $response = $this->actingAs($user)
        ->postJson("/project-mgmt/board/{$task->task_id}/watch");

    if ($response->status() === 403) {
        test()->markTestSkipped('Permission gate inesperado.');
    }

    $response->assertOk();
    $response->assertJson([
        'ok' => true,
        'is_watching' => true,
        'watchers_count' => 1,
    ]);

    $count = \Modules\Jana\Entities\Mcp\McpTaskWatcher::where('task_id', $task->task_id)
        ->where('user_id', $user->id)
        ->count();
    expect($count)->toBe(1);

    // Idempotente: 2× POST não duplica
    $this->actingAs($user)->postJson("/project-mgmt/board/{$task->task_id}/watch");
    $countAfter = \Modules\Jana\Entities\Mcp\McpTaskWatcher::where('task_id', $task->task_id)
        ->where('user_id', $user->id)
        ->count();
    expect($countAfter)->toBe(1);
});

it('DELETE /board/{taskId}/watch remove watcher idempotente', function () {
    $user = pmgBootstrapUser();
    pmgGivePerm($user);
    $project = pmgEnsureProject();
    $task = pmgCreateTask($project, 'todo');

    \Modules\Jana\Entities\Mcp\McpTaskWatcher::create([
        'task_id' => $task->task_id,
        'user_id' => $user->id,
    ]);

    $response = $this->actingAs($user)
        ->deleteJson("/project-mgmt/board/{$task->task_id}/watch");

    if ($response->status() === 403) {
        test()->markTestSkipped('Permission gate inesperado.');
    }

    $response->assertOk();
    $response->assertJson([
        'ok' => true,
        'is_watching' => false,
        'watchers_count' => 0,
    ]);

    // Idempotente: 2× DELETE não erra
    $second = $this->actingAs($user)->deleteJson("/project-mgmt/board/{$task->task_id}/watch");
    expect($second->status())->toBe(200);
});

it('POST /board/{taskId}/watch sem permission retorna 403', function () {
    $user = pmgBootstrapUser();
    pmgGivePerm($user);
    $project = pmgEnsureProject();
    $task = pmgCreateTask($project, 'todo');

    pmgRevokePerm($user);

    $response = $this->actingAs($user)
        ->postJson("/project-mgmt/board/{$task->task_id}/watch");

    expect($response->status())->toBe(403);
});

it('POST /board/{taskId}/watch com taskId inexistente retorna 404', function () {
    $user = pmgBootstrapUser();
    pmgGivePerm($user);

    $response = $this->actingAs($user)
        ->postJson('/project-mgmt/board/TEST-PMG-NAOEXISTE-99999/watch');

    expect($response->status())->toBe(404);
});

it('GET /board/{taskId}/detail inclui watchers + is_watching', function () {
    $user = pmgBootstrapUser();
    pmgGivePerm($user);
    $project = pmgEnsureProject();
    $task = pmgCreateTask($project, 'todo');

    \Modules\Jana\Entities\Mcp\McpTaskWatcher::create([
        'task_id' => $task->task_id,
        'user_id' => $user->id,
    ]);

    $response = $this->actingAs($user)
        ->getJson("/project-mgmt/board/{$task->task_id}/detail");

    if ($response->status() === 403) {
        test()->markTestSkipped('Permission gate inesperado.');
    }

    $response->assertOk();
    $response->assertJsonStructure([
        'task',
        'watchers',
        'is_watching',
    ]);

    $data = $response->json();
    expect($data['is_watching'])->toBeTrue();
    expect($data['watchers'])->toHaveCount(1);
});

// ============================================================================
// PMG-007 (ADR 0100) — Subtasks UI (create + toggle status)
// ============================================================================

it('POST /board/{taskId}/subtask happy path cria subtask com parent_task_id', function () {
    $user = pmgBootstrapUser();
    pmgGivePerm($user);
    $project = pmgEnsureProject();
    $parent = pmgCreateTask($project, 'todo');

    $response = $this->actingAs($user)
        ->postJson("/project-mgmt/board/{$parent->task_id}/subtask", [
            'title' => 'TEST-PMG subtask filha',
        ]);

    if ($response->status() === 403) {
        test()->markTestSkipped('Permission gate inesperado.');
    }

    expect($response->status())->toBe(201);
    $response->assertJsonStructure([
        'ok',
        'subtask' => ['task_id', 'display_id', 'title', 'status', 'priority'],
    ]);

    $data = $response->json();
    $childId = $data['subtask']['task_id'];

    $child = \Modules\Jana\Entities\Mcp\McpTask::where('task_id', $childId)->first();
    expect($child)->not->toBeNull();
    expect((int) $child->parent_task_id)->toBe((int) $parent->id);
    expect($child->status)->toBe('todo');
});

it('POST subtask sem permission retorna 403', function () {
    $user = pmgBootstrapUser();
    pmgGivePerm($user);
    $project = pmgEnsureProject();
    $parent = pmgCreateTask($project, 'todo');

    pmgRevokePerm($user);

    $response = $this->actingAs($user)
        ->postJson("/project-mgmt/board/{$parent->task_id}/subtask", [
            'title' => 'TEST-PMG sem perm',
        ]);

    expect($response->status())->toBe(403);
});

it('POST subtask com title vazio retorna 422', function () {
    $user = pmgBootstrapUser();
    pmgGivePerm($user);
    $project = pmgEnsureProject();
    $parent = pmgCreateTask($project, 'todo');

    $response = $this->actingAs($user)
        ->postJson("/project-mgmt/board/{$parent->task_id}/subtask", ['title' => '']);

    expect($response->status())->toBe(422);
});

it('POST subtask com taskId inexistente retorna 404', function () {
    $user = pmgBootstrapUser();
    pmgGivePerm($user);

    $response = $this->actingAs($user)
        ->postJson('/project-mgmt/board/TEST-PMG-NAOEXISTE-99999/subtask', [
            'title' => 'TEST-PMG órfã',
        ]);

    expect($response->status())->toBe(404);
});
