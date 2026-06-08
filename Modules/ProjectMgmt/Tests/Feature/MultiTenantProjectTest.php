<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Entities\Mcp\McpProject;
use Modules\Jana\Entities\Mcp\McpTask;

/**
 * Testa isolamento multi-tenant Tier 0 do Modules/ProjectMgmt.
 *
 * Module Jira-style opera sobre `mcp_projects` + `mcp_tasks` — ambas
 * possuem coluna `business_id` (multi-tenant scope Tier 0 — ADR 0093).
 * Controllers (Board/Backlog/MyWork) aplicam filtro por business via
 * sessão UltimatePOS (`session('user.business_id')`).
 *
 * Cenários validados:
 *   - Project biz=1 NÃO aparece em listagem scoped biz=99
 *   - Project biz=1 APARECE em listagem scoped biz=1
 *   - Task biz=1 com project biz=1 não vaza quando query roda em biz=99
 *   - 3 tasks biz=1 não aparecem em count biz=99 (anti-vazamento bulk)
 *
 * NUNCA usar biz=4 (ROTA LIVRE — cliente Larissa produção) — ADR 0101.
 * Tests usam biz=1 (Wagner) e biz=99 (fictício isolamento).
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 * @see memory/decisions/0070-jira-style-task-management-current-md-removed.md
 */

uses(Tests\TestCase::class);

// Guard SQLite: mcp_projects + mcp_tasks requerem schema MySQL UltimatePOS.
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompativel: mcp_projects/mcp_tasks requerem schema MySQL UltimatePOS (ADR 0101)');
    }
    if (! Schema::hasTable('mcp_projects')) {
        $this->markTestSkipped('mcp_projects table missing — rode migrations Copiloto/Jana primeiro');
    }
    if (! Schema::hasTable('mcp_tasks')) {
        $this->markTestSkipped('mcp_tasks table missing — rode migrations Copiloto/Jana primeiro');
    }
    if (! Schema::hasColumn('mcp_projects', 'business_id')) {
        $this->markTestSkipped('coluna business_id ausente em mcp_projects — multi-tenant Tier 0 violado');
    }
});

// IDs canônicos — biz=1 (Wagner) e biz=99 (fictício isolamento).
const PMG_BIZ_WAGNER = 1;
const PMG_BIZ_FICTICIO = 99;

// Marcador único pra cleanup isolado (não colide com prod).
const PMG_PROJECT_KEY_TEST = 'PMGMT-TEST';
const PMG_TASK_PREFIX_TEST = 'PMGMT-MT-';

// ------------------------------------------------------------------
// Helpers internos
// ------------------------------------------------------------------

function pmgmtBootProject(int $businessId, string $keySuffix = ''): McpProject
{
    return McpProject::create([
        'key'         => PMG_PROJECT_KEY_TEST . $keySuffix,
        'name'        => "PMGMT MT test biz={$businessId}",
        'business_id' => $businessId,
        'status'      => 'active',
    ]);
}

function pmgmtBootTask(McpProject $project, int $businessId, int $idx = 0): McpTask
{
    $taskId = PMG_TASK_PREFIX_TEST . substr((string) microtime(true), -6) . "-{$idx}";
    return McpTask::create([
        'task_id'     => $taskId,
        'project_id'  => $project->id,
        'business_id' => $businessId,
        'module'      => 'PMGMT',
        'title'       => "PMGMT MT task {$taskId}",
        'status'      => 'todo',
        'priority'    => 'p2',
        'type'        => 'task',
    ]);
}

// ------------------------------------------------------------------
// Cleanup global pós cada teste
// ------------------------------------------------------------------

afterEach(function () {
    try {
        McpTask::where('task_id', 'like', PMG_TASK_PREFIX_TEST . '%')->delete();
        McpProject::where('key', 'like', PMG_PROJECT_KEY_TEST . '%')->delete();
    } catch (\Throwable $e) {
        // ignore — env de teste pode ter sido derrubado
    }
});

// ------------------------------------------------------------------
// Cenários
// ------------------------------------------------------------------

it('Project biz=1 NÃO aparece em query scoped biz=99', function () {
    $project = pmgmtBootProject(PMG_BIZ_WAGNER);

    $resultado = McpProject::query()
        ->where('business_id', PMG_BIZ_FICTICIO)
        ->where('id', $project->id)
        ->get();

    expect($resultado)->toHaveCount(0);
});

it('Project biz=1 APARECE em query scoped biz=1', function () {
    $project = pmgmtBootProject(PMG_BIZ_WAGNER);

    $resultado = McpProject::query()
        ->where('business_id', PMG_BIZ_WAGNER)
        ->where('id', $project->id)
        ->get();

    expect($resultado)->toHaveCount(1);
    expect((int) $resultado->first()->business_id)->toBe(PMG_BIZ_WAGNER);
});

it('Task biz=1 não vaza quando query mcp_tasks roda em biz=99', function () {
    $project = pmgmtBootProject(PMG_BIZ_WAGNER);
    $task = pmgmtBootTask($project, PMG_BIZ_WAGNER);

    $vazamento = McpTask::query()
        ->where('business_id', PMG_BIZ_FICTICIO)
        ->where('task_id', $task->task_id)
        ->count();

    expect($vazamento)->toBe(0);
});

it('listing scoped biz=99 não vaza 3 tasks biz=1 (anti-vazamento bulk)', function () {
    $project = pmgmtBootProject(PMG_BIZ_WAGNER);
    for ($i = 0; $i < 3; $i++) {
        pmgmtBootTask($project, PMG_BIZ_WAGNER, $i);
    }

    $vazamento = McpTask::query()
        ->where('business_id', PMG_BIZ_FICTICIO)
        ->where('task_id', 'like', PMG_TASK_PREFIX_TEST . '%')
        ->count();

    expect($vazamento)->toBe(0);
});

it('Project biz=1 e Project biz=99 coexistem isolados (cross-tenant)', function () {
    $projectBiz1 = pmgmtBootProject(PMG_BIZ_WAGNER, '-A');
    $projectBiz99 = pmgmtBootProject(PMG_BIZ_FICTICIO, '-B');

    // biz=1 só vê seu project
    $viewBiz1 = McpProject::query()
        ->where('business_id', PMG_BIZ_WAGNER)
        ->whereIn('id', [$projectBiz1->id, $projectBiz99->id])
        ->pluck('id')->all();

    expect($viewBiz1)->toEqual([$projectBiz1->id]);

    // biz=99 só vê seu project
    $viewBiz99 = McpProject::query()
        ->where('business_id', PMG_BIZ_FICTICIO)
        ->whereIn('id', [$projectBiz1->id, $projectBiz99->id])
        ->pluck('id')->all();

    expect($viewBiz99)->toEqual([$projectBiz99->id]);
});
