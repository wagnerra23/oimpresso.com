<?php

declare(strict_types=1);

// Local-dev worktree autoload fix (espelha CyclesCloseToolTest): garante a versão
// da worktree do CyclesCreateTool. Em CI/prod o autoload nativo resolve sozinho.
(function () {
    $worktreeTool = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'Mcp' . DIRECTORY_SEPARATOR . 'Tools' . DIRECTORY_SEPARATOR . 'CyclesCreateTool.php';
    if (is_file($worktreeTool) && ! class_exists('Modules\\Jana\\Mcp\\Tools\\CyclesCreateTool', false)) {
        require_once $worktreeTool;
    }
})();

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Entities\Mcp\McpCycle;
use Modules\Jana\Entities\Mcp\McpProject;
use Modules\Jana\Entities\Mcp\McpTask;
use Modules\Jana\Mcp\Tools\CyclesCreateTool;

uses(Tests\TestCase::class);

/**
 * G18 (porta de saída do loop) — cycles-create propõe corte do backlog por prioridade.
 * Cobre: propõe top-N por prioridade (P0 antes de P1); ignora tasks com cycle/done;
 * default (sem propose_backlog) não lista; read-only (não atribui cycle às tasks).
 *
 * @see Modules/Jana/Mcp/Tools/CyclesCreateTool.php (proposeBacklog)
 */

beforeEach(function () {
    if (config('database.default') !== 'sqlite') {
        $this->markTestSkipped('era-sqlite: schema manual corrompe MySQL compartilhado — sqlite-only.');
    }

    Schema::dropIfExists('mcp_jira_projects');
    Schema::create('mcp_jira_projects', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->string('key', 24)->unique();
        $t->string('name', 100)->nullable();
        $t->string('status', 16)->default('active');
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
        $t->timestamps();
        $t->softDeletes();
        $t->unique(['project_id', 'key']);
    });

    Schema::dropIfExists('mcp_cycle_goals');
    Schema::create('mcp_cycle_goals', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->unsignedBigInteger('cycle_id');
        $t->text('description');
        $t->string('metric_name', 100)->nullable();
        $t->string('target_value', 100)->nullable();
        $t->string('achieved_value', 100)->nullable();
        $t->string('status', 16)->default('open');
        $t->unsignedInteger('sort_order')->default(1);
        $t->timestamps();
    });

    Schema::dropIfExists('mcp_tasks');
    Schema::create('mcp_tasks', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->string('task_id', 40)->unique();
        $t->unsignedBigInteger('project_id')->nullable();
        $t->unsignedBigInteger('cycle_id')->nullable();
        $t->string('module', 60);
        $t->string('title', 255);
        $t->string('status', 24)->default('todo');
        $t->string('priority', 8)->nullable();
        $t->string('source_path', 500)->default('test');
        $t->timestamps();
    });

    // AuthorizesMcpMutation exige scope — injeta user autorizado (caminho feliz; negação
    // tem cobertura própria em AuthorizesMcpMutationTest). Espelha CyclesCloseToolTest.
    app('auth')->resolveUsersUsing(fn ($guard = null) => new class implements \Illuminate\Contracts\Auth\Authenticatable {
        public function can($abilities, $arguments = []): bool { return true; }
        public function getAuthIdentifierName() { return 'id'; }
        public function getAuthIdentifier() { return 42; }
        public function getAuthPasswordName() { return 'password'; }
        public function getAuthPassword() { return ''; }
        public function getRememberToken() { return ''; }
        public function setRememberToken($value) {}
        public function getRememberTokenName() { return 'remember_token'; }
    });
});

afterEach(function () {
    if (config('database.default') !== 'sqlite') {
        return;
    }
    Schema::dropIfExists('mcp_tasks');
    Schema::dropIfExists('mcp_cycle_goals');
    Schema::dropIfExists('mcp_cycles');
    Schema::dropIfExists('mcp_jira_projects');
});

