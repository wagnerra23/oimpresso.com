<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Forja\Services\ForjaAprovacoesService;
use Modules\Jana\Entities\Mcp\McpTask;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Mesa de Aprovações — a superfície do funil de admissão (ADR 0368).
 *
 * O que estes casos defendem, e por que cada um existe:
 *
 *   1. A fila mostra SÓ o que espera decisão humana (`pending_approval`) — se
 *      vazar `blocked`, a mesa vira o proxy velho que a ADR 0368 §3 aposentou
 *      justamente por misturar "espera por alguém" com "travado por dependência".
 *   2. A ordem é por espera CRESCENTE. É a regra que faz a fila envelhecer à
 *      vista; ordenar por prioridade esconderia o p3 de três dias.
 *   3. Recusar SEM motivo é rejeitado E NÃO MUDA O ESTADO (ADR 0368 §5).
 *   4. As decisões oferecidas DERIVAM do FSM — não são lista paralela.
 *
 * ⚠️ Pré-condição anti-vácuo (lápide §5 2026-07-24): o caso 3 afirma que o
 * estado foi PRESERVADO. Um teste assim passa por engano quando a operação nem
 * acontece — mede não-execução e chama de preservação. Por isso ele exige, no
 * mesmo caso, que a recusa COM motivo funcione: prova que o caminho de escrita
 * está vivo e que o 422 veio da trava, não de um controller inerte.
 *
 * Stack UltimatePOS + `can:jana.mcp.usage.all` (construtor do AprovacoesController)
 * → exige schema MySQL real; em sqlite :memory: pula gracioso, igual
 * ForjaRoutesSmokeTest. `DatabaseTransactions` porque os casos concedem
 * permission e criam tasks — sem rollback fica resíduo no banco da lane.
 *
 * NUNCA biz=4 (ROTA LIVRE prod) — ADR 0101, biz=1 canônico via seededTenant().
 *
 * @see Modules\Forja\Http\Controllers\AprovacoesController
 * @see Modules\Forja\Services\ForjaAprovacoesService
 * @see memory/decisions/0368-funil-admissao-feature-pesquisa-propoe-w-admite.md
 */

const MESA_PERMISSION = 'jana.mcp.usage.all';

/** Guard: a stack UltimatePOS não sobe sem schema MySQL real. */
function mesaExigeSchemaMysql(): void
{
    if (DB::connection()->getDriverName() === 'sqlite') {
        test()->markTestSkipped(
            'SQLite-incompatível: middlewares UltimatePOS exigem schema MySQL '.
            'com business/users/permissions (ADR 0101).'
        );
    }
    if (! Schema::hasTable('users') || ! Schema::hasTable('mcp_tasks')) {
        test()->markTestSkipped('Schema ausente — rode com DB_CONNECTION=mysql.');
    }
}

/** Usuário autenticável COM a permission, no tenant canônico biz=1. */
function mesaBootstrap(): User
{
    mesaExigeSchemaMysql();

    try {
        $business = test()->seededTenant(); // biz=1 canônico (ADR 0101)
    } catch (\Throwable $e) {
        test()->markTestSkipped('Tenant canônico ausente: '.$e->getMessage());
    }

    session([
        'user.business_id' => $business->id,
        'business.id'      => $business->id,
    ]);

    $user = User::where('business_id', $business->id)
        ->where('user_type', '!=', 'user_customer')
        ->first();
    if (! $user) {
        test()->markTestSkipped('Sem user não-customer no business pra autenticar.');
    }

    Permission::findOrCreate(MESA_PERMISSION, 'web');
    $user->givePermissionTo(MESA_PERMISSION);
    $user->forgetCachedPermissions();

    session(['user.id' => $user->id]);

    return $user;
}

/**
 * Cria uma task de fixture com o status pedido.
 *
 * Escreve direto no Eloquent DE PROPÓSITO: é montagem de cenário, não o caminho
 * sob teste. O caminho sob teste (a decisão) passa pelo TaskCrudService, que é
 * onde FSM e recusa-com-motivo moram.
 */
