<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Entities\Mcp\McpTaskEvent;
use Modules\Jana\Services\TaskRegistry\TaskCrudService;

uses(Tests\TestCase::class);

/**
 * Item A1+A8 (SDD Leva 2) — FSM mcp_tasks: barra o teleport todo→done.
 *
 * A matriz de transições (McpTask::TRANSITIONS) espelha o workflow default semeado
 * em McpDefaultsSeeder e ANTES tinha ZERO leitores — qualquer salto era aceito. O
 * chokepoint applyLockedUpdate agora chama McpTask::canTransition() e lança
 * RuntimeException numa transição ilegal; como roda dentro de DB::transaction, a
 * escrita parcial (status + evento status_changed) REVERTE — esta é a mordida.
 *
 * era-sqlite sintético (mcp_tasks + mcp_task_events) + activitylog OFF (McpTask usa
 * LogsActivity). Mesmo molde de TaskUpdateAtomicTest. markTestSkipped se driver !=
 * sqlite (a matriz é unit-pura, mas o teste de rollback depende da lane sqlite).
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
    if (config('database.default') !== 'sqlite') {
        return; // era-sqlite: não dropar tabela compartilhada no MySQL persistente (US-GOV-021)
    }
    Schema::dropIfExists('mcp_tasks');
    Schema::dropIfExists('mcp_task_events');
});

function seedFsmTask(string $id, string $status): void
{
    DB::table('mcp_tasks')->insert([
        'task_id' => $id,
        'module' => 'Governance',
        'title' => 'T',
        'status' => $status,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('todo→done (teleport) lança RuntimeException E reverte — status fica todo, 0 evento status_changed', function () {
    seedFsmTask('US-GOV-A1', 'todo');

    expect(fn () => app(TaskCrudService::class)->update('US-GOV-A1', ['status' => 'done'], 'wagner'))
        ->toThrow(RuntimeException::class);

    // SEM o guard, o teleport persistiria status='done' + evento status_changed.
    // COM o guard, o throw dentro da DB::transaction reverte tudo — esta é a mordida.
    expect(DB::table('mcp_tasks')->where('task_id', 'US-GOV-A1')->value('status'))->toBe('todo')
        ->and(McpTaskEvent::where('task_id', 'US-GOV-A1')->where('event_type', 'status_changed')->count())->toBe(0);
})->group('atomic-update', 'ci');

it('todo→doing é transição legal — persiste e popula started_at', function () {
    seedFsmTask('US-GOV-A2', 'todo');

    $r = app(TaskCrudService::class)->update('US-GOV-A2', ['status' => 'doing'], 'wagner');

    expect($r['task']->status)->toBe('doing')
        ->and(DB::table('mcp_tasks')->where('task_id', 'US-GOV-A2')->value('status'))->toBe('doing')
        ->and(DB::table('mcp_tasks')->where('task_id', 'US-GOV-A2')->value('started_at'))->not->toBeNull();
})->group('atomic-update', 'ci');

it('review→done é transição legal — o caminho canônico de fechamento passa', function () {
    seedFsmTask('US-GOV-A3', 'review');

    $r = app(TaskCrudService::class)->update('US-GOV-A3', ['status' => 'done'], 'wagner');

    expect($r['task']->status)->toBe('done')
        ->and(DB::table('mcp_tasks')->where('task_id', 'US-GOV-A3')->value('status'))->toBe('done')
        ->and(DB::table('mcp_tasks')->where('task_id', 'US-GOV-A3')->value('completed_at'))->not->toBeNull();
})->group('atomic-update', 'ci');

it('done→review é transição legal — reabrir uma task concluída passa', function () {
    seedFsmTask('US-GOV-A4', 'done');

    $r = app(TaskCrudService::class)->update('US-GOV-A4', ['status' => 'review'], 'wagner');

    expect($r['task']->status)->toBe('review')
        ->and(DB::table('mcp_tasks')->where('task_id', 'US-GOV-A4')->value('status'))->toBe('review');
})->group('atomic-update', 'ci');
