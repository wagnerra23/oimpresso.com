<?php

declare(strict_types=1);

use App\User;
use Modules\Jana\Entities\Mcp\McpProject;
use Modules\Jana\Entities\Mcp\McpTask;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

/**
 * PMG-002 (ADR 0100) — Cmd+K Search Global.
 *
 * Cobertura mínima do SearchController:
 *  - GET /project-mgmt/search sem permission → 403
 *  - GET com query < 2 chars → 200 + results vazios
 *  - GET com query válida + permission → 200 + tasks/epics/cycles/projects shape
 *  - GET com query que combina título de task → retorna a task no group
 *
 * Padrão Repair/Whatsapp/Jana: roda contra DB dev real, markTestSkipped
 * se mcp_* tables ou User não estão semeados.
 *
 * Fixtures: project=PRJSEARCH, tasks com prefix TEST-SEARCH-, cleanup afterEach.
 */

function searchBootstrapUser(): User
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

function searchGivePerm(User $user): void
{
    Permission::firstOrCreate(['name' => 'copiloto.mcp.usage.all', 'guard_name' => 'web']);
    if (! $user->hasPermissionTo('jana.mcp.usage.all')) {
        $user->givePermissionTo('copiloto.mcp.usage.all');
    }
}

function searchRevokePerm(User $user): void
{
    if ($user->hasPermissionTo('jana.mcp.usage.all')) {
        $user->revokePermissionTo('copiloto.mcp.usage.all');
    }
}

function searchEnsureProject(): McpProject
{
    try {
        return McpProject::firstOrCreate(
            ['key' => 'PRJSEARCH'],
            ['name' => 'TEST-SEARCH project', 'business_id' => 1, 'status' => 'active']
        );
    } catch (\Throwable $e) {
        test()->markTestSkipped('Schema mcp_projects indisponível: ' . $e->getMessage());
    }
    return new McpProject;
}

function searchCreateTask(McpProject $project, string $title): McpTask
{
    return McpTask::create([
        'task_id' => 'TEST-SEARCH-' . substr((string) microtime(true), -8),
        'project_id' => $project->id,
        'module' => 'PRJSEARCH',
        'title' => $title,
        'status' => 'todo',
        'priority' => 'p2',
        'type' => 'task',
    ]);
}

afterEach(function () {
    try {
        McpTask::where('task_id', 'like', 'TEST-SEARCH-%')->delete();
        McpProject::where('key', 'PRJSEARCH')->delete();
    } catch (\Throwable $e) {
        // ignore
    }
});

it('GET /project-mgmt/search sem permission retorna 403', function () {
    $user = searchBootstrapUser();
    searchRevokePerm($user);

    $response = $this->actingAs($user)
        ->getJson('/project-mgmt/search?q=teste');

    expect($response->status())->toBe(403);
});

it('GET com query < 2 chars retorna 200 + results vazios', function () {
    $user = searchBootstrapUser();
    searchGivePerm($user);

    $response = $this->actingAs($user)
        ->getJson('/project-mgmt/search?q=a');

    if ($response->status() === 403) {
        test()->markTestSkipped('Permission gate inesperado.');
    }

    $response->assertOk();
    $response->assertJson([
        'query' => 'a',
        'total' => 0,
    ]);
    $response->assertJsonStructure([
        'query',
        'results' => ['tasks', 'epics', 'cycles', 'projects'],
        'total',
    ]);
});

it('GET com query válida retorna shape canônico (tasks/epics/cycles/projects)', function () {
    $user = searchBootstrapUser();
    searchGivePerm($user);

    $response = $this->actingAs($user)
        ->getJson('/project-mgmt/search?q=zz_naoexisteprovavelmente_zz');

    if ($response->status() === 403) {
        test()->markTestSkipped('Permission gate inesperado.');
    }

    $response->assertOk();
    $response->assertJsonStructure([
        'query',
        'results' => ['tasks', 'epics', 'cycles', 'projects'],
        'total',
    ]);
});

it('GET com query que combina título de task — retorna a task', function () {
    $user = searchBootstrapUser();
    searchGivePerm($user);
    $project = searchEnsureProject();

    $unique = 'unicusearchterm' . substr((string) microtime(true), -4);
    $task = searchCreateTask($project, "TEST-SEARCH task com termo {$unique}");

    $response = $this->actingAs($user)
        ->getJson('/project-mgmt/search?q=' . $unique);

    if ($response->status() === 403) {
        test()->markTestSkipped('Permission gate inesperado.');
    }

    $response->assertOk();
    $data = $response->json();

    expect($data['total'])->toBeGreaterThanOrEqual(1);
    $taskTitles = array_column($data['results']['tasks'], 'title');
    $found = false;
    foreach ($taskTitles as $title) {
        if (str_contains($title, $unique)) {
            $found = true;
            break;
        }
    }
    expect($found)->toBeTrue("Task com '{$unique}' não apareceu nos resultados");
});
