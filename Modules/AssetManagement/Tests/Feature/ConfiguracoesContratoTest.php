<?php

declare(strict_types=1);

use App\Business;
use App\User;
use App\Utils\ModuleUtil;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class);

/**
 * Contrato da tela Patrimonio/Configuracoes — prefixos de código e notificações de
 * manutenção, migrada de 3 blades pra uma tela Inertia.
 *
 * Defende os UCs de `resources/js/Pages/Patrimonio/Configuracoes.casos.md` (G-2 do
 * casos-gate, ADR 0264): cada `it()` cita o id do UC no título, que é como o gate amarra
 * caso <-> teste.
 *
 * ADR 0358: tenant canônico de teste é o FICTÍCIO 98 (`seededTenant()`); 99 é o adversário
 * cross-tenant (`seededSupportClientTenant()`). biz=1 é a WR2 Sistemas, empresa REAL — no
 * CT 100 a base é clone de prod e não se limpa entre execuções. biz=4 (ROTA LIVRE) é
 * proibido sem exceção.
 *
 * ⚠️ Esta é a única suíte da frente do Patrimônio que ESCREVE em `business` (o UC-CFG-04
 * exercita o `store()`). Por isso todo cenário que grava faz backup da coluna
 * `asset_settings` e a restaura em `finally` — inclusive quando o assert falha.
 *
 * @see resources/js/Pages/Patrimonio/Configuracoes.casos.md
 * @see memory/requisitos/AssetManagement/RUNBOOK-configuracoes.md
 * @see memory/decisions/0394-endereco-de-ui-do-patrimonio-pages-patrimonio.md
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: Models AssetManagement legacy requerem schema MySQL UltimatePOS');
    }
    if (! Schema::hasTable('assets') || ! Schema::hasTable('business')) {
        $this->markTestSkipped('Tabelas assets/business ausentes — rode migrate primeiro');
    }
});

/**
 * Usuário do business dado.
 *
 * `$admin = true` concede o role `Admin#{business_id}` — que é LITERALMENTE o que
 * `Util::is_admin()` verifica (`app/Utils/Util.php:486`: `hasRole('Admin#'.$business_id)`).
 * Não é permission: por isso o fixture não-admin recebe `asset.view` e mesmo assim é barrado.
 * Fosse permission, o cenário do 403 passaria pelo motivo errado.
 *
 * `roles.business_id` é NOT NULL + FK, e o sufixo `#{biz}` é a convenção da casa — role
 * global viola a FK.
 */
function cfgContratoUsuario(int $businessId, bool $admin): User
{
    $user = User::factory()->create([
        'business_id' => $businessId,
        'username' => 'cfg_contrato_'.uniqid(),
        'user_type' => 'user',
        'allow_login' => 1,
    ]);

    // Concedida nos DOIS casos: o não-admin tem permissão do módulo e ainda assim toma 403,
    // que é exatamente o que o UC-CFG-01 afirma.
    $perm = Permission::firstOrCreate(['name' => 'asset.view', 'guard_name' => 'web']);

    $nomeRole = $admin ? 'Admin#'.$businessId : 'cfg-contrato#'.$businessId;

    $role = Role::firstOrCreate(
        ['name' => $nomeRole, 'guard_name' => 'web'],
        ['business_id' => $businessId]
    );
    $role->givePermissionTo($perm);
    $user->assignRole($role);

    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

/**
 * A assinatura do módulo é o gate que vem ANTES do `! $is_admin` dentro do mesmo `if`. Sem
 * neutralizá-la, todo cenário tomaria 403 nessa parte e mediria a coisa errada — inclusive o
 * cenário POSITIVO do UC-CFG-01, que precisa provar que o admin passa.
 */
function cfgContratoAssinaturaLiberada(): void
{
    $moduleUtil = Mockery::mock(ModuleUtil::class)->makePartial();
    $moduleUtil->shouldReceive('hasThePermissionInSubscription')->andReturn(true);
    app()->instance(ModuleUtil::class, $moduleUtil);
}

function cfgContratoGet(User $user, int $businessId)
{
    return test()
        ->actingAs($user)
        ->withSession([
            'user.business_id' => $businessId,
            'user' => ['business_id' => $businessId, 'id' => $user->id],
        ])
        ->get('/asset/settings');
}

function cfgContratoPost(User $user, int $businessId, array $payload)
{
    return test()
        ->actingAs($user)
        ->withSession([
            'user.business_id' => $businessId,
            'user' => ['business_id' => $businessId, 'id' => $user->id],
        ])
        ->post('/asset/settings', $payload);
}

/**
 * Limpeza à mão em `try/finally`, NÃO por `afterEach` encadeado: medido no CT 100 em
 * 2026-09-08 (thread 03), o gancho por teste não executou e deixou fixtures órfãos numa base
 * que é clone de prod e não se limpa entre runs.
 *
 * ⚠️ O role `Admin#{biz}` NÃO é deletado: ele é pré-existente e compartilhado (outros
 * usuários reais do tenant dependem dele). Some-se a associação do fixture. Só o role
 * `cfg-contrato#{biz}`, que esta suíte cria, é removido.
 */
function cfgContratoLimpar(): void
{
    app()->forgetInstance(ModuleUtil::class);

    $users = User::withTrashed()->where('username', 'like', 'cfg_contrato_%')->get();
    foreach ($users as $u) {
        DB::table('model_has_roles')->where('model_id', $u->id)->delete();
        $u->forceDelete();
    }

    Role::where('name', 'like', 'cfg-contrato#%')->delete();
}

/** Backup/restore da coluna que o `store()` regrava por inteiro. */
function cfgContratoSettingsAtuais(int $businessId): ?string
{
    return Business::where('id', $businessId)->value('asset_settings');
}

function cfgContratoRestaurarSettings(int $businessId, ?string $original): void
{
    Business::where('id', $businessId)->update(['asset_settings' => $original]);
}

it('UC-CFG-01: usuário NÃO-admin do business recebe 403 em /asset/settings', function () {
    $biz = $this->seededTenant();
    $user = cfgContratoUsuario((int) $biz->id, admin: false);

    try {
        cfgContratoAssinaturaLiberada();

        cfgContratoGet($user, (int) $biz->id)->assertStatus(403);
    } finally {
        cfgContratoLimpar();
    }
});

it('UC-CFG-01: o ESPELHO — o admin do mesmo business recebe 200', function () {
    // Sem este espelho, o 403 acima também passaria se a rota estivesse quebrada, se o módulo
    // não estivesse assinado, ou se qualquer gate anterior barrasse. É ele que prova que o 403
    // veio do gate de ADMIN, e não de outra coisa.
    $biz = $this->seededTenant();
    $user = cfgContratoUsuario((int) $biz->id, admin: true);

    try {
        cfgContratoAssinaturaLiberada();

        cfgContratoGet($user, (int) $biz->id)->assertStatus(200);
    } finally {
        cfgContratoLimpar();
    }
});

it('UC-CFG-02: a rota /asset/settings devolve Inertia com o componente Patrimonio/Configuracoes', function () {
    $biz = $this->seededTenant();
    $user = cfgContratoUsuario((int) $biz->id, admin: true);

    try {
        cfgContratoAssinaturaLiberada();

        cfgContratoGet($user, (int) $biz->id)
            ->assertStatus(200)
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Patrimonio/Configuracoes')
                ->has('settings')
                ->has('templates.send_for_maintenance.subject')
                ->has('templates.assigned_for_maintenance.subject')
                ->has('usuarios')
                ->has('tags.send_for_maintenance')
            );
    } finally {
        cfgContratoLimpar();
    }
});

