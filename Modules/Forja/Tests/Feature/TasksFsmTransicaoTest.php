<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Entities\Mcp\McpTask;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Tarefas · transição proibida pelo FSM responde 422 (UC-TSK-08).
 *
 * Contrato: decisão [W] D13 (2026-10-07, playbook Forja thread 14) — "responder 422" —
 * registrada em `Modules/Forja/Resources/js/Pages/team-mcp/Tasks/Index.casos.md`.
 * O FSM é `McpTask::TRANSITIONS` (ADR 0070); o controller só confirma.
 *
 * Antes do conserto, `TasksAdminController::updateStatus` devolvia 404 pra QUALQUER
 * RuntimeException do TaskCrudService — inclusive "Transição ilegal todo → done" —
 * e quem arrastava no Quadro lia "task não encontrada".
 *
 * Só pernas de REQUEST: exigem a stack UltimatePOS → veredito na lane MySQL
 * `forja-pest.yml`. Em sqlite PULAM, e skip não é cobertura.
 *
 * `mcp_tasks` é repo-wide POR DESIGN (ADR 0070/0093). O tenant 98 (ADR 0358) só existe
 * porque a stack de rotas exige sessão de business. NUNCA biz=4.
 * Fixtures com prefixo `TSKFSM98-`; DatabaseTransactions — nada persiste.
 */

const TSKFSM_PERMISSION = 'jana.mcp.usage.all';

function tskFsmUsuario(): User
{
    if (DB::connection()->getDriverName() === 'sqlite') {
        test()->markTestSkipped('SQLite-incompatível: stack UltimatePOS. Veredito na lane forja-pest.yml.');
    }
    if (! Schema::hasTable('users') || ! Schema::hasTable('mcp_tasks') || ! Schema::hasTable('mcp_task_events')) {
        test()->markTestSkipped('Schema ausente (users/mcp_tasks/mcp_task_events) — rode com DB_CONNECTION=mysql.');
    }

    try {
        $business = test()->seededTenant(); // biz=98 (ADR 0358) — NUNCA biz=4
    } catch (\Throwable $e) {
        test()->markTestSkipped('Tenant canônico ausente: '.$e->getMessage());
    }

    $user = test()->usuarioComPermissoes([TSKFSM_PERMISSION], $business);

    session([
        'user.business_id' => $business->id,
        'business.id'      => $business->id,
        'user.id'          => $user->id,
    ]);

    return $user;
}

function tskFsmTask(string $sufixo, string $status): McpTask
{
    $t = new McpTask();
    $t->task_id = 'TSKFSM98-'.$sufixo;
    $t->identifier = 'TSKFSM98-'.$sufixo;
    $t->title = 'Fixture FSM 422 '.$sufixo;
    $t->module = 'TSKFSM98';
    $t->status = $status;
    $t->type = 'story';
    $t->priority = 'p2';
    $t->save();

    return $t;
}

it('UC-TSK-08 · transição proibida pelo FSM (todo → done) responde 422 com o motivo em PT-BR e nada muda', function () {
    $user = tskFsmUsuario();
    $task = tskFsmTask('PULO', 'todo');

    // Pré-condição anti-vácuo: o FSM de fato proíbe todo → done. Se a matriz mudar,
    // este caso precisa de outro par — não passa por coincidência.
    expect(McpTask::canTransition('todo', 'done'))->toBeFalse();

    $eventosAntes = DB::table('mcp_task_events')->where('task_id', $task->task_id)->count();

    $r = $this->actingAs($user)
        ->patchJson("/team-mcp/tasks/{$task->task_id}/status", ['status' => 'done']);

    // Discriminante: antes do conserto isto era 404 "task não encontrada".
    $r->assertStatus(422);
    $erro = (string) $r->json('error');
    expect(str_contains($erro, 'Transição não permitida'))->toBeTrue("motivo sem a frase esperada: {$erro}");
    expect(str_contains($erro, 'não encontrada'))->toBeFalse("422 dizendo que a task não existe: {$erro}");
    expect($r->json('de'))->toBe('todo');
    expect($r->json('para'))->toBe('done');
    expect($r->json('permitidas'))->toBe(McpTask::TRANSITIONS['todo']);

    expect(McpTask::where('task_id', $task->task_id)->value('status'))->toBe('todo');
    expect(DB::table('mcp_task_events')->where('task_id', $task->task_id)->count())->toBe($eventosAntes);
});

it('UC-TSK-08 · task que não existe continua respondendo 404', function () {
    $user = tskFsmUsuario();

    $this->actingAs($user)
        ->patchJson('/team-mcp/tasks/TSKFSM98-NAO-EXISTE/status', ['status' => 'doing'])
        ->assertStatus(404)
        ->assertJsonPath('error', 'Task não encontrada.');
});

it('UC-TSK-08 · controle: transição permitida (review → done) segue 200 e grava', function () {
    $user = tskFsmUsuario();
    $task = tskFsmTask('OK', 'review');

    expect(McpTask::canTransition('review', 'done'))->toBeTrue();

    $this->actingAs($user)
        ->patchJson("/team-mcp/tasks/{$task->task_id}/status", ['status' => 'done'])
        ->assertStatus(200)
        ->assertJsonPath('status', 'done');

    expect(McpTask::where('task_id', $task->task_id)->value('status'))->toBe('done');
});
