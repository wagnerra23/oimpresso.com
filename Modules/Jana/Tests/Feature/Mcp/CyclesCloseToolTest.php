<?php

declare(strict_types=1);

// Local-dev worktree autoload fix: require_once ANTES de qualquer `use`
// pra garantir que a versão da worktree do CyclesCloseTool seja a carregada
// (vendor é symlink pra main repo onde a versão é antiga). Em CI/prod o
// autoload nativo do worktree resolve direto sem precisar disso.
(function () {
    $worktreeTool = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'Mcp' . DIRECTORY_SEPARATOR . 'Tools' . DIRECTORY_SEPARATOR . 'CyclesCloseTool.php';
    if (is_file($worktreeTool) && ! class_exists('Modules\\Jana\\Mcp\\Tools\\CyclesCloseTool', false)) {
        require_once $worktreeTool;
    }
})();

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Entities\Mcp\McpCycle;
use Modules\Jana\Entities\Mcp\McpProject;
use Modules\Jana\Entities\Mcp\McpTask;
use Modules\Jana\Mcp\Tools\CyclesCloseTool;

uses(Tests\TestCase::class);

/**
 * Gap #5 COMPARATIVO-MCP-ESTADO-DA-ARTE-2026-05-13 — rollover Linear-style por
 * default no CyclesCloseTool.
 *
 * Cobre:
 *   - rollover ligado por default (sem flag)
 *   - regras por status: doing/review/blocked rolam; todo NÃO rola (default)
 *   - force_rollover_todo=true inclui todo
 *   - rollover=false desliga
 *   - auto-detect próximo cycle (sem rollover_to explícito)
 *   - rollover_to explícito sobrescreve auto-detect
 *   - blocked task recebe comentário pra re-avaliação
 *   - cycle expirado fecha mesmo sem próximo cycle (rollover apenas se há alvo)
 */

beforeEach(function () {
    // era-sqlite: cria schema mcp_*/jana_* manual (sqlite-friendly). No MySQL persistente
    // do nightly isso corrompe os testes irmãos (lever do floor SDD). Cobertura real é
    // na lane sqlite (per-PR); pula no MySQL.
    if (config('database.default') !== 'sqlite') {
        $this->markTestSkipped('era-sqlite: corruptor de schema compartilhado no MySQL — sqlite-only no burn-down do floor SDD.');
    }

    // Schema minimal pra SQLite (mcp_cycles, mcp_projects, mcp_tasks, events, comments)
    Schema::dropIfExists('mcp_jira_projects');
    Schema::create('mcp_jira_projects', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->string('key', 24)->unique();
        $t->string('name', 100)->nullable();
        $t->text('description')->nullable();
        $t->unsignedBigInteger('lead_user_id')->nullable();
        $t->string('color', 16)->nullable();
        $t->string('icon', 32)->nullable();
        $t->string('status', 16)->default('active');
        $t->text('settings')->nullable();
        $t->unsignedBigInteger('default_workflow_id')->nullable();
        $t->text('custom_field_schema')->nullable();
        $t->unsignedInteger('next_task_number')->default(1);
        $t->timestamps();
        $t->softDeletes();
    });

    Schema::dropIfExists('mcp_cycles');
    Schema::create('mcp_cycles', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->unsignedBigInteger('project_id');
        $t->string('key', 24);
        $t->string('name', 100)->nullable();
        $t->date('start_date');
        $t->date('end_date');
        $t->text('goal')->nullable();
        $t->string('status', 16)->default('planning');
        $t->text('retro')->nullable();
        $t->unsignedBigInteger('owner_user_id')->nullable();
        $t->timestamps();
        $t->softDeletes();
        $t->unique(['project_id', 'key']);
    });

    Schema::dropIfExists('mcp_tasks');
    Schema::create('mcp_tasks', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->string('task_id', 40)->unique();
        $t->string('identifier', 24)->nullable();
        $t->unsignedBigInteger('project_id')->nullable();
        $t->unsignedBigInteger('epic_id')->nullable();
        $t->unsignedBigInteger('cycle_id')->nullable();
        $t->unsignedBigInteger('component_id')->nullable();
        $t->unsignedBigInteger('parent_task_id')->nullable();
        $t->string('module', 60);
        $t->string('title', 255);
        $t->text('description')->nullable();
        $t->string('status', 24)->default('todo');
        $t->string('type', 24)->default('story');
        $t->string('owner', 60)->nullable();
        $t->string('sprint', 40)->nullable();
        $t->string('priority', 8)->nullable();
        $t->decimal('estimate_h', 5, 1)->nullable();
        $t->decimal('story_points', 5, 1)->nullable();
        $t->string('estimate_unit', 16)->default('points');
        $t->decimal('estimate_value', 8, 2)->nullable();
        $t->timestamp('due_date')->nullable();
        $t->timestamp('started_at')->nullable();
        $t->timestamp('completed_at')->nullable();
        $t->text('labels')->nullable();
        $t->text('custom_fields')->nullable();
        $t->text('blocked_by')->nullable();
        $t->string('source_path', 500)->default('test');
        $t->string('source_git_sha', 40)->nullable();
        $t->timestamp('parsed_at')->nullable();
        $t->timestamps();
    });

    Schema::dropIfExists('mcp_task_events');
    Schema::create('mcp_task_events', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->string('task_id', 40);
        $t->string('event_type', 40);
        $t->string('from_value', 255)->nullable();
        $t->string('to_value', 255)->nullable();
        $t->string('author', 60)->nullable();
        $t->text('note')->nullable();
        $t->timestamp('occurred_at')->nullable();
        $t->timestamp('created_at')->nullable();
    });

    Schema::dropIfExists('mcp_task_comments');
    Schema::create('mcp_task_comments', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->string('task_id', 40);
        $t->string('author', 60);
        $t->text('body');
        $t->timestamps();
    });
});

