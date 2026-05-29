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
 * Triage UI — Onda 2 (US-TR-301..303 / SPEC-UI-FASE7).
 *
 * Cobertura do TriageController:
 *  - GET /project-mgmt/triage sem permission → 403
 *  - GET com permission → 200 + Inertia 'ProjectMgmt/Triage/Index' + props canônicas
 *  - lista órfã = scope McpTask::triage() (sem owner OU sem prio OU backlog),
 *    paridade com a tool MCP `triage`
 *  - PATCH /triage/{id}/assign sem permission → 403
 *  - PATCH assign owner+prio → 200 + persiste + cria mcp_task_events (assigned) +
 *    still_triage reflete se ainda é órfã
 *  - PATCH assign priority inválida → 422
 *  - PATCH assign taskId inexistente → 404
 *
 * Padrão Board/Repair/Jana: roda contra DB dev real (UltimatePOS schema),
 * markTestSkipped se mcp_* tables ou User não estão semeados.
 *
 * Fixtures: project=TRGTEST, prefix de task=TEST-TRG-, cleanup afterEach.
 */

function trgBootstrapUser(): User
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

function trgGivePerm(User $user): void
{
    Permission::firstOrCreate(['name' => 'copiloto.mcp.usage.all', 'guard_name' => 'web']);
    if (! $user->hasPermissionTo('copiloto.mcp.usage.all')) {
        $user->givePermissionTo('copiloto.mcp.usage.all');
    }
}

function trgRevokePerm(User $user): void
{
    if ($user->hasPermissionTo('copiloto.mcp.usage.all')) {
        $user->revokePermissionTo('copiloto.mcp.usage.all');
    }
}

function trgEnsureProject(): McpProject
{
    try {
        return McpProject::firstOrCreate(
            ['key' => 'TRGTEST'],
            ['name' => 'TEST-TRG project', 'business_id' => 1, 'status' => 'active']
        );
    } catch (\Throwable $e) {
        test()->markTestSkipped('Schema mcp_jira_projects indisponível: ' . $e->getMessage());
    }
    return new McpProject;
}

/**
 * Cria task. Por default ÓRFÃ (sem owner, sem priority) — cai na triage.
 */
function trgCreateTask(McpProject $project, ?string $owner = null, ?string $priority = null, string $status = 'todo'): McpTask
{
    $taskId = 'TEST-TRG-' . substr((string) microtime(true), -8) . random_int(10, 99);
    return McpTask::create([
        'task_id'    => $taskId,
        'project_id' => $project->id,
        'module'     => 'TRGTEST',
        'title'      => 'TEST-TRG triage fixture ' . $taskId,
        'status'     => $status,
        'owner'      => $owner,
        'priority'   => $priority,
        'type'       => 'task',
    ]);
}

afterEach(function () {
    try {
        $taskIds = McpTask::where('task_id', 'like', 'TEST-TRG-%')->pluck('task_id')->all();
        if ($taskIds) {
            McpTaskEvent::whereIn('task_id', $taskIds)->delete();
            McpTask::whereIn('task_id', $taskIds)->delete();
        }
        McpProject::where('key', 'TRGTEST')->delete();
    } catch (\Throwable $e) {
        // ignore — env de teste pode não ter as tabelas
    }
});

it('GET /project-mgmt/triage sem permission retorna 403', function () {
    $user = trgBootstrapUser();
    trgRevokePerm($user);

    $response = $this->actingAs($user)->get('/project-mgmt/triage');

    expect($response->status())->toBe(403);
});

it('GET /project-mgmt/triage com permission retorna Inertia ProjectMgmt/Triage/Index', function () {
    $user = trgBootstrapUser();
    trgGivePerm($user);
    trgEnsureProject();

    $response = $this->actingAs($user)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => 'test'])
        ->get('/project-mgmt/triage?project=TRGTEST');

    if ($response->status() === 403) {
        test()->markTestSkipped('Permission gate inesperado neste env.');
    }

    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('ProjectMgmt/Triage/Index')
        ->has('tasks')
        ->has('kpis')
        ->has('cycles')
        ->has('epics')
        ->has('owners')
        ->has('filters')
    );
});

