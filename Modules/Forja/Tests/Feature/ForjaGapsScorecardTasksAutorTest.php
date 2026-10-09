<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Entities\Mcp\McpTask;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * UC-TSK-07 · o autor do movimento na trilha é o usuário LOGADO (Tier 0 — thread 08 do
 * playbook Forja, achado do scorecard da thread 04).
 *
 * Antes: `Tasks/Index.tsx` mandava `author: 'wagner'` fixo e o
 * `TasksAdminController@updateStatus` gravava o que viesse no body — todo movimento em
 * `mcp_task_events` aparecia como do Wagner, e qualquer um forjava o nome. A trilha é
 * append-only (triggers de imutabilidade): autoria falsa lá não se conserta depois.
 *
 * Contrato: `Tasks/Index.casos.md` UC-TSK-07 (charter: "registra `mcp_task_events`" +
 * Atividade real no drawer). `mcp_tasks` é repo-wide POR DESIGN (ADR 0070) — o tenant 98
 * (ADR 0358) só existe porque a stack de rotas exige sessão de business. NUNCA biz=4.
 */

const GAPS_TSK_PERMISSION = 'jana.mcp.usage.all';

function gapsTskExigeMysql(): void
{
    if (DB::connection()->getDriverName() === 'sqlite') {
        test()->markTestSkipped('Stack UltimatePOS exige MySQL — veredito só na lane forja-pest.yml.');
    }
    if (! Schema::hasTable('users') || ! Schema::hasTable('mcp_tasks') || ! Schema::hasTable('mcp_task_events')) {
        test()->markTestSkipped('Schema ausente (users/mcp_tasks/mcp_task_events).');
    }
}

function gapsTskUsuario(): User
{
    gapsTskExigeMysql();

    try {
        $business = test()->seededTenant(); // biz=98 — NUNCA biz=4
    } catch (\Throwable $e) {
        test()->markTestSkipped('Tenant canônico ausente: '.$e->getMessage());
    }

    $user = test()->usuarioComPermissoes([GAPS_TSK_PERMISSION], $business);

    session([
        'user.business_id' => $business->id,
        'business.id'      => $business->id,
        'user.id'          => $user->id,
    ]);

    return $user;
}

function gapsTskTask(string $sufixo): McpTask
{
    $t = new McpTask();
    $t->task_id = 'GAPS98-'.$sufixo;
    $t->identifier = 'GAPS98-'.$sufixo;
    $t->title = 'Fixture autoria thread 08 '.$sufixo;
    $t->module = 'GAPS98';
    $t->status = 'todo';
    $t->type = 'story';
    $t->priority = 'p2';
    $t->save();

    return $t;
}

function gapsTskAutorEsperado(User $u): string
{
    return mb_substr((string) ($u->username ?: 'user#'.$u->id), 0, 60);
}

it('UC-TSK-07 · usuário B move a tarefa e o evento sai com autor B, mesmo com author forjado no body', function () {
    $a = gapsTskUsuario();
    $b = test()->usuarioComPermissoes([GAPS_TSK_PERMISSION], test()->seededTenant());

    // Controle: os dois usuários têm de ser distinguíveis, senão "autor = B" não prova nada.
    expect(gapsTskAutorEsperado($b))->not->toBe(gapsTskAutorEsperado($a));

    $task = gapsTskTask('FORJA');

    // O body carrega o autor de OUTRA pessoa — exatamente o que a tela fazia com 'wagner'.
    $this->actingAs($b)
        ->patchJson("/team-mcp/tasks/{$task->task_id}/status", [
            'status' => 'doing',
            'author' => gapsTskAutorEsperado($a),
        ])
        ->assertStatus(200);

    $autores = DB::table('mcp_task_events')
        ->where('task_id', $task->task_id)
        ->where('to_value', 'doing')
        ->pluck('author')
        ->all();

    // Âncora positiva: o PATCH chegou ao código e gravou o evento (não é verde por vácuo).
    expect($autores)->not->toBeEmpty();
    expect(array_values(array_unique($autores)))->toBe([gapsTskAutorEsperado($b)]);
});

it('UC-TSK-07 · sem author no body, o evento NÃO cai no default "wagner"', function () {
    $b = gapsTskUsuario();
    $task = gapsTskTask('DEFAULT');

    $this->actingAs($b)
        ->patchJson("/team-mcp/tasks/{$task->task_id}/status", ['status' => 'doing'])
        ->assertStatus(200);

    $autores = DB::table('mcp_task_events')
        ->where('task_id', $task->task_id)
        ->where('to_value', 'doing')
        ->pluck('author')
        ->all();

    expect($autores)->not->toBeEmpty();
    expect(array_values(array_unique($autores)))->toBe([gapsTskAutorEsperado($b)]);
});

it('UC-TSK-07 · a tela não manda mais autor fixo no PATCH de status', function () {
    $tsx = file_get_contents(base_path('Modules/Forja/Resources/js/Pages/team-mcp/Tasks/Index.tsx'));

    // Controle positivo: o PATCH de status ainda existe no arquivo (senão o assert abaixo é vácuo).
    expect(str_contains($tsx, '/status`'))->toBeTrue();
    expect(str_contains($tsx, "author: 'wagner'"))->toBeFalse();
});
