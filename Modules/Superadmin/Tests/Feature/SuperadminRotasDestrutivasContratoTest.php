<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

// @covers-us US-SUPER-001

/**
 * Contrato das rotas do superadmin que MUDAM ESTADO — auditoria 2026-11-05 (eixo 5, ALTA).
 *
 * Antes: `GET /superadmin/{id}/toggle-active/{x}` (x livre, ia direto ao UPDATE),
 * `GET /superadmin/business/{id}/destroy` e `GET /superadmin/packages/{id}/destroy` — um link
 * visto por superadmin logado (cookie `same_site=lax`) bastava. E `show` dos resources de
 * pacote e assinatura renderizava `superadmin::show`, view inexistente (500).
 *
 * Decisão B5 ([W] 2026-11-18): "excluir" empresa não apaga nada — só desativa (UC-SARD-08).
 *
 * ⚠️ SKIP em SQLite: precisa do schema UltimatePOS real. Leia *assertions*, não "0 failed" (LC-13).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: rotas do superadmin requerem schema MySQL UltimatePOS.');
    }
    if (! Schema::hasTable('business') || ! Schema::hasTable('packages') || ! Schema::hasTable('permissions')) {
        $this->markTestSkipped('Schema UltimatePOS ausente — rode migrations primeiro.');
    }
    config(['constants.administrator_usernames' => 'rd_superadmin_test']);

    // Tenant fictício 98 (sessão) e 99 (alvo). NUNCA biz=4 nem biz=1 — ADR 0358.
    Business::firstOrCreate(['id' => 98], ['name' => 'Tenant fictício rotas destrutivas', 'currency_id' => 1]);
    Business::firstOrCreate(['id' => 99], ['name' => 'Tenant fictício alvo rotas destrutivas', 'currency_id' => 1]);
    $this->rdAtivoAntes = DB::table('business')->where('id', 99)->value('is_active');
});

afterEach(function () {
    if (property_exists($this, 'rdAtivoAntes')) {
        DB::table('business')->where('id', 99)->update(['is_active' => $this->rdAtivoAntes]);
    }
});

function rdUsuario(string $username, bool $superadmin): User
{
    $user = User::firstOrCreate(['username' => $username], [
        'email' => $username.'@test.local', 'password' => bcrypt('secret'),
        'business_id' => 98, 'first_name' => 'Rd', 'last_name' => 'Teste',
    ]);
    $user->syncRoles([]);
    $user->syncPermissions($superadmin
        ? [Permission::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web'])]
        : []);

    return $user;
}

/** Pacote marcado por nome; idempotente entre runs. */
function rdPacote(): int
{
    $nome = 'Pacote fictício contrato rotas destrutivas';
    $id = DB::table('packages')->where('name', $nome)->value('id');

    return (int) ($id ?? DB::table('packages')->insertGetId([
        'name' => $nome, 'description' => 'Só para o contrato de rotas destrutivas.',
        'location_count' => 1, 'user_count' => 0, 'product_count' => 0, 'invoice_count' => 0,
        'interval' => 'months', 'interval_count' => 1, 'trial_days' => 0, 'price' => 0,
        'created_by' => 1, 'sort_order' => 99, 'is_active' => 1, 'is_private' => 0, 'is_one_time' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]));
}

it('UC-SARD-01 · GET no toggle-active é 405 e não muda o negócio', function () {
    DB::table('business')->where('id', 99)->update(['is_active' => 1]);

    $this->actingAs(rdUsuario('rd_superadmin_test', true))
        ->get('/superadmin/99/toggle-active/0')
        ->assertStatus(405);

    expect((int) DB::table('business')->where('id', 99)->value('is_active'))->toBe(1);
});

it('UC-SARD-02 · toggle-active só aceita 0 ou 1', function () {
    DB::table('business')->where('id', 99)->update(['is_active' => 1]);

    $this->actingAs(rdUsuario('rd_superadmin_test', true))
        ->post('/superadmin/99/toggle-active/7')
        ->assertNotFound();

    expect((int) DB::table('business')->where('id', 99)->value('is_active'))->toBe(1);
});