afterEach(function () {
    if (config('database.default') !== 'sqlite') {
        return;
    }

    Schema::dropIfExists('mcp_task_comments');
    Schema::dropIfExists('mcp_task_events');
    Schema::dropIfExists('mcp_tasks');
    Schema::dropIfExists('mcp_cycles');
    Schema::dropIfExists('mcp_jira_projects');
});

/** Helper — cria um projeto + cycles + tasks pra cenário típico. */
function setupBasicScenario(): array
{
    $project = McpProject::create(['key' => 'COPI', 'name' => 'Test Project']);

    $cycleActive = McpCycle::create([
        'project_id' => $project->id,
        'key' => 'CYCLE-01',
        'name' => 'Cycle Ativo',
        'start_date' => today()->subDays(13)->toDateString(),
        'end_date' => today()->toDateString(),
        'status' => 'active',
    ]);

    $cycleNext = McpCycle::create([
        'project_id' => $project->id,
        'key' => 'CYCLE-02',
        'name' => 'Próximo',
        'start_date' => today()->addDay()->toDateString(),
        'end_date' => today()->addDays(14)->toDateString(),
        'status' => 'planning',
    ]);

    // Tasks variadas no cycle ativo
    $t1 = McpTask::create([
        'task_id' => 'US-X-001', 'module' => 'X', 'title' => 'doing 1',
        'status' => 'doing', 'cycle_id' => $cycleActive->id, 'source_path' => 'test',
    ]);
    $t2 = McpTask::create([
        'task_id' => 'US-X-002', 'module' => 'X', 'title' => 'review 1',
        'status' => 'review', 'cycle_id' => $cycleActive->id, 'source_path' => 'test',
    ]);
    $t3 = McpTask::create([
        'task_id' => 'US-X-003', 'module' => 'X', 'title' => 'blocked 1',
        'status' => 'blocked', 'cycle_id' => $cycleActive->id, 'source_path' => 'test',
    ]);
    $t4 = McpTask::create([
        'task_id' => 'US-X-004', 'module' => 'X', 'title' => 'todo 1',
        'status' => 'todo', 'cycle_id' => $cycleActive->id, 'source_path' => 'test',
    ]);
    $t5 = McpTask::create([
        'task_id' => 'US-X-005', 'module' => 'X', 'title' => 'done 1',
        'status' => 'done', 'cycle_id' => $cycleActive->id, 'source_path' => 'test',
    ]);

    return compact('project', 'cycleActive', 'cycleNext', 't1', 't2', 't3', 't4', 't5');
}

