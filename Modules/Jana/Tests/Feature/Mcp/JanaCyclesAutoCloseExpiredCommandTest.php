<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Entities\Mcp\McpCycle;
use Modules\Jana\Entities\Mcp\McpProject;
use Modules\Jana\Entities\Mcp\McpTask;

uses(Tests\TestCase::class);

// Local-dev worktree autoload fix: vendor é symlink pra main repo, então
// $baseDir do autoload aponta pro main repo Modules/ (não worktree). Quando
// o command class só existe na worktree, autoload falha. Aqui registramos
// um autoloader que tenta o worktree primeiro pra classe específica.
spl_autoload_register(function (string $class) {
    if (! str_starts_with($class, 'Modules\\Jana\\Console\\Commands\\JanaCyclesAutoCloseExpiredCommand')) {
        return;
    }
    $relative = str_replace(['Modules\\Jana\\', '\\'], ['', DIRECTORY_SEPARATOR], $class) . '.php';
    $candidate = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . $relative;
    if (is_file($candidate)) {
        require_once $candidate;
    }
}, true, true);

/**
 * Gap #5 COMPARATIVO-MCP-ESTADO-DA-ARTE-2026-05-13 — auto-rollover daily.
 *
 * Cobre:
 *   - cycle com end_date < today + status active|planning é detectado
 *   - rollover automático (doing/review/blocked) pro próximo cycle
 *   - cria próximo cycle vazio se não existir (auto_create_next)
 *   - infere chave CYCLE-NN+1 com zero padding
 *   - dry-run não modifica banco
 *   - cycle com end_date no futuro NÃO é tocado
 *   - cycle já closed NÃO é tocado
 *   - blocked recebe comentário
 *   - retro.auto_closed=true após fechamento automático
 */