function invokeCyclesCreateTool(array $input): string
{
    request()->replace($input);
    $tool = new CyclesCreateTool();
    $response = $tool->handle(new \Laravel\Mcp\Request($input));

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

/** Project + tasks de backlog variadas (prioridades, com/sem cycle, done). */
function setupBacklog(): McpProject
{
    $project = McpProject::create(['key' => 'COPI', 'name' => 'Test']);

    // backlog candidatos (sem cycle, backlog/todo) — prioridades fora de ordem de propósito
    McpTask::create(['task_id' => 'US-A-002', 'project_id' => $project->id, 'module' => 'A', 'title' => 'p1 b', 'status' => 'todo', 'priority' => 'P1', 'source_path' => 'test']);
    McpTask::create(['task_id' => 'US-A-001', 'project_id' => $project->id, 'module' => 'A', 'title' => 'p0 a', 'status' => 'backlog', 'priority' => 'P0', 'source_path' => 'test']);
    McpTask::create(['task_id' => 'US-A-003', 'project_id' => $project->id, 'module' => 'A', 'title' => 'p2 c', 'status' => 'backlog', 'priority' => 'P2', 'source_path' => 'test']);

    // NÃO candidatos:
    McpTask::create(['task_id' => 'US-A-009', 'project_id' => $project->id, 'cycle_id' => 999, 'module' => 'A', 'title' => 'já tem cycle', 'status' => 'todo', 'priority' => 'P0', 'source_path' => 'test']);
    McpTask::create(['task_id' => 'US-A-010', 'project_id' => $project->id, 'module' => 'A', 'title' => 'done', 'status' => 'done', 'priority' => 'P0', 'source_path' => 'test']);

    return $project;
}

it('propose_backlog=3 lista candidatos por prioridade (P0 antes de P1)', function () {
    setupBacklog();

    $out = invokeCyclesCreateTool([
        'project' => 'COPI', 'key' => 'CYCLE-09', 'name' => 'Novo',
        'end_date' => today()->addDays(14)->toDateString(), 'propose_backlog' => 3,
    ]);

    expect($out)->toContain('Candidatos do backlog');
    expect($out)->toContain('US-A-001'); // P0
    expect($out)->toContain('US-A-002'); // P1
    // P0 aparece antes de P1 no texto
    expect(strpos($out, 'US-A-001'))->toBeLessThan(strpos($out, 'US-A-002'));
    // read-only: NÃO atribuiu cycle às candidatas
    expect(McpTask::where('task_id', 'US-A-001')->first()->cycle_id)->toBeNull();
});

it('propose_backlog ignora tasks com cycle e done', function () {
    setupBacklog();

    $out = invokeCyclesCreateTool([
        'project' => 'COPI', 'key' => 'CYCLE-09', 'name' => 'Novo',
        'end_date' => today()->addDays(14)->toDateString(), 'propose_backlog' => 10,
    ]);

    expect($out)->not->toContain('US-A-009'); // tem cycle
    expect($out)->not->toContain('US-A-010'); // done
});

it('sem propose_backlog (default) NÃO lista candidatos', function () {
    setupBacklog();

    $out = invokeCyclesCreateTool([
        'project' => 'COPI', 'key' => 'CYCLE-09', 'name' => 'Novo',
        'end_date' => today()->addDays(14)->toDateString(),
    ]);

    expect($out)->toContain('Cycle **CYCLE-09** criado');
    expect($out)->not->toContain('Candidatos do backlog');
});

it('cria o cycle normalmente (sanity)', function () {
    McpProject::create(['key' => 'COPI', 'name' => 'Test']);

    $out = invokeCyclesCreateTool([
        'project' => 'COPI', 'key' => 'CYCLE-09', 'name' => 'Novo',
        'end_date' => today()->addDays(14)->toDateString(),
    ]);

    expect($out)->toContain('Cycle **CYCLE-09** criado');
    expect(McpCycle::where('key', 'CYCLE-09')->exists())->toBeTrue();
});