/**
 * Invoca o handle() do tool simulando um Laravel\Mcp\Request via arr de input.
 * Como Request::get() lê do request HTTP global, usamos request()->merge() pra inject.
 */
function invokeCyclesCloseTool(array $input): string
{
    request()->replace($input);
    $tool = new CyclesCloseTool();
    $response = $tool->handle(new \Laravel\Mcp\Request($input));

    // Extrai o text via reflection (Response.content é protected).
    $ref = new \ReflectionClass($response);
    $prop = $ref->getProperty('content');
    $prop->setAccessible(true);
    $content = $prop->getValue($response);

    if (is_object($content) && method_exists($content, 'toArray')) {
        $arr = $content->toArray();
        return $arr['text'] ?? json_encode($arr);
    }
    if (is_object($content)) {
        $cref = new \ReflectionClass($content);
        foreach ($cref->getProperties() as $p) {
            $p->setAccessible(true);
            $v = $p->getValue($content);
            if (is_string($v) && $v !== '') {
                return $v;
            }
        }
    }
    return (string) $content;
}

it('default (sem flag rollover) faz rollover Linear-style', function () {
    $s = setupBasicScenario();

    $out = invokeCyclesCloseTool(['project' => 'COPI']);

    // Cycle fechou
    expect($s['cycleActive']->fresh()->status)->toBe('closed');

    // doing/review/blocked rolaram pro próximo
    expect($s['t1']->fresh()->cycle_id)->toBe($s['cycleNext']->id); // doing
    expect($s['t2']->fresh()->cycle_id)->toBe($s['cycleNext']->id); // review
    expect($s['t3']->fresh()->cycle_id)->toBe($s['cycleNext']->id); // blocked

    // todo NÃO rolou (força triage)
    expect($s['t4']->fresh()->cycle_id)->toBe($s['cycleActive']->id);

    // done ficou no cycle original (terminal)
    expect($s['t5']->fresh()->cycle_id)->toBe($s['cycleActive']->id);

    expect($out)->toContain('CYCLE-02');
    expect($out)->toContain('auto-detectado');
});

it('força triage: status=todo NÃO rola por default + relatório conta restante', function () {
    $s = setupBasicScenario();

    $out = invokeCyclesCloseTool(['project' => 'COPI']);

    expect($s['t4']->fresh()->status)->toBe('todo');
    expect($s['t4']->fresh()->cycle_id)->toBe($s['cycleActive']->id);
    expect($out)->toContain('1'); // pelo menos 1 todo restante na contagem
    expect($out)->toContain('todo');
});

it('force_rollover_todo=true inclui status=todo no rollover', function () {
    $s = setupBasicScenario();

    invokeCyclesCloseTool([
        'project' => 'COPI',
        'force_rollover_todo' => true,
    ]);

    // todo TAMBÉM rolou agora
    expect($s['t4']->fresh()->cycle_id)->toBe($s['cycleNext']->id);
});

it('rollover=false desliga rollover e nenhuma task muda de cycle', function () {
    $s = setupBasicScenario();

    invokeCyclesCloseTool([
        'project' => 'COPI',
        'rollover' => false,
    ]);

    expect($s['cycleActive']->fresh()->status)->toBe('closed');
    expect($s['t1']->fresh()->cycle_id)->toBe($s['cycleActive']->id); // doing fica
    expect($s['t2']->fresh()->cycle_id)->toBe($s['cycleActive']->id); // review fica
    expect($s['t3']->fresh()->cycle_id)->toBe($s['cycleActive']->id); // blocked fica
});