it('UC-SARD-03 · POST sem ser superadmin é 403 ENQUANTO o superadmin desativa', function () {
    DB::table('business')->where('id', 99)->update(['is_active' => 1]);

    $this->actingAs(rdUsuario('rd_comum_test', false))
        ->post('/superadmin/99/toggle-active/0')
        ->assertForbidden();
    expect((int) DB::table('business')->where('id', 99)->value('is_active'))->toBe(1);

    $this->actingAs(rdUsuario('rd_superadmin_test', true))
        ->post('/superadmin/99/toggle-active/0')
        ->assertRedirect();
    expect((int) DB::table('business')->where('id', 99)->value('is_active'))->toBe(0);
});

it('UC-SARD-04 · os aliases GET de excluir não existem mais e não apagam nada', function () {
    $pacote = rdPacote();
    $user = rdUsuario('rd_superadmin_test', true);

    $this->actingAs($user)->get('/superadmin/business/99/destroy')->assertNotFound();
    $this->actingAs($user)->get("/superadmin/packages/{$pacote}/destroy")->assertNotFound();

    expect(DB::table('business')->where('id', 99)->exists())->toBeTrue();
    expect(DB::table('packages')->where('id', $pacote)->exists())->toBeTrue();
});

it('UC-SARD-05 · DELETE do próprio negócio é recusado e não desativa nem registra', function () {
    $user = rdUsuario('rd_superadmin_test', true);
    $antes = Schema::hasTable('activity_log')
        ? DB::table('activity_log')->where('event', 'negocio_desativado')->where('business_id', 98)->count()
        : 0;

    $this->actingAs($user)
        ->withSession(['user.business_id' => 98, 'user.id' => $user->id])
        ->delete('/superadmin/business/98')
        ->assertRedirect();

    expect(DB::table('business')->where('id', 98)->exists())->toBeTrue();
    expect((int) DB::table('business')->where('id', 98)->value('is_active'))->toBe(1);
    if (Schema::hasTable('activity_log')) {
        expect(DB::table('activity_log')->where('event', 'negocio_desativado')->where('business_id', 98)->count())->toBe($antes);
    }
});

it('UC-SARD-06 · show de pacote e de assinatura (view inexistente) não responde mais — 405, não 500', function () {
    $user = rdUsuario('rd_superadmin_test', true);

    // A URI segue registrada para o PUT (update); sem o GET, o Laravel responde 405.
    $this->actingAs($user)->get('/superadmin/packages/'.rdPacote())->assertStatus(405);
    $this->actingAs($user)->get('/superadmin/superadmin-subscription/1')->assertStatus(405);
});

it('UC-SARD-07 · o resource /subscription só expõe index e show', function () {
    $user = rdUsuario('rd_superadmin_test', true);

    // store/update/destroy não existem no controller (davam 500): agora o método não é aceito.
    $this->actingAs($user)->post('/subscription')->assertStatus(405);
    $this->actingAs($user)->delete('/subscription/1')->assertStatus(405);
    $this->actingAs($user)->put('/subscription/1')->assertStatus(405);
});

it('UC-SARD-08 · DELETE de outra empresa só desativa: a empresa e as transações dela continuam no banco', function () {
    DB::table('business')->where('id', 99)->update(['is_active' => 1]);
    $user = rdUsuario('rd_superadmin_test', true);
    $transacoesAntes = Schema::hasTable('transactions')
        ? DB::table('transactions')->where('business_id', 99)->count()
        : 0;
    $logAntes = Schema::hasTable('activity_log')
        ? DB::table('activity_log')->where('event', 'negocio_desativado')->where('business_id', 99)->count()
        : 0;

    $this->actingAs($user)
        ->withSession(['user.business_id' => 98, 'user.id' => $user->id])
        ->delete('/superadmin/business/99')
        ->assertRedirect();

    // Antes da B5 a linha de `business` sumia — aqui ela tem de continuar, só inativa.
    expect(DB::table('business')->where('id', 99)->exists())->toBeTrue();
    expect((int) DB::table('business')->where('id', 99)->value('is_active'))->toBe(0);
    if (Schema::hasTable('transactions')) {
        expect(DB::table('transactions')->where('business_id', 99)->count())->toBe($transacoesAntes);
    }
    if (Schema::hasTable('activity_log')) {
        expect(DB::table('activity_log')->where('event', 'negocio_desativado')->where('business_id', 99)->count())->toBe($logAntes + 1);
    }
});