function mesaTask(string $status, string $sufixo, ?\DateTimeInterface $criadaEm = null): McpTask
{
    $task = new McpTask();
    $task->task_id = 'MESA-TEST-'.$sufixo;
    $task->identifier = 'MESA-TEST-'.$sufixo;
    $task->title = 'Fixture da mesa '.$sufixo;
    $task->module = 'Forja';
    $task->status = $status;
    $task->type = 'story';
    $task->priority = 'p2';
    $task->save();

    if ($criadaEm !== null) {
        // `created_at` é gerenciado pelo Eloquent — para controlar a ORDEM da fila
        // precisamos gravá-lo depois, sem tocar em updated_at.
        McpTask::where('task_id', $task->task_id)->update(['created_at' => $criadaEm]);
        $task->refresh();
    }

    return $task;
}

it('UC-APROV-01 — anônimo é barrado e autenticado sem permissão leva 403', function () {
    mesaExigeSchemaMysql();

    try {
        $business = test()->seededTenant(); // biz=1 canônico (ADR 0101)
    } catch (\Throwable $e) {
        test()->markTestSkipped('Tenant canônico ausente: '.$e->getMessage());
    }

    session(['user.business_id' => $business->id, 'business.id' => $business->id]);

    // Anônimo: o `auth` da stack UltimatePOS responde 302 (login) ou 401/403.
    // `assertStatus` só aceita um int, e os três são aceitáveis aqui — então a
    // asserção é sobre o conjunto, não sobre um código específico.
    $anon = $this->post('/forja/aprovacoes/QUALQUER/decidir', ['destino' => 'todo']);
    expect([302, 401, 403])->toContain($anon->getStatusCode());

    // O usuário PRECISA ser não-admin: `Gate::before` (AuthServiceProvider) devolve
    // true pra QUALQUER ability de quem tem `Admin#{business_id}`. Com admin, este
    // 403 aconteceria mesmo se o `can:` fosse removido do controller — o caso seria
    // decorativo. Mesma trava do ForjaRoutesSmokeTest/JanaAccessGateTest.
    $user = User::where('business_id', $business->id)
        ->where('user_type', '!=', 'user_customer')
        ->get()
        ->first(static fn (User $u): bool => ! $u->hasRole('Admin#'.$business->id));

    if (! $user) {
        test()->markTestSkipped(
            "Sem usuário NÃO-admin em business_id={$business->id} — com admin o ".
            'Gate::before libera qualquer ability e o 403 seria falso-verde.'
        );
    }

    Permission::findOrCreate(MESA_PERMISSION, 'web');
    $user->revokePermissionTo(MESA_PERMISSION);
    $user->forgetCachedPermissions();
    session(['user.id' => $user->id]);

    $this->actingAs($user)->get('/forja/aprovacoes')->assertStatus(403);
});

it('UC-APROV-02 — a fila traz só o que espera decisão humana; blocked NÃO entra', function () {
    mesaBootstrap();

    $esperando = mesaTask(McpTask::AWAITING_HUMAN, 'ESPERA');
    $travada = mesaTask('blocked', 'TRAVADA');

    $ids = array_column(app(ForjaAprovacoesService::class)->fila(), 'task_id');

    expect($ids)->toContain($esperando->task_id);

    // O coração do caso: `blocked` é trava TÉCNICA (ADR 0368 §3). Se ele aparecer
    // aqui, a mesa reconstruiu o proxy velho e volta a misturar as duas coisas.
    expect($ids)->not->toContain($travada->task_id);
});

it('UC-APROV-03 — a fila vem por espera crescente; o mais antigo primeiro', function () {
    mesaBootstrap();

    // Ordem de criação invertida em relação à ordem esperada: se o service
    // ordenasse por inserção (ou não ordenasse), o caso cairia.
    $novo = mesaTask(McpTask::AWAITING_HUMAN, 'NOVO', now()->subMinutes(5));
    $antigo = mesaTask(McpTask::AWAITING_HUMAN, 'ANTIGO', now()->subDays(3));

    $ids = array_column(app(ForjaAprovacoesService::class)->fila(), 'task_id');
    $posAntigo = array_search($antigo->task_id, $ids, true);
    $posNovo = array_search($novo->task_id, $ids, true);

    expect($posAntigo)->not->toBeFalse();
    expect($posNovo)->not->toBeFalse();
    expect($posAntigo)->toBeLessThan($posNovo);
});

