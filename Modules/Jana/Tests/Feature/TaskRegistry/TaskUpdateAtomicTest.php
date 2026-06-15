<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Entities\Mcp\McpTaskEvent;
use Modules\Jana\Services\TaskRegistry\TaskCrudService;

uses(Tests\TestCase::class);

/**
 * Fase 2b (ADR 0278) — updateInner ATÔMICO (DB::transaction + lockForUpdate).
 *
 * Fecha o lost-update — a causa-raiz literal do "a lista fura": 2 agentes escrevendo
 * a mesma task com last-write-wins silencioso. lockForUpdate serializa writers no
 * MySQL; aqui (sqlite :memory:, single-thread) o valor do teste é provar (a) que o
 * caminho atômico é SQLITE-SAFE e funcional, e (b) que a transação REVERTE escritas
 * parciais. A prevenção de corrida concorrente é propriedade do MySQL, garantida por
 * construção (transação + lock pessimista) — não unit-testável em sqlite single-thread.
 *
 * era-sqlite sintético + activitylog OFF (McpTask usa LogsActivity).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('era-sqlite: schema sintético (floor SDD).');
    }
    config(['activitylog.enabled' => false]);

    Schema::dropIfExists('mcp_tasks');
    Schema::dropIfExists('mcp_task_events');

    Schema::create('mcp_tasks', function ($t) {
        $t->bigIncrements('id');
        $t->string('task_id', 40)->unique();
        $t->string('module', 60)->nullable();
        $t->string('title', 255)->nullable();
        $t->string('status', 20)->default('todo');
        $t->string('owner', 60)->nullable();
        $t->string('sprint', 40)->nullable();
        $t->string('priority', 8)->nullable();
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
    Schema::dropIfExists('mcp_tasks');
    Schema::dropIfExists('mcp_task_events');
});

function seedAtomicTask(string $id): void
{
    DB::table('mcp_tasks')->insert([
        'task_id' => $id,
        'module' => 'Governance',
        'title' => 'T',
        'status' => 'todo',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('update sob transação+lock persiste e loga (sqlite-safe — lockForUpdate não quebra a lane)', function () {
    seedAtomicTask('US-GOV-099');

    $r = app(TaskCrudService::class)->update('US-GOV-099', ['status' => 'doing'], 'wagner');

    expect($r['task']->status)->toBe('doing')
        ->and(DB::table('mcp_tasks')->where('task_id', 'US-GOV-099')->value('status'))->toBe('doing')
        ->and(DB::table('mcp_tasks')->where('task_id', 'US-GOV-099')->value('started_at'))->not->toBeNull()
        ->and(McpTaskEvent::where('task_id', 'US-GOV-099')->where('event_type', 'status_changed')->count())->toBe(1);
})->group('atomic-update', 'ci');

it('update multi-campo commita o conjunto inteiro (atomicidade da transação)', function () {
    seedAtomicTask('US-GOV-099');

    // 3 campos sem side-effect externo (owner dispararia maybeNotifyAssignment →
    // query em `users`, ausente do schema sintético). status/priority/sprint provam
    // a atomicidade do commit do conjunto sem dependência de outras tabelas.
    app(TaskCrudService::class)->update('US-GOV-099', ['status' => 'doing', 'priority' => 'p1', 'sprint' => 'S1'], 'wagner');

    $row = DB::table('mcp_tasks')->where('task_id', 'US-GOV-099')->first();
    expect($row->status)->toBe('doing')
        ->and($row->priority)->toBe('p1')
        ->and($row->sprint)->toBe('S1');
})->group('atomic-update', 'ci');

it('campo inválido reverte a transação inteira — sem escrita parcial (rollback real)', function () {
    seedAtomicTask('US-GOV-099');

    // Ordem: 'status' é processado (loga evento status_changed em mcp_task_events
    // ANTES do save) e então 'xpto' lança RuntimeException (fora da whitelist).
    expect(fn () => app(TaskCrudService::class)->update('US-GOV-099', ['status' => 'doing', 'xpto' => 'y'], 'wagner'))
        ->toThrow(RuntimeException::class);

    // SEM a transação, o evento status_changed teria persistido (escrita parcial).
    // COM a transação, ele reverte junto — ESTA é a prova do wrapper atômico.
    expect(McpTaskEvent::where('task_id', 'US-GOV-099')->where('event_type', 'status_changed')->count())->toBe(0)
        ->and(DB::table('mcp_tasks')->where('task_id', 'US-GOV-099')->value('status'))->toBe('todo');
})->group('atomic-update', 'ci');
