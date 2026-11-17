<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Compras\Jobs\RequisicaoEstoqueMinimoJob;

/**
 * D6 da auditoria de Compras (2026-11-05), lote 2.5: a agenda do módulo só roda em produção, e a
 * falha dela (ou do job que ela enfileira) vira task no `mcp_tasks`, o canal que o brief lê.
 *
 * O ambiente é conferido pelo `runsInEnvironment`, a mesma função que o `schedule:run` chama
 * (proibicoes.md §5 2026-09-05: o `schedule:list` não responde essa pergunta).
 *
 * Tenant 98 (ADR 0358).
 *
 * @covers-us US-COM-027
 */
uses(Tests\TestCase::class, DatabaseTransactions::class);

function comprasEventoDaAgenda(): Event
{
    $evento = collect(app(Schedule::class)->events())
        ->first(fn (Event $e) => str_contains((string) $e->command, 'compras:requisicao-estoque-minimo'));
    expect($evento)->not->toBeNull('compras:requisicao-estoque-minimo não está na agenda');

    return $evento;
}

it('D6 · a requisição pelo estoque mínimo só roda em produção', function () {
    $evento = comprasEventoDaAgenda();

    expect($evento->runsInEnvironment('live'))->toBeTrue()
        ->and($evento->runsInEnvironment('local'))->toBeFalse()
        ->and($evento->runsInEnvironment('testing'))->toBeFalse();
});

it('D6 · falha da agenda vira task blocked para o dono; execução sem erro não cria task', function () {
    expect(Schema::hasTable('mcp_tasks'))->toBeTrue('a lane precisa de mcp_tasks para provar o aviso');
    $taskId = 'HITL-COMPRAS-REQ-ESTMIN';
    DB::table('mcp_tasks')->where('task_id', $taskId)->delete();

    $evento = comprasEventoDaAgenda();

    // Controle: saída 0 não avisa.
    $evento->exitCode = 0;
    $evento->callAfterCallbacks(app());
    expect(DB::table('mcp_tasks')->where('task_id', $taskId)->exists())->toBeFalse();

    // Saída com erro: a task aparece no canal do brief.
    $evento->exitCode = 1;
    $evento->callAfterCallbacks(app());
    $task = DB::table('mcp_tasks')->where('task_id', $taskId)->first();
    expect($task)->not->toBeNull('a falha da agenda não virou task')
        ->and($task->status)->toBe('blocked')
        ->and($task->owner)->toBe('wagner');
});

it('D6 · o job tenta de novo e, esgotado, avisa no canal do brief', function () {
    expect(Schema::hasTable('mcp_tasks'))->toBeTrue('a lane precisa de mcp_tasks para provar o aviso');
    $biz = (int) $this->seededTenant()->id;
    $taskId = 'HITL-COMPRAS-REQ-ESTMIN-B'.$biz;
    DB::table('mcp_tasks')->where('task_id', $taskId)->delete();

    $job = new RequisicaoEstoqueMinimoJob($biz);
    expect($job->tries)->toBe(3)
        ->and($job->backoff)->toBe([60, 300])
        ->and($job->queue)->toBe('default');

    $job->failed(new RuntimeException('falha simulada'));

    $task = DB::table('mcp_tasks')->where('task_id', $taskId)->first();
    expect($task)->not->toBeNull('o failed() do job não virou task')
        ->and($task->status)->toBe('blocked')
        ->and($task->owner)->toBe('wagner');
});
