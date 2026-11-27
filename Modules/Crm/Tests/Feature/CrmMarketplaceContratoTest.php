<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(Tests\TestCase::class);

/**
 * Contrato da tela `/crm/b2b-marketplace` (Marketplace B2B) — Onda 1 do RUNBOOK-marketplace: integração ligada
 * e Inertia atrás da flag `useV2CrmMarketplace`, ligada aqui pelo override `feature-flags.forced_on`.
 *
 * Os UCs vêm do contrato, não do código:
 *   Modules/Crm/Resources/js/Pages/Crm/Marketplace/Index.casos.md
 *
 * Tenant 98 (fictício, ADR 0358) e 99 como o "outro negócio". NUNCA biz=4.
 * ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
 *
 * @see Modules/Crm/Http/Controllers/CrmMarketplaceController.php
 */
const MKT_TAG = '[crmmkt]';
const MKT_BIZ = 98;
const MKT_OUTRO = 99;
const MKT_ROTA = '/crm/b2b-marketplace';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: crm_marketplaces requer schema MySQL UltimatePOS.');
    }
    foreach (['business', 'users', 'roles', 'crm_marketplaces', 'categories'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema incompleto — tabela {$t} ausente.");
        }
    }
    config(['feature-flags.forced_on' => 'useV2CrmMarketplace', 'constants.enable_b2b_marketplace' => true]);
    mktLimpa();
});

afterEach(fn () => mktLimpa());

function mktLimpa(): void
{
    DB::table('crm_marketplaces')->where('site_key', 'like', '%'.MKT_TAG.'%')->delete();
    DB::table('categories')->where('name', 'like', '%'.MKT_TAG.'%')->delete();
}

function mktNegocio(int $biz): void
{
    if (Business::whereKey($biz)->exists()) {
        return;
    }
    $dono = (int) DB::table('users')->min('id');
    (new Business)->forceFill(['id' => $biz, 'name' => 'Tenant fictício crm '.$biz, 'currency_id' => 1, 'owner_id' => $dono])->save();
}

function mktUsuario(string $username, bool $admin, int $biz = MKT_BIZ): User
{
    mktNegocio($biz);
    $user = User::firstOrCreate(['username' => $username], [
        'email' => $username.'@test.local', 'password' => bcrypt('secret'),
        'business_id' => $biz, 'first_name' => 'Mkt', 'last_name' => 'Teste',
        'user_type' => 'user', 'allow_login' => 1,
    ]);
    if ($admin) {
        $role = Role::firstOrCreate(['name' => 'Admin#'.$biz, 'guard_name' => 'web'], ['business_id' => $biz]);
        $user->syncRoles([$role]);
    } else {
        $user->syncRoles([]);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user->fresh();
}

function mktFonte(int $biz, string $nome, User $quem): int
{
    return (int) DB::table('categories')->insertGetId([
        'business_id' => $biz, 'category_type' => 'source', 'name' => $nome.' '.MKT_TAG, 'parent_id' => 0,
        'created_by' => $quem->id, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function mktConfig(int $biz, string $chave, int $usuario): void
{
    if (DB::table('crm_marketplaces')->where('business_id', $biz)->where('site_key', 'not like', '%'.MKT_TAG.'%')->exists()) {
        test()->markTestSkipped("A empresa {$biz} já tem configuração de marketplace que não é do teste; não é apagada.");
    }
    DB::table('crm_marketplaces')->insert([
        'business_id' => $biz, 'marketplace' => 'exportersindia', 'site_key' => $chave.' '.MKT_TAG,
        'site_id' => 'conta@test.local', 'assigned_users' => json_encode([(string) $usuario]),
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

it('UC-CRMMKT-01 · Admin abre a tela React; com ?classico=1, a Blade', function () {
    $admin = mktUsuario('crmmkt_admin_test', true);

    $this->actingAs($admin)->get(MKT_ROTA)->assertOk()
        ->assertInertia(fn (AssertableInertia $p) => $p->component('Crm/Marketplace/Index', false)
            ->has('config')->has('usuarios')->has('fontes'));

    $this->actingAs($admin)->get(MKT_ROTA.'?classico=1')->assertOk()->assertViewIs('crm::marketplace.index');
});

it('UC-CRMMKT-02 · quem não é Admin recebe 403', function () {
    $this->actingAs(mktUsuario('crmmkt_comum_test', false))->get(MKT_ROTA)->assertForbidden();
});

it('UC-CRMMKT-03 · a configuração exibida é a da minha empresa', function () {
    $admin = mktUsuario('crmmkt_admin_test', true);
    $vizinho = mktUsuario('crmmkt_vizinho_test', true, MKT_OUTRO);
    mktConfig(MKT_OUTRO, 'chave-vizinha', $vizinho->id);
    mktConfig(MKT_BIZ, 'chave-minha', $admin->id);

    $this->actingAs($admin)->get(MKT_ROTA)->assertOk()
        ->assertInertia(fn (AssertableInertia $p) => $p->where('config.chave', 'chave-minha '.MKT_TAG));
});

it('UC-CRMMKT-04 · salvar recusa usuário ou fonte de outra empresa, e não grava', function () {
    $admin = mktUsuario('crmmkt_admin_test', true);
    $vizinho = mktUsuario('crmmkt_vizinho_test', true, MKT_OUTRO);
    $fonteVizinha = mktFonte(MKT_OUTRO, 'Fonte vizinha', $vizinho);

    $this->actingAs($admin)->post('/crm/save-marketplace', [
        'marketplace' => 'exportersindia', 'site_key' => 'nao-grava '.MKT_TAG, 'site_id' => 'conta@test.local',
        'assigned_users' => [(string) $vizinho->id], 'crm_source_id' => (string) $fonteVizinha,
    ])->assertSessionHasErrors(['assigned_users.0', 'crm_source_id']);

    expect(DB::table('crm_marketplaces')->where('site_key', 'nao-grava '.MKT_TAG)->exists())->toBeFalse();
});

it('UC-CRMMKT-05 · salvar com usuário e fonte da minha empresa grava no meu negócio e avisa', function () {
    $admin = mktUsuario('crmmkt_admin_test', true);
    if (DB::table('crm_marketplaces')->where('business_id', MKT_BIZ)->where('site_key', 'not like', '%'.MKT_TAG.'%')->exists()) {
        $this->markTestSkipped('A empresa 98 já tem configuração de marketplace que não é do teste; não é sobrescrita.');
    }
    $fonte = mktFonte(MKT_BIZ, 'Fonte minha', $admin);

    $this->actingAs($admin)->from(MKT_ROTA)->post('/crm/save-marketplace', [
        'marketplace' => 'exportersindia', 'site_key' => 'grava '.MKT_TAG, 'site_id' => 'conta@test.local',
        'assigned_users' => [(string) $admin->id], 'crm_source_id' => (string) $fonte,
    ])->assertSessionHasNoErrors()->assertSessionHas('status.success', 1);

    $linha = DB::table('crm_marketplaces')->where('site_key', 'grava '.MKT_TAG)->first();
    expect($linha)->not->toBeNull();
    expect((int) $linha->business_id)->toBe(MKT_BIZ);
    expect((int) $linha->crm_source_id)->toBe($fonte);
});
