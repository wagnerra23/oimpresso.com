<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Entities\Mcp\McpTask;
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
        $t->text('custom_fields')->nullable(); // ADR 0368 §5 — motivo da recusa mora aqui
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

function seedFsmTask(string $id, string $status, ?array $customFields = null): void
{
    DB::table('mcp_tasks')->insert([
        'task_id' => $id,
        'module' => 'Governance',
        'title' => 'T',
        'status' => $status,
        'custom_fields' => $customFields === null ? null : json_encode($customFields),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/** Status persistido — o que sobrou DEPOIS do rollback, não o que o objeto em memória diz. */
function fsmStatus(string $id): ?string
{
    return DB::table('mcp_tasks')->where('task_id', $id)->value('status');
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

/*
|--------------------------------------------------------------------------
| FUNIL DE ADMISSÃO — ADR 0368 §3/§5
|--------------------------------------------------------------------------
| O contrato testado aqui é o da ADR, não o do código: a candidata ESPERA em
| pending_approval e tem DUAS saídas — `admitida` (volta ao fluxo normal) e
| `recusada` (cancelled), esta última COM MOTIVO OBRIGATÓRIO.
|
| Antes destes testes, `pending_approval` estava em McpTask::STATUSES e em
| AWAITING_HUMAN mas fora de TRANSITIONS — e canTransition() é fail-closed.
| O estado era, pelo chokepoint, inalcançável E inescapável: os 4 casos de
| transição legal abaixo falham em origin/main @ 13450bc8338.
*/

it('backlog→pending_approval é legal — a candidata parqueada vai a voto', function () {
    seedFsmTask('US-GOV-F1', 'backlog');

    $r = app(TaskCrudService::class)->update('US-GOV-F1', ['status' => McpTask::AWAITING_HUMAN], 'wagner');

    expect($r['task']->status)->toBe(McpTask::AWAITING_HUMAN)
        ->and(fsmStatus('US-GOV-F1'))->toBe(McpTask::AWAITING_HUMAN);
})->group('atomic-update', 'ci');

it('pending_approval→todo é legal — [W] ADMITE e a candidata entra no fluxo', function () {
    seedFsmTask('US-GOV-F2', McpTask::AWAITING_HUMAN);

    $r = app(TaskCrudService::class)->update('US-GOV-F2', ['status' => 'todo'], 'wagner');

    expect($r['task']->status)->toBe('todo')
        ->and(fsmStatus('US-GOV-F2'))->toBe('todo');
})->group('atomic-update', 'ci');

it('pending_approval→backlog é legal — admitida, mas parqueada no backlog', function () {
    seedFsmTask('US-GOV-F3', McpTask::AWAITING_HUMAN);

    app(TaskCrudService::class)->update('US-GOV-F3', ['status' => 'backlog'], 'wagner');

    expect(fsmStatus('US-GOV-F3'))->toBe('backlog');
})->group('atomic-update', 'ci');

it('pending_approval→done (teleporte) é ILEGAL — reverte, candidata segue esperando', function () {
    seedFsmTask('US-GOV-F4', McpTask::AWAITING_HUMAN);

    // Pular a decisão humana e cair direto em `done` é o teleporte que o funil existe pra
    // impedir: a feature entraria em produção sem NINGUÉM ter admitido.
    expect(fn () => app(TaskCrudService::class)->update('US-GOV-F4', ['status' => 'done'], 'wagner'))
        ->toThrow(RuntimeException::class);

    expect(fsmStatus('US-GOV-F4'))->toBe(McpTask::AWAITING_HUMAN)
        ->and(McpTaskEvent::where('task_id', 'US-GOV-F4')->where('event_type', 'status_changed')->count())->toBe(0);
})->group('atomic-update', 'ci');

it('status fora da FSM lança RuntimeException (não TypeError) e diz que não há saída', function () {
    // Fail-closed com mensagem utilizável: sem o `?? []` em applyLockedUpdate, montar a lista
    // de "permitidas" pra um estado ausente da matriz estourava TypeError no implode(null).
    seedFsmTask('US-GOV-F5', 'estado_zumbi');

    expect(fn () => app(TaskCrudService::class)->update('US-GOV-F5', ['status' => 'todo'], 'wagner'))
        ->toThrow(RuntimeException::class, 'nenhuma — estado fora da FSM');

    expect(fsmStatus('US-GOV-F5'))->toBe('estado_zumbi');
})->group('atomic-update', 'ci');

it('RECUSA SEM MOTIVO é barrada — reverte e a candidata continua esperando', function () {
    seedFsmTask('US-GOV-F6', McpTask::AWAITING_HUMAN);

    // ADR 0368 §5: recusa sem motivo registrado é o que faz a mesma capacidade voltar daqui a
    // três meses e consumir a decisão de novo.
    expect(fn () => app(TaskCrudService::class)->update('US-GOV-F6', ['status' => 'cancelled'], 'wagner'))
        ->toThrow(RuntimeException::class, 'Recusa sem motivo');

    expect(fsmStatus('US-GOV-F6'))->toBe(McpTask::AWAITING_HUMAN)
        ->and(McpTaskEvent::where('task_id', 'US-GOV-F6')->where('event_type', 'status_changed')->count())->toBe(0);
})->group('atomic-update', 'ci');

it('RECUSA COM MOTIVO na mesma chamada passa — grava cancelled + o motivo', function () {
    seedFsmTask('US-GOV-F7', McpTask::AWAITING_HUMAN);

    $r = app(TaskCrudService::class)->update('US-GOV-F7', [
        'status' => 'cancelled',
        'custom_fields' => [McpTask::REFUSAL_REASON_KEY => 'Premissa do concorrente não vale aqui: nosso preço é digitado por célula.'],
    ], 'wagner');

    expect($r['task']->status)->toBe('cancelled')
        ->and(fsmStatus('US-GOV-F7'))->toBe('cancelled')
        ->and($r['task']->custom_fields[McpTask::REFUSAL_REASON_KEY])->toContain('digitado por célula')
        ->and(DB::table('mcp_tasks')->where('task_id', 'US-GOV-F7')->value('completed_at'))->not->toBeNull();
})->group('atomic-update', 'ci');

it('RECUSA com motivo JÁ gravado na task passa — o registro pode vir de chamada anterior', function () {
    seedFsmTask('US-GOV-F8', McpTask::AWAITING_HUMAN, [McpTask::REFUSAL_REASON_KEY => 'Sem sinal de cliente (ADR 0105).']);

    app(TaskCrudService::class)->update('US-GOV-F8', ['status' => 'cancelled'], 'wagner');

    expect(fsmStatus('US-GOV-F8'))->toBe('cancelled');
})->group('atomic-update', 'ci');

it('motivo em BRANCO não conta como motivo — espaço não é justificativa', function () {
    seedFsmTask('US-GOV-F9', McpTask::AWAITING_HUMAN, [McpTask::REFUSAL_REASON_KEY => '   ']);

    expect(fn () => app(TaskCrudService::class)->update('US-GOV-F9', ['status' => 'cancelled'], 'wagner'))
        ->toThrow(RuntimeException::class, 'Recusa sem motivo');

    expect(fsmStatus('US-GOV-F9'))->toBe(McpTask::AWAITING_HUMAN);
})->group('atomic-update', 'ci');

it('custom_fields SOBRESCRITO sem o motivo é barrado — não vale apoiar-se no que se está apagando', function () {
    seedFsmTask('US-GOV-F10', McpTask::AWAITING_HUMAN, [McpTask::REFUSAL_REASON_KEY => 'motivo antigo']);

    // O update faz assignment, não merge: este payload APAGARIA o motivo. Se a verificação
    // olhasse "existe motivo em qualquer lugar", a recusa passaria e a task terminaria em
    // cancelled SEM motivo — exatamente o buraco que a ADR fecha.
    expect(fn () => app(TaskCrudService::class)->update('US-GOV-F10', [
        'status' => 'cancelled',
        'custom_fields' => ['parent_plan' => 'algum-plano'],
    ], 'wagner'))->toThrow(RuntimeException::class, 'Recusa sem motivo');

    expect(fsmStatus('US-GOV-F10'))->toBe(McpTask::AWAITING_HUMAN);
})->group('atomic-update', 'ci');

it('todo→cancelled NÃO exige motivo — a trava é da RECUSA de candidata, não de todo cancelamento', function () {
    // Controle negativo: sem este caso, a verificação poderia estar barrando qualquer
    // cancelamento e os testes de recusa passariam pelo motivo errado.
    seedFsmTask('US-GOV-F11', 'todo');

    app(TaskCrudService::class)->update('US-GOV-F11', ['status' => 'cancelled'], 'wagner');

    expect(fsmStatus('US-GOV-F11'))->toBe('cancelled');
})->group('atomic-update', 'ci');