it('UC-CFG-03: as settings exibidas são as do MEU business, não as do vizinho', function () {
    $bizA = $this->seededTenant();
    $bizB = $this->seededSupportClientTenant();

    $originalA = cfgContratoSettingsAtuais((int) $bizA->id);
    $originalB = cfgContratoSettingsAtuais((int) $bizB->id);

    $userA = cfgContratoUsuario((int) $bizA->id, admin: true);
    $userB = cfgContratoUsuario((int) $bizB->id, admin: true);

    try {
        cfgContratoAssinaturaLiberada();

        Business::where('id', $bizA->id)->update([
            'asset_settings' => json_encode(['asset_code_prefix' => 'CFG-AAA-']),
        ]);
        Business::where('id', $bizB->id)->update([
            'asset_settings' => json_encode(['asset_code_prefix' => 'CFG-BBB-']),
        ]);

        // Os DOIS lados assertados: um `not->toBe` sozinho passaria se a prop viesse vazia.
        cfgContratoGet($userA, (int) $bizA->id)
            ->assertStatus(200)
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('settings.asset_code_prefix', 'CFG-AAA-')
            );

        cfgContratoGet($userB, (int) $bizB->id)
            ->assertStatus(200)
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('settings.asset_code_prefix', 'CFG-BBB-')
            );
    } finally {
        cfgContratoRestaurarSettings((int) $bizA->id, $originalA);
        cfgContratoRestaurarSettings((int) $bizB->id, $originalB);
        cfgContratoLimpar();
    }
});

it('UC-CFG-04: desligar o e-mail desliga de verdade — a chave ausente é o "desligado"', function () {
    // A ARMADILHA desta migração. O `store()` decide por `$request->has(...)`, não
    // `boolean(...)`: um payload com `enable_...: false` faria `has()` devolver TRUE e
    // gravaria 1 — desligar deixaria de funcionar em silêncio. A tela OMITE a chave quando
    // desmarcada, e é esse contrato que este teste fixa.
    $biz = $this->seededTenant();
    $original = cfgContratoSettingsAtuais((int) $biz->id);
    $user = cfgContratoUsuario((int) $biz->id, admin: true);

    try {
        cfgContratoAssinaturaLiberada();

        $base = [
            'asset_code_prefix' => 'CFG-T-',
            'allocation_code_prefix' => 'CFA-T-',
            'revoke_code_prefix' => 'CFR-T-',
            'asset_maintenance_prefix' => 'CFM-T-',
        ];

        // 1) LIGA — a chave presente grava o valor.
        cfgContratoPost($user, (int) $biz->id, $base + [
            'enable_asset_send_for_maintenance_email' => '1',
        ]);

        $ligado = json_decode((string) cfgContratoSettingsAtuais((int) $biz->id), true);
        expect(empty($ligado['enable_asset_send_for_maintenance_email']))->toBeFalse();

        // 2) DESLIGA — a chave OMITIDA (é o que o checkbox HTML faz, e o que a tela replica).
        cfgContratoPost($user, (int) $biz->id, $base);

        $desligado = json_decode((string) cfgContratoSettingsAtuais((int) $biz->id), true);
        expect(empty($desligado['enable_asset_send_for_maintenance_email']))->toBeTrue();

        // 3) Os prefixos sobreviveram aos dois saves — o `store()` regrava o JSON inteiro, e
        //    este assert é o que denuncia se alguém quebrar isso.
        expect($desligado['asset_code_prefix'])->toBe('CFG-T-');
    } finally {
        cfgContratoRestaurarSettings((int) $biz->id, $original);
        cfgContratoLimpar();
    }
});
