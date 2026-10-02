<?php

declare(strict_types=1);

// Tests\TestCase já é aplicado globalmente em tests/Pest.php. NÃO redeclarar aqui.

use App\User;
use App\Utils\ModuleUtil;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * API de Perfil de menu do app das lojas (tela 30) — PUT /api/app/perfil-menu e `barra` no Início.
 *
 * Contrato: memory/requisitos/AppMobile/API-CONTRATO-v1.md §12.3 + ADR 0426 (decisão [W]: a
 * escolha fica guardada NO ERP; `barra` = escolha ∩ `areas`; sem escolha, o padrão do ERP §7.1).
 * NÃO derivado do controller.
 *
 * Tier 0: tenant fictício 98 (ADR 0358) contra o business 2 da lane. Transação revertida.
 */

const APP_PM_BIZ = 98;
const APP_PM_OUTRO = 2;

/** Usuário que vê vendas: areas = inicio, pedidos, producao, orcamentos, mais (Essentials desligado). */
function appPmUsuario(): User
{
    $user = User::factory()->create(['business_id' => APP_PM_BIZ]);
    $papel = Role::create(['name' => 'AppPm' . uniqid() . '#' . APP_PM_BIZ, 'business_id' => APP_PM_BIZ, 'guard_name' => 'web']);
    Permission::findOrCreate('direct_sell.view', 'web');
    $papel->syncPermissions(['direct_sell.view']);
    $user->assignRole($papel);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return User::findOrFail($user->id);
}

beforeEach(function () {
    // O guard `api` do Passport precisa das chaves mesmo com Passport::actingAs; a lane não as gera.
    if (! file_exists(storage_path('oauth-public.key'))) {
        Artisan::call('passport:keys', ['--force' => true]);
    }
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Schema UltimatePOS + Spatie exigem MySQL (ADR 0358).');
    }
    if (DB::table('business')->whereIn('id', [APP_PM_BIZ, APP_PM_OUTRO])->count() !== 2
        || ! Schema::hasTable('app_menu_preferencias')) {
        $this->markTestSkipped('Tenants 98/2 ou app_menu_preferencias ausentes nesta lane.');
    }
    // Tarefas fora (Essentials fora do plano), como no AppInicioApiContratoTest: as áreas ficam fixas.
    $mu = Mockery::mock(ModuleUtil::class)->makePartial();
    $mu->shouldReceive('hasThePermissionInSubscription')->andReturn(false);
    app()->instance(ModuleUtil::class, $mu);
    DB::beginTransaction();
});

afterEach(function () {
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
});

it('UC-APP30-01: sem escolha, a barra é o padrão do ERP dentro das áreas do usuário', function () {
    Passport::actingAs(appPmUsuario(), [], 'api');

    $r = $this->getJson('/api/app/inicio')->assertOk();

    expect($r->json('areas'))->toBe(['inicio', 'pedidos', 'producao', 'orcamentos', 'mais']);
    expect($r->json('barra'))->toBe(['pedidos', 'producao', 'orcamentos']);
});

it('UC-APP30-02: a escolha fica gravada no ERP, na ordem, e volta no Início; lista vazia volta ao padrão', function () {
    $u = appPmUsuario();
    Passport::actingAs($u, [], 'api');

    $this->putJson('/api/app/perfil-menu', ['modulos' => ['orcamentos', 'pedidos']])
        ->assertOk()->assertExactJson(['modulos' => ['orcamentos', 'pedidos'], 'barra' => ['orcamentos', 'pedidos']]);

    $linha = DB::table('app_menu_preferencias')->where('user_id', $u->id)->first();
    expect((int) $linha->business_id)->toBe(APP_PM_BIZ);
    expect(json_decode((string) $linha->modulos, true))->toBe(['orcamentos', 'pedidos']);
    expect($this->getJson('/api/app/inicio')->assertOk()->json('barra'))->toBe(['orcamentos', 'pedidos']);

    $this->putJson('/api/app/perfil-menu', ['modulos' => ['producao']])->assertOk();
    expect(DB::table('app_menu_preferencias')->where('user_id', $u->id)->count())->toBe(1);
    expect($this->getJson('/api/app/inicio')->assertOk()->json('barra'))->toBe(['producao']);

    $this->putJson('/api/app/perfil-menu', ['modulos' => []])->assertOk()->assertJsonPath('barra', ['pedidos', 'producao', 'orcamentos']);
    expect(DB::table('app_menu_preferencias')->where('user_id', $u->id)->count())->toBe(0);
});

it('UC-APP30-03: mais de 3, repetido, módulo fora das áreas ou fixo é 422 em campos.modulos e nada é gravado', function () {
    $u = appPmUsuario();
    Passport::actingAs($u, [], 'api');

    foreach ([
        ['pedidos', 'producao', 'orcamentos', 'pedidos'],
        ['pedidos', 'pedidos'],
        ['ponto'],
        ['inicio'],
    ] as $modulos) {
        $this->putJson('/api/app/perfil-menu', ['modulos' => $modulos])
            ->assertStatus(422)->assertJsonPath('erro', 'validacao')->assertJsonStructure(['campos' => ['modulos']]);
    }
    $this->putJson('/api/app/perfil-menu', [])->assertStatus(422);
    expect(DB::table('app_menu_preferencias')->where('user_id', $u->id)->count())->toBe(0);
});

it('UC-APP30-04: escolha gravada sob OUTRO business não vale para o usuário (Tier 0)', function () {
    $u = appPmUsuario();
    DB::table('app_menu_preferencias')->insert([
        'business_id' => APP_PM_OUTRO, 'user_id' => $u->id, 'modulos' => json_encode(['orcamentos']),
        'created_at' => now(), 'updated_at' => now(),
    ]);
    Passport::actingAs($u, [], 'api');

    expect($this->getJson('/api/app/inicio')->assertOk()->json('barra'))->toBe(['pedidos', 'producao', 'orcamentos']);

    // Controle positivo: a mesma escolha no business do usuário vale.
    $this->putJson('/api/app/perfil-menu', ['modulos' => ['orcamentos']])->assertOk();
    expect($this->getJson('/api/app/inicio')->assertOk()->json('barra'))->toBe(['orcamentos']);
    expect(DB::table('app_menu_preferencias')->where('business_id', APP_PM_OUTRO)->where('user_id', $u->id)->value('modulos'))
        ->toBe(json_encode(['orcamentos']));
});
