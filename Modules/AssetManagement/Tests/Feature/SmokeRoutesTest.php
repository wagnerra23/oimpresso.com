<?php

declare(strict_types=1);

use App\Business;
use App\User;
use App\Utils\ModuleUtil;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class);

/**
 * Smoke test das rotas principais do Modules/AssetManagement.
 *
 * Valida que as rotas resource declaradas em Routes/web.php foram registradas
 * pelo nWidart/laravel-modules e estão acessíveis via Route::has().
 *
 * Refs: Routes/web.php (Route::resource assets/allocation/revocation/settings/asset-maintenance)
 * Padrão: skill `criar-modulo` + Modules/Auditoria/Tests/Feature/AuditoriaModuleTest.php
 */

it('cenario 1: rota nomeada assets.index existe', function () {
    expect(\Route::has('assets.index'))->toBeTrue('Rota assets.index deveria existir per Routes/web.php');
});

it('cenario 2: rota nomeada assets.create existe', function () {
    expect(\Route::has('assets.create'))->toBeTrue('Rota assets.create deveria existir');
});

it('cenario 3: rota nomeada allocation.index existe', function () {
    expect(\Route::has('allocation.index'))->toBeTrue('Rota allocation.index deveria existir');
});

it('cenario 4: rota nomeada asset-maintenance.index existe', function () {
    expect(\Route::has('asset-maintenance.index'))->toBeTrue('Rota asset-maintenance.index deveria existir');
});

/**
 * Gate de permissão do ÍNDICE de Bens — `GET asset/assets` exige `asset.view`.
 *
 * O QUE DEFENDE: até 2026-09-08 o `AssetController::index()` checava só a assinatura
 * do módulo (`can('superadmin') || hasThePermissionInSubscription(..., 'assetmanagement_module')`),
 * então QUALQUER usuário da empresa com o módulo assinado listava o patrimônio inteiro.
 * As checagens de `asset.view_all_maintenance` (:145), `asset.update` (:154) e
 * `asset.delete` (:163) que já existiam no método só desenham botão de linha — botão
 * escondido não é autorização. Os métodos `create()`, `edit()` e `destroy()` já traziam
 * o padrão correto (permissão de tela ANTES do gate de assinatura); o `index()` era o
 * único método de leitura de dados do controller sem ele.
 *
 * PERMISSÃO NÃO INVENTADA: `asset.view` já existia registrada em
 * `Modules/AssetManagement/Http/Controllers/DataController.php:31`
 * (`user_permissions()`, que popula a lista de `/roles/{id}/edit`) e já era a permissão
 * que o próprio módulo usava para decidir se mostra o link "Ativos"
 * (`Resources/views/layouts/nav.blade.php:21`, `@can('asset.view')`). A guarda faz a
 * ROTA honrar o que a NAV já declarava.
 *
 * CONTROLE POSITIVO (CN): sem ele, o 403 do primeiro cenário seria indistinguível de um
 * 403 vindo do pipeline (`throttle`/`authh`/`auth`/`SetSessionData`/`AdminSidebarMenu`) —
 * um teste verde que não prova a guarda. O CN asserta `status !== 403` (e não `200`) de
 * propósito: ele responde "o 403 veio da guarda?", NÃO "a listagem renderiza" — o corpo
 * do `index()` fora do ramo ajax é comportamento pré-existente que este PR não toca.
 *
 * TENANT: 98 (fictício, ADR 0358) resolvido por `find`-ou-`forceCreate`. Fixar o id sem
 * o fallback assume um seed que o CT 100 pode não ter (ele já causou FK violation em
 * `ClienteVeiculosModuleGateTest`); os fixtures são removidos no `afterEach` porque a base
 * do CT 100 é clone de prod e NÃO se limpa entre runs.
 *
 * @see Modules/AssetManagement/Http/Controllers/AssetController::index()
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md
 */
