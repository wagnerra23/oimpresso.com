<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Console\Commands\McpTasksHealthCheckCommand;
use Modules\Jana\Entities\Mcp\McpTask;

uses(Tests\TestCase::class);

/**
 * Bug #4 (BUGS-MCP-SYNC-2026-05-13) — mcp:tasks:health-check command.
 *
 * Cria mcp_tasks + mcp_git_links + mcp_task_comments + mcp_task_events em
 * SQLite manual (migration original usa enum/json incompatível com SQLite
 * em alguns drivers — replicamos com colunas string nullable).
 *
 * Testa:
 *  - stale_todo (>21d sem update) flagga
 *  - stale_blocked (>30d sem update) flagga
 *  - stale_doing (>7d sem commit linkado) flagga
 *  - task normal (1d update) NÃO flagga
 *  - --auto-comment cria comentários nas flagged
 */

beforeEach(function () {
    // era-sqlite: cria schema mcp_*/jana_* manual (sqlite-friendly). No MySQL persistente
    // do nightly isso corrompe os testes irmãos (lever do floor SDD). Cobertura real é
    // na lane sqlite (per-PR); pula no MySQL.
    if (config('database.default') !== 'sqlite') {
        $this->markTestSkipped('era-sqlite: corruptor de schema compartilhado no MySQL — sqlite-only no burn-down do floor SDD.');
    }

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

    Schema::dropIfExists('mcp_git_links');
    Schema::create('mcp_git_links', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->string('task_id', 40);
        $t->string('action', 24);
        $t->string('repo_full_name', 120)->nullable();
        $t->string('commit_sha', 40)->nullable();
        $t->unsignedInteger('pr_number')->nullable();
        $t->string('branch', 200)->nullable();
        $t->string('author_username', 60)->nullable();
        $t->text('message')->nullable();
        $t->timestamp('occurred_at')->nullable();
        $t->timestamps();
    });

    Schema::dropIfExists('mcp_task_comments');
    Schema::create('mcp_task_comments', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->string('task_id', 40);
        $t->string('author', 60);
        $t->text('body');
        $t->timestamps();
    });

    Schema::dropIfExists('mcp_task_events');
    Schema::create('mcp_task_events', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->string('task_id', 40);
        $t->string('event_type', 40);
        $t->string('author', 60)->nullable();
        $t->text('note')->nullable();
        $t->text('payload')->nullable();
        $t->timestamps();
    });
});

afterEach(function () {
    if (config('database.default') !== 'sqlite') {
        return;
    }

    Schema::dropIfExists('mcp_tasks');
    Schema::dropIfExists('mcp_git_links');
    Schema::dropIfExists('mcp_task_comments');
    Schema::dropIfExists('mcp_task_events');
});

/**
 * Helper — cria task com updated_at backdated em N dias.
 */
function makeStaleTask(string $taskId, string $status, int $daysOld, ?string $owner = 'wagner'): McpTask
{
    $task = McpTask::create([
        'task_id' => $taskId,
        'identifier' => $taskId,
        'module' => 'TEST',
        'title' => "Task fake {$taskId} ({$status}, {$daysOld}d)",
        'status' => $status,
        'owner' => $owner,
        'priority' => 'p2',
        'estimate_unit' => 'points',
        'source_path' => 'test',
        'parsed_at' => now(),
    ]);

    // Backdate updated_at — Eloquent default é now()
    DB::table('mcp_tasks')
        ->where('id', $task->id)
        ->update([
            'created_at' => now()->subDays($daysOld),
            'updated_at' => now()->subDays($daysOld),
        ]);

    return $task->fresh();
}

it('flagga stale_todo, stale_blocked, stale_doing e NÃO flagga task normal', function () {
    // 4 tasks com states variados
    makeStaleTask('US-TEST-001', 'todo', 25);     // >21d sem update → stale_todo
    makeStaleTask('US-TEST-002', 'blocked', 35);  // >30d sem update → stale_blocked
    makeStaleTask('US-TEST-003', 'doing', 10);    // >7d sem commit → stale_doing
    makeStaleTask('US-TEST-004', 'todo', 1);      // 1d → normal, NÃO flagga

    $command = app(McpTasksHealthCheckCommand::class);
    $flagged = $command->scanStaleness();

    expect($flagged)->toHaveCount(3);

    $taskIds = $flagged->pluck('task_id')->all();
    expect($taskIds)->toContain('US-TEST-001');
    expect($taskIds)->toContain('US-TEST-002');
    expect($taskIds)->toContain('US-TEST-003');
    expect($taskIds)->not->toContain('US-TEST-004');

    $flags = $flagged->pluck('flag', 'task_id')->all();
    expect($flags['US-TEST-001'])->toBe('stale_todo');
    expect($flags['US-TEST-002'])->toBe('stale_blocked');
    expect($flags['US-TEST-003'])->toBe('stale_doing');
});