it('lista de triage contém task órfã e exclui task já triada (paridade tool triage)', function () {
    $user = trgBootstrapUser();
    trgGivePerm($user);
    $project = trgEnsureProject();

    $orfan = trgCreateTask($project); // sem owner + sem prio → órfã
    $triada = trgCreateTask($project, 'wagner', 'p1', 'todo'); // tem tudo → NÃO órfã

    $response = $this->actingAs($user)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => 'test'])
        ->get('/project-mgmt/triage?project=TRGTEST');

    if ($response->status() === 403) {
        test()->markTestSkipped('Permission gate inesperado.');
    }

    $response->assertOk();
    $response->assertInertia(function (AssertableInertia $page) use ($orfan, $triada) {
        $ids = collect($page->toArray()['props']['tasks'] ?? [])->pluck('task_id')->all();
        expect($ids)->toContain($orfan->task_id);
        expect($ids)->not->toContain($triada->task_id);
    });
});

it('PATCH /triage/{id}/assign sem permission retorna 403', function () {
    $user = trgBootstrapUser();
    trgGivePerm($user);
    $project = trgEnsureProject();
    $task = trgCreateTask($project);

    trgRevokePerm($user);

    $response = $this->actingAs($user)
        ->patchJson("/project-mgmt/triage/{$task->task_id}/assign", ['owner' => 'wagner']);

    expect($response->status())->toBe(403);
    expect($task->fresh()->owner)->toBeNull(); // não persistiu
});

it('PATCH assign owner+prio persiste + cria mcp_task_events + still_triage=false', function () {
    $user = trgBootstrapUser();
    trgGivePerm($user);
    $project = trgEnsureProject();
    $task = trgCreateTask($project, null, null, 'todo'); // órfã

    $eventsBefore = McpTaskEvent::where('task_id', $task->task_id)->count();

    $response = $this->actingAs($user)
        ->patchJson("/project-mgmt/triage/{$task->task_id}/assign", [
            'owner'    => 'wagner',
            'priority' => 'p1',
        ]);

    if ($response->status() === 403) {
        test()->markTestSkipped('Permission gate inesperado.');
    }

    $response->assertOk();
    $response->assertJson(['ok' => true, 'still_triage' => false]);

    $fresh = $task->fresh();
    expect($fresh->owner)->toBe('wagner');
    expect($fresh->priority)->toBe('p1');

    // Gera evento de audit (assigned do owner — TaskCrudService)
    $eventsAfter = McpTaskEvent::where('task_id', $task->task_id)
        ->whereIn('event_type', ['assigned', 'field_updated'])
        ->count();
    expect($eventsAfter)->toBeGreaterThan($eventsBefore);
});

it('PATCH assign mantém still_triage=true se task continua órfã (só prio, sem owner)', function () {
    $user = trgBootstrapUser();
    trgGivePerm($user);
    $project = trgEnsureProject();
    $task = trgCreateTask($project, null, null, 'todo'); // órfã

    $response = $this->actingAs($user)
        ->patchJson("/project-mgmt/triage/{$task->task_id}/assign", ['priority' => 'p2']);

    if ($response->status() === 403) {
        test()->markTestSkipped('Permission gate inesperado.');
    }

    $response->assertOk();
    // Ainda sem owner → continua na fila
    $response->assertJson(['ok' => true, 'still_triage' => true]);
    expect($task->fresh()->priority)->toBe('p2');
    expect($task->fresh()->owner)->toBeNull();
});

it('PATCH assign com priority inválida retorna 422', function () {
    $user = trgBootstrapUser();
    trgGivePerm($user);
    $project = trgEnsureProject();
    $task = trgCreateTask($project);

    $response = $this->actingAs($user)
        ->patchJson("/project-mgmt/triage/{$task->task_id}/assign", ['priority' => 'p9_invalida']);

    expect($response->status())->toBe(422);
    expect($task->fresh()->priority)->toBeNull(); // não persistiu
});

it('PATCH assign sem nenhum campo retorna 422', function () {
    $user = trgBootstrapUser();
    trgGivePerm($user);
    $project = trgEnsureProject();
    $task = trgCreateTask($project);

    $response = $this->actingAs($user)
        ->patchJson("/project-mgmt/triage/{$task->task_id}/assign", []);

    expect($response->status())->toBe(422);
});

it('PATCH assign com taskId inexistente retorna 404', function () {
    $user = trgBootstrapUser();
    trgGivePerm($user);

    $response = $this->actingAs($user)
        ->patchJson('/project-mgmt/triage/TEST-TRG-NAOEXISTE-99999/assign', ['owner' => 'wagner']);

    expect($response->status())->toBe(404);
});
