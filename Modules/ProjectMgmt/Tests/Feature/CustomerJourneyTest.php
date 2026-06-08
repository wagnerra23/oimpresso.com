<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Entities\Mcp\McpProject;
use Modules\Jana\Entities\Mcp\McpTask;
use Modules\ProjectMgmt\Services\ProjectService;

uses(Tests\TestCase::class);

/**
 * CustomerJourneyTest — Wave 16 Governance D5 cliente (2026-05-16).
 *
 * Smoke E2E da jornada típica do operador que usa Modules/ProjectMgmt:
 *   1. Criar project (via ProjectService.create — D4 refatorado)
 *   2. Adicionar tasks ao project (via McpTask::create direto — Jana model)
 *   3. Mover task entre status (todo → doing → done)
 *   4. Concluir project (archive)
 *
 * Tier 0 multi-tenant ([ADR 0093]): cenário roda em biz=1 (Wagner) +
 * cross-check biz=99 garante isolamento. NUNCA biz=4 (ROTA LIVRE — ADR 0101).
 *
 * Diferente do MultiTenantProjectTest (só isolamento), este test valida a
 * EXPERIÊNCIA do usuário fim-a-fim — replica como Wagner/Felipe operam
 * diariamente no Board/Backlog Kanban.
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 * @see Modules\ProjectMgmt\Services\ProjectService
 */

const PMG_CJ_BIZ_WAGNER = 1;
const PMG_CJ_BIZ_FICTICIO = 99;
const PMG_CJ_PROJECT_KEY = 'CJOURN-TEST';
const PMG_CJ_TASK_PREFIX = 'CJOURN-MT-';

// ------------------------------------------------------------------
// Guards de schema (skip se env minimal)
// ------------------------------------------------------------------

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompativel: mcp_projects/mcp_tasks exigem MySQL UltimatePOS (ADR 0101)');
    }
    if (! Schema::hasTable('mcp_projects') || ! Schema::hasTable('mcp_tasks')) {
        $this->markTestSkipped('Tables mcp_projects/mcp_tasks missing — rode migrations Jana/Copiloto');
    }
});

afterEach(function () {
    try {
        McpTask::where('task_id', 'like', PMG_CJ_TASK_PREFIX . '%')->delete();
        McpProject::where('key', 'like', PMG_CJ_PROJECT_KEY . '%')->delete();
        DB::table('mcp_projects')->where('codigo', 'like', 'CJOURN-%')->delete();
    } catch (\Throwable $e) {
        // ignore
    }
});

// ------------------------------------------------------------------
// Cenário 1: criar project via Service (D4 refatorado)
// ------------------------------------------------------------------

it('cliente cria project via ProjectService respeitando business_id', function () {
    $service = new ProjectService(PMG_CJ_BIZ_WAGNER);

    $projectId = $service->create([
        'nome'           => 'Project teste journey cliente',
        'objetivo_macro' => 'Validar que cliente consegue criar/operar projetos',
        'codigo'         => 'CJOURN-001',
        'owner'          => 'wagner',
    ]);

    expect($projectId)->toBeInt()->toBeGreaterThan(0);

    // Project deve aparecer scoped biz=1
    $row = DB::table('mcp_projects')->where('id', $projectId)->first();
    expect((int) $row->business_id)->toBe(PMG_CJ_BIZ_WAGNER);
    expect($row->codigo)->toBe('CJOURN-001');
    expect($row->status)->toBe('draft');
});

it('listagem do cliente NÃO vaza project de outro tenant (biz=99)', function () {
    $serviceBiz1 = new ProjectService(PMG_CJ_BIZ_WAGNER);
    $serviceBiz99 = new ProjectService(PMG_CJ_BIZ_FICTICIO);

    $idBiz1 = $serviceBiz1->create([
        'nome'           => 'Project biz=1',
        'objetivo_macro' => 'cenário biz=1',
        'codigo'         => 'CJOURN-002',
    ]);
    $idBiz99 = $serviceBiz99->create([
        'nome'           => 'Project biz=99',
        'objetivo_macro' => 'cenário biz=99',
        'codigo'         => 'CJOURN-003',
    ]);

    // biz=1 só enxerga o seu na listagem
    $listBiz1 = $serviceBiz1->list();
    $idsBiz1 = $listBiz1->pluck('id')->all();
    expect($idsBiz1)->toContain($idBiz1)->not->toContain($idBiz99);

    // biz=99 só enxerga o seu na listagem
    $listBiz99 = $serviceBiz99->list();
    $idsBiz99 = $listBiz99->pluck('id')->all();
    expect($idsBiz99)->toContain($idBiz99)->not->toContain($idBiz1);
});

// ------------------------------------------------------------------
// Cenário 2: adicionar tasks ao project (via Jana model McpTask)
// ------------------------------------------------------------------

