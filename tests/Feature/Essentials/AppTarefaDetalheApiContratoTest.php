<?php

declare(strict_types=1);

use App\User;
use App\Utils\ModuleUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Passport\Passport;

/**
 * API do Detalhe da tarefa do app das lojas (tela 28) — GET /api/app/tarefas/todo/{id}, só leitura.
 *
 * Contrato: memory/requisitos/AppMobile/API-CONTRATO-v1.md §3.1. Mesmo escopo da lista §3 e da
 * tela web (TodoService::scopedQueryForUser): quem não é admin vê as que criou ou que lhe foram
 * atribuídas. NÃO derivado do controller.
 *
 * Tier 0 (ADR 0093): ToDo de OUTRO business → 404. Controle positivo em par: a do próprio
 * usuário abre — senão o 404 seria verde por vácuo.
 *
 * Tenant 98 (ADR 0358), adversário 99. Usuários criados aqui, sem papel (não-admin).
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    // O guard `api` do Passport precisa das chaves mesmo com Passport::actingAs; a lane não as gera.
    if (! file_exists(storage_path('oauth-public.key'))) {
        \Illuminate\Support\Facades\Artisan::call('passport:keys', ['--force' => true]);
    }
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Requer schema MySQL UltimatePOS (ADR 0358).');
    }
    foreach (['essentials_to_dos', 'essentials_todos_users', 'essentials_todo_comments'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema ausente ({$t}).");
        }
    }

    $this->tenant = $this->seededTenant();
    $this->adversario = $this->seededSupportClientTenant();

    // Essentials no plano, sem depender do pacote semeado na lane. O resto do ModuleUtil é o real
    // (is_admin decide o escopo).
    $mu = Mockery::mock(ModuleUtil::class)->makePartial();
    $mu->shouldReceive('hasThePermissionInSubscription')->andReturn(true);
    app()->instance(ModuleUtil::class, $mu);
});

function appTarUsuario(int $businessId, string $nome): User
{
    $id = DB::table('users')->insertGetId([
        'first_name' => $nome, 'last_name' => 'Teste', 'username' => 'app_tar_' . uniqid(), 'password' => 'x',
        'business_id' => $businessId, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return User::findOrFail($id);
}

function appTarTodo(int $businessId, int $criadoPor, array $extra = []): int
{
    return (int) DB::table('essentials_to_dos')->insertGetId(array_merge([
        'business_id' => $businessId, 'user_id' => $criadoPor, 'task' => '<p>Conferir tiragem</p>',
        'task_id' => 'APP-' . uniqid(), 'date' => now()->subDays(5), 'end_date' => now()->subDay(),
        'is_completed' => 0, 'status' => 'new', 'priority' => 'high', 'created_by' => $criadoPor,
        'created_at' => now(), 'updated_at' => now(),
    ], $extra));
}

it('abre a ToDo do próprio usuário com responsável, prazo atrasado e comentários em ordem', function () {
    $eu = appTarUsuario((int) $this->tenant->id, 'Ana');
    $colega = appTarUsuario((int) $this->tenant->id, 'Bruno');
    $id = appTarTodo((int) $this->tenant->id, (int) $eu->id, ['description' => '<b>Tiragem de 500</b>']);
    DB::table('essentials_todos_users')->insert(['todo_id' => $id, 'user_id' => $colega->id]);
    foreach (['Primeiro', 'Segundo'] as $i => $texto) {
        DB::table('essentials_todo_comments')->insert([
            'task_id' => $id, 'comment_by' => $colega->id, 'comment' => "<p>{$texto}</p>",
            'created_at' => now()->subHours(2 - $i), 'updated_at' => now(),
        ]);
    }
    Passport::actingAs($eu, [], 'api');

    $r = $this->getJson("/api/app/tarefas/todo/{$id}")->assertOk();

    expect($r->json('id'))->toBe("todo:{$id}");
    expect($r->json('titulo'))->toBe('Conferir tiragem');
    expect($r->json('descricao'))->toBe('Tiragem de 500');
    expect($r->json('modulo'))->toBe('Tarefa · alta');
    expect($r->json('responsavel'))->toBe('Bruno Teste');
    expect($r->json('prazo'))->toBe(now()->subDay()->toDateString());
    expect($r->json('atrasado'))->toBeTrue();
    expect($r->json('concluida'))->toBeFalse();
    expect($r->json('checklist'))->toBe([]);
    expect($r->json('cliente'))->toBeNull();
    expect($r->json('origem'))->toBeNull();
    expect(array_column($r->json('comentarios'), 'texto'))->toBe(['Primeiro', 'Segundo']);
    expect($r->json('comentarios.0.autor'))->toBe('Bruno Teste');
    expect($r->json('comentarios.0.detalhe'))->toBeNull();
});

it('ToDo concluída abre com concluida=true e não conta como atrasada', function () {
    $eu = appTarUsuario((int) $this->tenant->id, 'Ana');
    $id = appTarTodo((int) $this->tenant->id, (int) $eu->id, ['status' => 'completed']);
    Passport::actingAs($eu, [], 'api');

    $r = $this->getJson("/api/app/tarefas/todo/{$id}")->assertOk();

    expect($r->json('concluida'))->toBeTrue();
    expect($r->json('atrasado'))->toBeFalse();
});

it('ToDo de outro usuário não atribuída a mim → 404; a minha abre (controle positivo)', function () {
    $eu = appTarUsuario((int) $this->tenant->id, 'Ana');
    $outro = appTarUsuario((int) $this->tenant->id, 'Carla');
    $minha = appTarTodo((int) $this->tenant->id, (int) $eu->id);
    $dela = appTarTodo((int) $this->tenant->id, (int) $outro->id);
    Passport::actingAs($eu, [], 'api');

    $this->getJson("/api/app/tarefas/todo/{$minha}")->assertOk();
    $this->getJson("/api/app/tarefas/todo/{$dela}")->assertNotFound()->assertJsonPath('erro', 'nao_encontrado');
});

it('Tier 0: ToDo de OUTRO business → 404, mesmo criada pelo mesmo id de usuário', function () {
    $eu = appTarUsuario((int) $this->tenant->id, 'Ana');
    $minha = appTarTodo((int) $this->tenant->id, (int) $eu->id);
    $alheia = appTarTodo((int) $this->adversario->id, (int) $eu->id);
    Passport::actingAs($eu, [], 'api');

    $this->getJson("/api/app/tarefas/todo/{$minha}")->assertOk();
    $this->getJson("/api/app/tarefas/todo/{$alheia}")->assertNotFound();
});