it('UC-APROV-04 — marca a espera longa como urgente e a curta como ok', function () {
    mesaBootstrap();

    mesaTask(McpTask::AWAITING_HUMAN, 'RECENTE', now()->subMinutes(2));
    mesaTask(McpTask::AWAITING_HUMAN, 'VELHA', now()->subHours(5));

    $porId = collect(app(ForjaAprovacoesService::class)->fila())->keyBy('task_id');

    expect($porId['MESA-TEST-RECENTE']['sla'])->toBe('ok');
    expect($porId['MESA-TEST-VELHA']['sla'])->toBe('urgente');
});

it('UC-APROV-05 — oferece exatamente as decisões que o FSM permite, nunca uma lista paralela', function () {
    $oferecidos = array_column(app(ForjaAprovacoesService::class)->decisoesPossiveis(), 'status');

    // Derivação, não cópia: se alguém alterar TRANSITIONS, este caso acompanha
    // sozinho — e uma lista hardcoded no service passaria a divergir aqui.
    sort($oferecidos);
    $doFsm = McpTask::TRANSITIONS[McpTask::AWAITING_HUMAN];
    sort($doFsm);

    expect($oferecidos)->toBe($doFsm);
});

it('UC-APROV-06 — admitir move pending_approval para todo pela rota', function () {
    $user = mesaBootstrap();
    $task = mesaTask(McpTask::AWAITING_HUMAN, 'ADMITE');

    $this->actingAs($user)
        ->post("/forja/aprovacoes/{$task->task_id}/decidir", ['destino' => 'todo'])
        ->assertOk();

    expect(McpTask::where('task_id', $task->task_id)->value('status'))->toBe('todo');
});

it('UC-APROV-07 — recusa SEM motivo é barrada, o estado NÃO muda, e o caminho de escrita está vivo', function () {
    $user = mesaBootstrap();
    $task = mesaTask(McpTask::AWAITING_HUMAN, 'RECUSA');

    // (a) sem motivo → 422 e estado preservado (ADR 0368 §5)
    $this->actingAs($user)
        ->post("/forja/aprovacoes/{$task->task_id}/decidir", ['destino' => 'cancelled'])
        ->assertStatus(422);

    expect(McpTask::where('task_id', $task->task_id)->value('status'))
        ->toBe(McpTask::AWAITING_HUMAN);

    // (b) ANTI-VÁCUO: com motivo, a MESMA rota persiste. Sem esta metade, (a)
    // passaria igual se o controller não fizesse nada — mediria não-execução e
    // chamaria de preservação (lápide §5 2026-07-24).
    $this->actingAs($user)
        ->post("/forja/aprovacoes/{$task->task_id}/decidir", [
            'destino' => 'cancelled',
            'motivo'  => 'Fora de escopo do cycle — [W] 2026-08-08.',
        ])
        ->assertOk();

    $fresca = McpTask::where('task_id', $task->task_id)->first();
    expect($fresca->status)->toBe('cancelled');

    $custom = is_array($fresca->custom_fields) ? $fresca->custom_fields : [];
    expect($custom[McpTask::REFUSAL_REASON_KEY] ?? '')->not->toBe('');
});

it('UC-APROV-08 — item que já saiu da fila responde 409 em vez de decidir de novo', function () {
    $user = mesaBootstrap();
    $task = mesaTask('todo', 'JASAIU');

    $this->actingAs($user)
        ->post("/forja/aprovacoes/{$task->task_id}/decidir", ['destino' => 'todo'])
        ->assertStatus(409);
});

it('UC-APROV-05 — destino fora do FSM é rejeitado', function () {
    $user = mesaBootstrap();
    $task = mesaTask(McpTask::AWAITING_HUMAN, 'DESTINO');

    // `done` não está em TRANSITIONS['pending_approval'] — admitir e concluir num
    // passo só pularia o trabalho inteiro.
    $this->actingAs($user)
        ->post("/forja/aprovacoes/{$task->task_id}/decidir", ['destino' => 'done'])
        ->assertStatus(422);

    expect(McpTask::where('task_id', $task->task_id)->value('status'))
        ->toBe(McpTask::AWAITING_HUMAN);
});
