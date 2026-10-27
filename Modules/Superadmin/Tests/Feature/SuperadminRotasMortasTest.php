<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

/**
 * Rotas Blade mortas do Superadmin que saíram em 2026-10-27 (migração Blade → React, ADR 0104).
 * Antes: `show` de pacotes e de assinaturas devolvia `view('superadmin::show')`, view que nunca
 * existiu (500); `/subscription/{id}` era o modal que o drawer de Minha assinatura substituiu;
 * `/all-subscriptions` alimentava só a Blade `subscription.index`, já trocada pela tela React;
 * `/pricing/old` era o fallback do `/pricing` em React.
 *
 * Cada caso pede a rota como SUPERADMIN, para o 404 vir da rota ausente e não do gate.
 * ⚠️ SKIP em SQLite: precisa do schema UltimatePOS real. Leia *assertions*, não "0 failed" (LC-13).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: rotas do Superadmin requerem schema MySQL UltimatePOS.');
    }
    if (! Schema::hasTable('business')) {
        $this->markTestSkipped('Schema UltimatePOS ausente — rode migrations primeiro.');
    }
    config(['constants.administrator_usernames' => 'morta_superadmin_test']);
});

/** Tenant fictício. NUNCA biz=4 (ROTA LIVRE, produção) — ADR 0358. */
const BIZ_MORTA = 98;

function mortaSuperadmin(): User
{
    Business::firstOrCreate(['id' => BIZ_MORTA], ['name' => 'Tenant fictício rotas mortas', 'currency_id' => 1]);

    $user = User::firstOrCreate(['username' => 'morta_superadmin_test'], [
        'email' => 'morta_superadmin@test.local', 'password' => bcrypt('secret'),
        'business_id' => BIZ_MORTA, 'first_name' => 'Morta', 'last_name' => 'Superadmin',
    ]);
    $user->givePermissionTo(Permission::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']));

    return $user;
}

it('o superadmin abre as telas vivas (controle positivo)', function () {
    $su = mortaSuperadmin();

    $this->actingAs($su)->get('/superadmin/packages')->assertOk();
    $this->actingAs($su)->get('/superadmin/superadmin-subscription')->assertOk();
});

it('show de pacote e de assinatura não existe mais (era 500)', function () {
    $su = mortaSuperadmin();

    $this->actingAs($su)->get('/superadmin/packages/1')->assertNotFound();
    $this->actingAs($su)->get('/superadmin/superadmin-subscription/1')->assertNotFound();
});

it('o modal Blade da assinatura e o ajax da lista antiga não existem mais', function () {
    $su = mortaSuperadmin();

    $this->actingAs($su)->get('/subscription/1')->assertNotFound();
    $this->actingAs($su)->get('/all-subscriptions')->assertNotFound();
});

it('o fallback Blade /pricing/old saiu; /pricing segue', function () {
    $this->get('/pricing/old')->assertNotFound();
    $this->get('/pricing')->assertOk();
});
