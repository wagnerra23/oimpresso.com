<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Entities\Mcp\McpTaskEvent;
use Modules\Jana\Services\TaskRegistry\TaskCrudService;

uses(Tests\TestCase::class);

/**
 * Fase 2 (ADR 0278) — acceptance_ref em mcp_tasks + consumer SOFT no TaskCrudService.
 *
 * GUARD tests:
 *   1. acceptance_ref persiste via service
 *   2. acceptance_ref + status=done juntos → grava ref + carimba completed_at
 *   3. done SEM acceptance_ref → evento de AVISO (soft, NÃO throw)
 *   4. done COM acceptance_ref preexistente → nenhum aviso
 *   5. campo não-whitelisted continua bloqueado (whitelist não virou wildcard)
 *
 * Padrão era-sqlite (schema sintético inline + activitylog OFF): a lane per-PR roda
 * sqlite :memory:; RefreshDatabase puxaria a migration MySQL-only do extend de
 * mcp_tasks (ALTER ... MODIFY ENUM) e quebraria. McpTask usa LogsActivity → desligo
 * o activitylog pra não exigir a tabela activity_log.
 *
 * @see Modules/Jana/Database/Migrations/2026_06_15_150000_add_acceptance_ref_to_mcp_tasks.php
 * @see memory/decisions/0278-arquitetura-rede-ia-duravel-anti-vazamento.md
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('era-sqlite: schema sintético incompatível com MySQL persistente (floor SDD).');
    }

    config(['activitylog.enabled' => false]); // McpTask usa LogsActivity

    Schema::dropIfExists('mcp_tasks');
    Schema::dropIfExists('mcp_task_events');

    Schema::create('mcp_tasks', function ($t) {
        $t->bigIncrements('id');
        $t->string('task_id', 40)->unique();
        $t->string('module', 60)->nullable();
        $t->string('title', 255)->nullable();
        $t->string('status', 20)->default('todo');
        $t->string('owner', 60)->nullable();
        $t->json('blocked_by')->nullable();
        $t->string('acceptance_ref', 500)->nullable();
        $t->timestamp('started_at')->nullable();
        $t->timestamp('completed_at')->nullable();
        $t->timestamps();
    });
    Schema::create('mcp_task_events', function ($t) {
        $t->bigIncrements('id');
        $t->string('task_id', 40);
        $t->string('event_type', 40);
        $t->string('from_value', 255)->nullable();
        $t->string('to_value', 255)->nullable();
        $t->string('author', 60)->nullable();
        $t->text('note')->nullable();
        $t->timestamp('created_at')->nullable();
        $t->timestamp('updated_at')->nullable();
    });
});

afterEach(function () {
    if (config('database.default') !== 'sqlite') {
        return; // era-sqlite: não dropar tabela compartilhada no MySQL persistente (US-GOV-021)
    }
    Schema::dropIfExists('mcp_tasks');
    Schema::dropIfExists('mcp_task_events');
});

function seedTask(string $id, string $status = 'todo', ?string $ref = null): void
{
    DB::table('mcp_tasks')->insert([
        'task_id' => $id,
        'module' => 'Governance',
        'title' => 'T',
        'status' => $status,
        'acceptance_ref' => $ref,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('aceita acceptance_ref no update via service', function () {
    seedTask('US-GOV-099');

    app(TaskCrudService::class)->update('US-GOV-099', ['acceptance_ref' => 'PR #9999'], 'wagner');

    expect(DB::table('mcp_tasks')->where('task_id', 'US-GOV-099')->value('acceptance_ref'))->toBe('PR #9999');
})->group('acceptance-ref', 'ci');

it('grava acceptance_ref junto com status done + carimba completed_at', function () {
    seedTask('US-GOV-099', 'review'); // review→done é legal na FSM (A1+A8); todo→done agora é barrado

    app(TaskCrudService::class)->update('US-GOV-099', ['status' => 'done', 'acceptance_ref' => 'commit abc123'], 'wagner');

    $row = DB::table('mcp_tasks')->where('task_id', 'US-GOV-099')->first();
    expect($row->acceptance_ref)->toBe('commit abc123')
        ->and($row->completed_at)->not->toBeNull();
})->group('acceptance-ref', 'ci');

it('done SEM acceptance_ref registra evento de aviso (soft, nao throw)', function () {
    seedTask('US-GOV-099', 'review'); // review→done é legal na FSM (A1+A8); todo→done agora é barrado

    app(TaskCrudService::class)->update('US-GOV-099', ['status' => 'done'], 'wagner'); // não lança

    $avisos = McpTaskEvent::where('task_id', 'US-GOV-099')
        ->where('note', 'like', '%SEM acceptance_ref%')->count();
    expect($avisos)->toBe(1);
})->group('acceptance-ref', 'ci');

it('done COM acceptance_ref preexistente nao gera aviso', function () {
    seedTask('US-GOV-099', 'review', 'PR #1'); // review→done é legal na FSM (A1+A8)

    app(TaskCrudService::class)->update('US-GOV-099', ['status' => 'done'], 'wagner');

    $avisos = McpTaskEvent::where('task_id', 'US-GOV-099')
        ->where('note', 'like', '%SEM acceptance_ref%')->count();
    expect($avisos)->toBe(0);
})->group('acceptance-ref', 'ci');

it('campo nao-whitelisted continua bloqueado (whitelist nao virou wildcard)', function () {
    seedTask('US-GOV-099');

    expect(fn () => app(TaskCrudService::class)->update('US-GOV-099', ['foo' => 'x'], 'wagner'))
        ->toThrow(RuntimeException::class);
})->group('acceptance-ref', 'ci');