it('cliente adiciona tasks ao project (3 tasks com priorities P0/P1/P2)', function () {
    // Project canônico Jana (key) — necessário pra McpTask via FK project_id
    $project = McpProject::create([
        'key'         => PMG_CJ_PROJECT_KEY . '-A',
        'name'        => 'Journey test project',
        'business_id' => PMG_CJ_BIZ_WAGNER,
        'status'      => 'active',
    ]);

    $tasksData = [
        ['priority' => 'p0', 'title' => 'Tarefa urgente'],
        ['priority' => 'p1', 'title' => 'Tarefa importante'],
        ['priority' => 'p2', 'title' => 'Tarefa normal'],
    ];

    $created = [];
    foreach ($tasksData as $i => $td) {
        $taskId = PMG_CJ_TASK_PREFIX . substr((string) microtime(true), -6) . "-{$i}";
        $task = McpTask::create([
            'task_id'     => $taskId,
            'project_id'  => $project->id,
            'business_id' => PMG_CJ_BIZ_WAGNER,
            'module'      => 'PMGMT',
            'title'       => $td['title'],
            'status'      => 'todo',
            'priority'    => $td['priority'],
            'type'        => 'task',
            'owner'       => 'wagner',
        ]);
        $created[] = $task;
    }

    expect($created)->toHaveCount(3);

    // Re-query: 3 tasks pendentes (todo) no project biz=1
    $pendingCount = McpTask::where('project_id', $project->id)
        ->where('business_id', PMG_CJ_BIZ_WAGNER)
        ->where('status', 'todo')
        ->count();

    expect($pendingCount)->toBe(3);

    // P0 fica primeiro quando ordenado
    $top = McpTask::where('project_id', $project->id)
        ->orderByRaw("FIELD(priority,'p0','p1','p2','p3','')")
        ->first();
    expect($top->priority)->toBe('p0');
});

// ------------------------------------------------------------------
// Cenário 3: mover task entre status (workflow Kanban completo)
// ------------------------------------------------------------------

it('cliente move task pelo workflow todo → doing → review → done', function () {
    $project = McpProject::create([
        'key'         => PMG_CJ_PROJECT_KEY . '-B',
        'name'        => 'Workflow test',
        'business_id' => PMG_CJ_BIZ_WAGNER,
        'status'      => 'active',
    ]);

    $taskId = PMG_CJ_TASK_PREFIX . 'WF-' . substr((string) microtime(true), -6);
    $task = McpTask::create([
        'task_id'     => $taskId,
        'project_id'  => $project->id,
        'business_id' => PMG_CJ_BIZ_WAGNER,
        'module'      => 'PMGMT',
        'title'       => 'Task que vai pelo workflow',
        'status'      => 'todo',
        'priority'    => 'p1',
        'type'        => 'task',
    ]);

    // Workflow
    $stages = ['doing', 'review', 'done'];
    foreach ($stages as $stage) {
        $task->status = $stage;
        $task->save();

        $fresh = McpTask::where('task_id', $taskId)->first();
        expect($fresh->status)->toBe($stage);
    }

    // Estado final
    $final = McpTask::where('task_id', $taskId)->first();
    expect($final->status)->toBe('done');
});

// ------------------------------------------------------------------
// Cenário 4: arquivar project completo (lifecycle end)
// ------------------------------------------------------------------

it('cliente arquiva project completo (update status archived via Service)', function () {
    $service = new ProjectService(PMG_CJ_BIZ_WAGNER);

    $projectId = $service->create([
        'nome'           => 'Project pra arquivar',
        'objetivo_macro' => 'simular lifecycle completo',
        'codigo'         => 'CJOURN-ARC',
    ]);

    $ok = $service->archive($projectId);
    expect($ok)->toBeTrue();

    $row = DB::table('mcp_projects')->where('id', $projectId)->first();
    expect($row->status)->toBe('archived');
});

it('arquivar project de OUTRO tenant retorna false (defesa multi-tenant)', function () {
    $serviceBiz1 = new ProjectService(PMG_CJ_BIZ_WAGNER);

    $projectId = $serviceBiz1->create([
        'nome'           => 'Project biz=1 protegido',
        'objetivo_macro' => 'biz=99 NÃO pode arquivar',
        'codigo'         => 'CJOURN-DEF',
    ]);

    // Outro tenant tenta arquivar — must fail
    $serviceBiz99 = new ProjectService(PMG_CJ_BIZ_FICTICIO);
    $ok = $serviceBiz99->archive($projectId);

    expect($ok)->toBeFalse();

    // Project original permanece intacto
    $row = DB::table('mcp_projects')->where('id', $projectId)->first();
    expect($row->status)->toBe('draft');
});

// ------------------------------------------------------------------
// Cenário 5: findDetail rejeita acesso cross-tenant (404)
// ------------------------------------------------------------------

it('findDetail() lança ModelNotFound quando project pertence a outro tenant', function () {
    $serviceBiz1 = new ProjectService(PMG_CJ_BIZ_WAGNER);

    $projectId = $serviceBiz1->create([
        'nome'           => 'Project biz=1 detalhe',
        'objetivo_macro' => 'cross-tenant teste',
        'codigo'         => 'CJOURN-DET',
    ]);

    $serviceBiz99 = new ProjectService(PMG_CJ_BIZ_FICTICIO);

    expect(fn () => $serviceBiz99->findDetail($projectId))
        ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
});