it('stale_review flagga >5d sem update', function () {
    makeStaleTask('US-TEST-010', 'review', 7);  // >5d → stale_review
    makeStaleTask('US-TEST-011', 'review', 3);  // 3d → normal

    $command = app(McpTasksHealthCheckCommand::class);
    $flagged = $command->scanStaleness();

    expect($flagged)->toHaveCount(1);
    expect($flagged->first()['task_id'])->toBe('US-TEST-010');
    expect($flagged->first()['flag'])->toBe('stale_review');
});

it('stale_doing NÃO flagga se houve commit linkado recente (<7d)', function () {
    makeStaleTask('US-TEST-020', 'doing', 30);  // backlog 30d MAS commit recente

    // Commit linkado 2 dias atrás
    DB::table('mcp_git_links')->insert([
        'task_id' => 'US-TEST-020',
        'action' => 'refs',
        'commit_sha' => 'abc123def456',
        'occurred_at' => now()->subDays(2),
        'created_at' => now()->subDays(2),
        'updated_at' => now()->subDays(2),
    ]);

    $command = app(McpTasksHealthCheckCommand::class);
    $flagged = $command->scanStaleness();

    expect($flagged)->toHaveCount(0); // commit recente cancela stale_doing
});

it('stale_doing flagga se último commit linkado é antigo (>7d)', function () {
    makeStaleTask('US-TEST-030', 'doing', 30);

    // Commit antigo (10 dias) — passou do threshold doing=7d
    DB::table('mcp_git_links')->insert([
        'task_id' => 'US-TEST-030',
        'action' => 'refs',
        'commit_sha' => 'old1234567890',
        'occurred_at' => now()->subDays(10),
        'created_at' => now()->subDays(10),
        'updated_at' => now()->subDays(10),
    ]);

    $command = app(McpTasksHealthCheckCommand::class);
    $flagged = $command->scanStaleness();

    expect($flagged)->toHaveCount(1);
    expect($flagged->first()['flag'])->toBe('stale_doing');
});

it('filtro --owner restringe scan ao owner informado', function () {
    makeStaleTask('US-TEST-040', 'todo', 25, 'wagner');
    makeStaleTask('US-TEST-041', 'todo', 25, 'eliana');

    $command = app(McpTasksHealthCheckCommand::class);
    $apenasWagner = $command->scanStaleness('wagner');

    expect($apenasWagner)->toHaveCount(1);
    expect($apenasWagner->first()['owner'])->toBe('wagner');
});

it('tasks done/cancelled NÃO entram no scan (só ativas)', function () {
    makeStaleTask('US-TEST-050', 'done', 100);
    makeStaleTask('US-TEST-051', 'cancelled', 100);
    makeStaleTask('US-TEST-052', 'todo', 30);  // só esta flagga

    $command = app(McpTasksHealthCheckCommand::class);
    $flagged = $command->scanStaleness();

    expect($flagged)->toHaveCount(1);
    expect($flagged->first()['task_id'])->toBe('US-TEST-052');
});

it('--auto-comment cria comentário em cada task flagged', function () {
    makeStaleTask('US-TEST-060', 'todo', 25);
    makeStaleTask('US-TEST-061', 'blocked', 35);
    makeStaleTask('US-TEST-062', 'todo', 1);  // não flagga

    $exitCode = Artisan::call('mcp:tasks:health-check', ['--auto-comment' => true]);

    expect($exitCode)->toBe(0);

    // Conferir comentários criados (mcp_task_comments)
    $comentarios = DB::table('mcp_task_comments')
        ->where('author', 'mcp-health-bot')
        ->get();

    expect($comentarios)->toHaveCount(2);  // 2 flagged → 2 comentários

    $taskIds = $comentarios->pluck('task_id')->all();
    expect($taskIds)->toContain('US-TEST-060');
    expect($taskIds)->toContain('US-TEST-061');
    expect($taskIds)->not->toContain('US-TEST-062');

    // Body deve conter a flag e sugestão
    $body060 = $comentarios->firstWhere('task_id', 'US-TEST-060')->body;
    expect($body060)->toContain('stale_todo');
    expect($body060)->toContain('Health check automático');
});

it('SEM --auto-comment não cria comentário (default só relatório)', function () {
    makeStaleTask('US-TEST-070', 'todo', 25);

    Artisan::call('mcp:tasks:health-check');

    $comentarios = DB::table('mcp_task_comments')->count();
    expect($comentarios)->toBe(0);
});

it('output markdown contém tabela com colunas task_id|status|última_update|flag|sugestão', function () {
    makeStaleTask('US-TEST-080', 'todo', 25);

    Artisan::call('mcp:tasks:health-check');
    $output = Artisan::output();

    // Header da tabela presente
    expect($output)->toContain('task_id');
    expect($output)->toContain('status');
    expect($output)->toContain('flag');
    expect($output)->toContain('sugestão');
    expect($output)->toContain('US-TEST-080');
    expect($output)->toContain('stale_todo');
});

it('output markdown sem tasks stale mostra mensagem de "backlog saudável"', function () {
    makeStaleTask('US-TEST-090', 'todo', 1);  // fresca

    Artisan::call('mcp:tasks:health-check');
    $output = Artisan::output();

    expect($output)->toContain('Nenhuma task stale');
});