it('rollover_to explícito sobrescreve auto-detect', function () {
    $s = setupBasicScenario();

    // Cria 3º cycle como destino explícito
    $cycleAlvo = McpCycle::create([
        'project_id' => $s['project']->id,
        'key' => 'CYCLE-99',
        'name' => 'Destino explícito',
        'start_date' => today()->addDays(20)->toDateString(),
        'end_date' => today()->addDays(34)->toDateString(),
        'status' => 'planning',
    ]);

    invokeCyclesCloseTool([
        'project' => 'COPI',
        'rollover_to' => 'CYCLE-99',
    ]);

    // doing foi pro CYCLE-99, NÃO pro CYCLE-02 auto-detectado
    expect($s['t1']->fresh()->cycle_id)->toBe($cycleAlvo->id);
});

it('tasks blocked recebem comentário de re-avaliação após rollover', function () {
    $s = setupBasicScenario();

    invokeCyclesCloseTool(['project' => 'COPI']);

    $comentarios = DB::table('mcp_task_comments')
        ->where('author', 'cycles-close')
        ->where('task_id', 'US-X-003') // a blocked
        ->get();

    expect($comentarios)->toHaveCount(1);
    expect($comentarios->first()->body)->toContain('blocked');
    expect($comentarios->first()->body)->toContain('CYCLE-01');
    expect($comentarios->first()->body)->toContain('CYCLE-02');
    expect($comentarios->first()->body)->toContain('ainda é relevante');
});

it('audit log mcp_task_events registra rollover por task', function () {
    $s = setupBasicScenario();

    invokeCyclesCloseTool(['project' => 'COPI']);

    $eventos = DB::table('mcp_task_events')
        ->where('author', 'cycles-close')
        ->where('event_type', 'field_updated')
        ->get();

    // 3 tasks (doing/review/blocked) → 3 events
    expect($eventos)->toHaveCount(3);

    $taskIds = $eventos->pluck('task_id')->all();
    expect($taskIds)->toContain('US-X-001');
    expect($taskIds)->toContain('US-X-002');
    expect($taskIds)->toContain('US-X-003');
});

it('sem cycle alvo (auto-detect falhou) ainda fecha o cycle, sem rollover', function () {
    $project = McpProject::create(['key' => 'COPI', 'name' => 'Solo']);

    $cycleActive = McpCycle::create([
        'project_id' => $project->id,
        'key' => 'CYCLE-SOLO',
        'name' => 'Único',
        'start_date' => today()->subDays(13)->toDateString(),
        'end_date' => today()->toDateString(),
        'status' => 'active',
    ]);

    McpTask::create([
        'task_id' => 'US-Y-001', 'module' => 'Y', 'title' => 'doing órfão',
        'status' => 'doing', 'cycle_id' => $cycleActive->id, 'source_path' => 'test',
    ]);

    invokeCyclesCloseTool(['project' => 'COPI']);

    expect($cycleActive->fresh()->status)->toBe('closed');
    // Task continua no cycle original (sem alvo pra rolar)
    expect(McpTask::where('task_id', 'US-Y-001')->first()->cycle_id)->toBe($cycleActive->id);
});

it('retro JSON é gravado no cycle se passado', function () {
    $s = setupBasicScenario();

    invokeCyclesCloseTool([
        'project' => 'COPI',
        'retro_sucessos' => 'FSM live em prod',
        'retro_falhas' => 'Daemon Baileys QR-fest 3x',
        'retro_licao' => 'Empilhar PRs daemon',
    ]);

    $retro = $s['cycleActive']->fresh()->retro;
    expect($retro['sucessos'])->toBe('FSM live em prod');
    expect($retro['falhas'])->toBe('Daemon Baileys QR-fest 3x');
    expect($retro['licao_proximo'])->toBe('Empilhar PRs daemon');
});