function assetViewGateFixture(bool $comPermissao): array
{
    if (DB::connection()->getDriverName() === 'sqlite') {
        test()->markTestSkipped('SQLite-incompatível: exige schema MySQL UltimatePOS (FKs business/users/roles).');
    }
    foreach (['business', 'users', 'roles', 'permissions'] as $tabela) {
        if (! Schema::hasTable($tabela)) {
            test()->markTestSkipped("Schema UltimatePOS ausente (tabela {$tabela}) — esta suíte roda em MySQL real semeado.");
        }
    }

    $biz = Business::find(98) ?: Business::forceCreate([
        'id' => 98,
        'name' => 'Tenant ficticio 98 (ADR 0358)',
        'currency_id' => 1,
        'start_date' => now()->toDateString(),
        'default_profit_percent' => 0,
        'owner_id' => 1,
        'stop_selling_before' => 0,
        'weighing_scale_setting' => '',
        'certificado' => '',
        'officeimpresso_numerodemaquinas' => 0,
    ]);

    // user_type='user' + allow_login=1: sem eles o pipeline UltimatePOS aborta antes
    // de chegar no controller, e o teste passaria pelo motivo errado.
    $user = User::factory()->create([
        'business_id' => $biz->id,
        'username' => 'asset_view_gate_'.uniqid(),
        'user_type' => 'user',
        'allow_login' => 1,
    ]);

    if ($comPermissao) {
        // `roles.business_id` é NOT NULL + FK pra business e o sufixo `#{biz}` é a
        // convenção da casa pra role por tenant (proibicoes.md §FSM).
        $perm = Permission::firstOrCreate(['name' => 'asset.view', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(
            ['name' => 'asset-view-gate#'.$biz->id, 'guard_name' => 'web'],
            ['business_id' => $biz->id]
        );
        $role->givePermissionTo($perm);
        $user->assignRole($role);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    return [$biz, $user];
}

function assetViewGateChamar(Business $biz, User $user)
{
    return test()
        ->actingAs($user)
        ->withSession([
            'user.business_id' => $biz->id,
            'user' => ['business_id' => $biz->id, 'id' => $user->id],
        ])
        ->get('/asset/assets');
}

function assetViewGateLimpar(): void
{
    app()->forgetInstance(ModuleUtil::class);

    // `withTrashed`: App\User usa SoftDeletes — sem isto um fixture já soft-deletado
    // escaparia da varredura e ficaria na base. Esta é a forma provada no CT 100
    // (apagou os 8 órfãos de 2026-09-08 sem uma exceção).
    $users = User::withTrashed()->where('username', 'like', 'asset_view_gate_%')->get();
    foreach ($users as $u) {
        DB::table('model_has_roles')->where('model_id', $u->id)->delete();
        $u->forceDelete();
    }
    Role::where('name', 'like', 'asset-view-gate#%')->delete();
}

// A limpeza vai em `try/finally` DENTRO do closure, não em `->afterEach()` encadeado:
// medido no CT 100 em 2026-09-08, o `->afterEach()` por teste não executou (4 rodadas
// deixaram 8 usuários e 1 role órfãos na base, que é clone de prod e NÃO se limpa entre
// runs). A mesma função de limpeza, chamada à mão, apagou os 8 sem erro — ou seja, o
// defeito era o gancho, não a lógica. `finally` também limpa quando o assert falha.
it('MORDE: usuário SEM asset.view recebe 403 em GET asset/assets', function () {
    [$biz, $user] = assetViewGateFixture(comPermissao: false);

    try {
        assetViewGateChamar($biz, $user)->assertStatus(403);
    } finally {
        assetViewGateLimpar();
    }
});

it('CN: usuário COM asset.view NÃO é barrado pela guarda (o 403 acima veio dela, não do pipeline)', function () {
    [$biz, $user] = assetViewGateFixture(comPermissao: true);

    try {
        // A assinatura do módulo é o gate SEGUINTE do método; sem neutralizá-la, quem tem
        // `asset.view` tomaria 403 na linha de baixo e o controle positivo mediria a coisa errada.
        $moduleUtil = Mockery::mock(ModuleUtil::class)->makePartial();
        $moduleUtil->shouldReceive('hasThePermissionInSubscription')->andReturn(true);
        app()->instance(ModuleUtil::class, $moduleUtil);

        expect(assetViewGateChamar($biz, $user)->status())->not->toBe(403);
    } finally {
        assetViewGateLimpar();
    }
});
