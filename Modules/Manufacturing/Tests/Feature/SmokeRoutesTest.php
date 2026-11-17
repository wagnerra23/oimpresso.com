<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Smoke das 4 telas do Manufacturing: lista de ordens, receitas, relatório e configurações.
 *
 * Até 2026-11-11 os casos eram anônimos e aceitavam qualquer status < 500: na prática mediam o
 * redirect de login, não a tela. Agora são dois lados, cada um com a sua expectativa exata:
 *   - visitante (sem login) é mandado para /login, em TODAS as rotas (antes bastava uma);
 *   - usuário da empresa 98 com as permissões do módulo recebe 200 (a tela responde).
 *
 * O usuário entra na lista de administradores, que resolve o pacote `manufacturing_module`
 * (Gate `superadmin`) sem conceder as permissões do recurso — essas são dadas explicitamente.
 *
 * Tenant 98 (fictício, ADR 0358). NUNCA biz=4. Tudo dentro de transação desfeita.
 *
 * @see Modules/Manufacturing/Routes/web.php
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: middlewares UltimatePOS dependem schema MySQL. ADR 0101.');
    }
    if (! Schema::hasTable('mfg_recipes')) {
        $this->markTestSkipped('Manufacturing não instalado nesta base.');
    }
});

dataset('manufacturing_smoke_routes', [
    'lista de ordens' => ['/manufacturing/production'],
    'receitas'        => ['/manufacturing/recipe'],
    'relatório'       => ['/manufacturing/report'],
    'configurações'   => ['/manufacturing/settings'],
]);

function mfgSmokeUsuario(): User
{
    if (! DB::table('business')->where('id', 98)->exists()) {
        $dono = (int) DB::table('users')->min('id');
        (new App\Business)->forceFill(['id' => 98, 'name' => 'Tenant fictício mfg 98', 'currency_id' => 1, 'owner_id' => $dono])->save();
    }
    $user = User::firstOrCreate(['username' => 'mfg_smoke_rotas'], [
        'email' => 'mfg_smoke_rotas@test.local', 'password' => bcrypt('secret'),
        'business_id' => 98, 'first_name' => 'Smoke', 'last_name' => 'Fabricacao',
        'user_type' => 'user', 'allow_login' => 1,
    ]);
    foreach (['manufacturing.access_recipe', 'manufacturing.access_production'] as $p) {
        $user->givePermissionTo(Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']));
    }
    config(['constants.administrator_usernames' => $user->username]);

    return $user;
}

it('visitante sem login é mandado para /login', function (string $url) {
    $this->get($url)->assertRedirect('/login');
})->with('manufacturing_smoke_routes');

it('usuário da empresa com as permissões do módulo recebe a tela (200)', function (string $url) {
    $sessao = ['currency' => ['code' => 'BRL', 'symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ',']];

    $this->actingAs(mfgSmokeUsuario())->withSession($sessao)->get($url)->assertOk();
})->with('manufacturing_smoke_routes');