beforeEach(function () {
    // era-sqlite: cria schema mcp_*/jana_* manual (sqlite-friendly). No MySQL persistente
    // do nightly isso corrompe os testes irmãos (lever do floor SDD). Cobertura real é
    // na lane sqlite (per-PR); pula no MySQL.
    if (config('database.default') !== 'sqlite') {
        $this->markTestSkipped('era-sqlite: corruptor de schema compartilhado no MySQL — sqlite-only no burn-down do floor SDD.');
    }

    // Worktree fix: registra o command manualmente caso o ServiceProvider em
    // execução (carregado da main repo via vendor symlink) ainda não conheça
    // o arquivo da worktree. Em CI/prod o command é auto-discovered.
    $cmdClass = 'Modules\\Jana\\Console\\Commands\\JanaCyclesAutoCloseExpiredCommand';
    if (class_exists($cmdClass)) {
        $kernel = app(\Illuminate\Contracts\Console\Kernel::class);
        if (method_exists($kernel, 'registerCommand')) {
            // Já registrado? skip pra evitar dup
            $existing = collect(\Illuminate\Support\Facades\Artisan::all())
                ->keys()
                ->contains('jana:cycles:auto-close-expired');
            if (! $existing) {
                $kernel->registerCommand(new $cmdClass());
            }
        }
    }

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

it('fecha cycle expirado e rola tasks doing/review/blocked pro próximo cycle existente', function () {
    $project = McpProject::create(['key' => 'COPI', 'name' => 'P']);

    $expired = McpCycle::create([
        'project_id' => $project->id,
        'key' => 'CYCLE-01',
        'start_date' => today()->subDays(14)->toDateString(),
        'end_date' => today()->subDay()->toDateString(),
        'status' => 'active',
    ]);

    $next = McpCycle::create([
        'project_id' => $project->id,
        'key' => 'CYCLE-02',
        'start_date' => today()->toDateString(),
        'end_date' => today()->addDays(13)->toDateString(),
        'status' => 'planning',
    ]);

    $tDoing = McpTask::create([
        'task_id' => 'US-A-001', 'module' => 'A', 'title' => 'd', 'status' => 'doing',
        'cycle_id' => $expired->id, 'source_path' => 'test',
    ]);
    $tBlocked = McpTask::create([
        'task_id' => 'US-A-002', 'module' => 'A', 'title' => 'b', 'status' => 'blocked',
        'cycle_id' => $expired->id, 'source_path' => 'test',
    ]);
    $tTodo = McpTask::create([
        'task_id' => 'US-A-003', 'module' => 'A', 'title' => 't', 'status' => 'todo',
        'cycle_id' => $expired->id, 'source_path' => 'test',
    ]);

    $exit = Artisan::call('jana:cycles:auto-close-expired');

    expect($exit)->toBe(0);
    expect($expired->fresh()->status)->toBe('closed');
    expect($tDoing->fresh()->cycle_id)->toBe($next->id);
    expect($tBlocked->fresh()->cycle_id)->toBe($next->id);
    // todo NÃO rola por default (força triage)
    expect($tTodo->fresh()->cycle_id)->toBe($expired->id);

    // Retro auto-anotado
    $retro = $expired->fresh()->retro;
    expect($retro['auto_closed'])->toBeTrue();
    expect($retro)->toHaveKey('auto_closed_at');
});

it('cria próximo cycle vazio se não existir (auto_create_next)', function () {
    $project = McpProject::create(['key' => 'COPI', 'name' => 'P']);

    $expired = McpCycle::create([
        'project_id' => $project->id,
        'key' => 'CYCLE-07',
        'start_date' => today()->subDays(14)->toDateString(),
        'end_date' => today()->subDay()->toDateString(),
        'status' => 'active',
    ]);

    McpTask::create([
        'task_id' => 'US-B-001', 'module' => 'B', 'title' => 'doing órfão',
        'status' => 'doing', 'cycle_id' => $expired->id, 'source_path' => 'test',
    ]);

    Artisan::call('jana:cycles:auto-close-expired');

    // Próximo cycle inferido: CYCLE-08 (mantém zero padding)
    $next = McpCycle::where('project_id', $project->id)->where('key', 'CYCLE-08')->first();
    expect($next)->not->toBeNull();
    expect($next->status)->toBe('planning');
    expect($next->name)->toContain('Auto-created');

    // Task migrou
    $task = McpTask::where('task_id', 'US-B-001')->first();
    expect($task->cycle_id)->toBe($next->id);
});

it('infere chave CYCLE-08 a partir de CYCLE-07 preservando zero padding', function () {
    $project = McpProject::create(['key' => 'COPI', 'name' => 'P']);

    McpCycle::create([
        'project_id' => $project->id,
        'key' => 'CYCLE-07',
        'start_date' => today()->subDays(14)->toDateString(),
        'end_date' => today()->subDay()->toDateString(),
        'status' => 'active',
    ]);

    Artisan::call('jana:cycles:auto-close-expired');

    expect(McpCycle::where('project_id', $project->id)->where('key', 'CYCLE-08')->exists())->toBeTrue();
});

it('dry-run não modifica nada', function () {
    $project = McpProject::create(['key' => 'COPI', 'name' => 'P']);

    $expired = McpCycle::create([
        'project_id' => $project->id,
        'key' => 'CYCLE-01',
        'start_date' => today()->subDays(14)->toDateString(),
        'end_date' => today()->subDay()->toDateString(),
        'status' => 'active',
    ]);

    McpTask::create([
        'task_id' => 'US-C-001', 'module' => 'C', 'title' => 'd', 'status' => 'doing',
        'cycle_id' => $expired->id, 'source_path' => 'test',
    ]);

    Artisan::call('jana:cycles:auto-close-expired', ['--dry-run' => true]);

    // Cycle continua active, task continua no cycle
    expect($expired->fresh()->status)->toBe('active');
    expect(McpCycle::where('project_id', $project->id)->count())->toBe(1); // não criou outro
    expect(McpTask::where('task_id', 'US-C-001')->first()->cycle_id)->toBe($expired->id);
});

it('cycle com end_date no futuro NÃO é tocado', function () {
    $project = McpProject::create(['key' => 'COPI', 'name' => 'P']);

    $future = McpCycle::create([
        'project_id' => $project->id,
        'key' => 'CYCLE-FUTURO',
        'start_date' => today()->toDateString(),
        'end_date' => today()->addDays(7)->toDateString(),
        'status' => 'active',
    ]);

    Artisan::call('jana:cycles:auto-close-expired');

    expect($future->fresh()->status)->toBe('active');
});

it('cycle já closed NÃO é tocado novamente', function () {
    $project = McpProject::create(['key' => 'COPI', 'name' => 'P']);

    $alreadyClosed = McpCycle::create([
        'project_id' => $project->id,
        'key' => 'CYCLE-ANTIGO',
        'start_date' => today()->subDays(30)->toDateString(),
        'end_date' => today()->subDays(15)->toDateString(),
        'status' => 'closed',
    ]);

    $countBefore = McpCycle::count();
    Artisan::call('jana:cycles:auto-close-expired');
    $countAfter = McpCycle::count();

    expect($countAfter)->toBe($countBefore); // nada criado
    expect($alreadyClosed->fresh()->status)->toBe('closed');
});

it('tasks blocked recebem comentário pra re-avaliação', function () {
    $project = McpProject::create(['key' => 'COPI', 'name' => 'P']);

    $expired = McpCycle::create([
        'project_id' => $project->id,
        'key' => 'CYCLE-01',
        'start_date' => today()->subDays(14)->toDateString(),
        'end_date' => today()->subDay()->toDateString(),
        'status' => 'active',
    ]);

    $next = McpCycle::create([
        'project_id' => $project->id,
        'key' => 'CYCLE-02',
        'start_date' => today()->toDateString(),
        'end_date' => today()->addDays(13)->toDateString(),
        'status' => 'planning',
    ]);

    McpTask::create([
        'task_id' => 'US-D-001', 'module' => 'D', 'title' => 'blocked task',
        'status' => 'blocked', 'cycle_id' => $expired->id, 'source_path' => 'test',
    ]);

    Artisan::call('jana:cycles:auto-close-expired');

    $comentarios = DB::table('mcp_task_comments')
        ->where('task_id', 'US-D-001')
        ->where('author', 'auto-close-expired')
        ->get();

    expect($comentarios)->toHaveCount(1);
    expect($comentarios->first()->body)->toContain('blocked');
    expect($comentarios->first()->body)->toContain('CYCLE-01');
});

it('--force-rollover-todo inclui tasks todo no rollover', function () {
    $project = McpProject::create(['key' => 'COPI', 'name' => 'P']);

    $expired = McpCycle::create([
        'project_id' => $project->id,
        'key' => 'CYCLE-01',
        'start_date' => today()->subDays(14)->toDateString(),
        'end_date' => today()->subDay()->toDateString(),
        'status' => 'active',
    ]);

    $next = McpCycle::create([
        'project_id' => $project->id,
        'key' => 'CYCLE-02',
        'start_date' => today()->toDateString(),
        'end_date' => today()->addDays(13)->toDateString(),
        'status' => 'planning',
    ]);

    $todo = McpTask::create([
        'task_id' => 'US-E-001', 'module' => 'E', 'title' => 'todo antigo',
        'status' => 'todo', 'cycle_id' => $expired->id, 'source_path' => 'test',
    ]);

    Artisan::call('jana:cycles:auto-close-expired', ['--force-rollover-todo' => true]);

    expect($todo->fresh()->cycle_id)->toBe($next->id);
});

it('filtro --project restringe scan a um projeto específico', function () {
    $p1 = McpProject::create(['key' => 'COPI', 'name' => 'P1']);
    $p2 = McpProject::create(['key' => 'XYZ', 'name' => 'P2']);

    $c1 = McpCycle::create([
        'project_id' => $p1->id, 'key' => 'CYCLE-A',
        'start_date' => today()->subDays(14)->toDateString(),
        'end_date' => today()->subDay()->toDateString(),
        'status' => 'active',
    ]);
    $c2 = McpCycle::create([
        'project_id' => $p2->id, 'key' => 'CYCLE-B',
        'start_date' => today()->subDays(14)->toDateString(),
        'end_date' => today()->subDay()->toDateString(),
        'status' => 'active',
    ]);

    Artisan::call('jana:cycles:auto-close-expired', ['--project' => 'COPI']);

    expect($c1->fresh()->status)->toBe('closed'); // fechou
    expect($c2->fresh()->status)->toBe('active');  // intocado
});

it('audit log mcp_task_events grava rollover com author=auto-close-expired', function () {
    $project = McpProject::create(['key' => 'COPI', 'name' => 'P']);

    $expired = McpCycle::create([
        'project_id' => $project->id, 'key' => 'CYCLE-01',
        'start_date' => today()->subDays(14)->toDateString(),
        'end_date' => today()->subDay()->toDateString(),
        'status' => 'active',
    ]);

    $next = McpCycle::create([
        'project_id' => $project->id, 'key' => 'CYCLE-02',
        'start_date' => today()->toDateString(),
        'end_date' => today()->addDays(13)->toDateString(),
        'status' => 'planning',
    ]);

    McpTask::create([
        'task_id' => 'US-F-001', 'module' => 'F', 'title' => 't', 'status' => 'doing',
        'cycle_id' => $expired->id, 'source_path' => 'test',
    ]);

    Artisan::call('jana:cycles:auto-close-expired');

    $eventos = DB::table('mcp_task_events')
        ->where('task_id', 'US-F-001')
        ->where('author', 'auto-close-expired')
        ->get();

    expect($eventos)->toHaveCount(1);
    expect($eventos->first()->event_type)->toBe('field_updated');
});
