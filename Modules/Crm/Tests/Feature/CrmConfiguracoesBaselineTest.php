<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(Tests\TestCase::class);

/**
 * Baseline da Blade `/crm/settings` (Configurações do CRM) ANTES da tela React — thread Crm/15, F2.
 *
 * Trava o que a Blade mostra e grava hoje; a F3 (Page atrás da flag) tem de manter cada linha.
 * As linhas vêm do mapa de paridade, não do código:
 *   memory/requisitos/Crm/configuracoes-parity.md (linhas 1 a 5)
 *
 * Tenant 98 (fictício, ADR 0358) e 99 como o "outro negócio". NUNCA biz=4.
 * O `crm_settings` dos dois é guardado antes e devolvido depois de cada caso.
 * ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
 *
 * @see Modules/Crm/Http/Controllers/CrmSettingsController::index()
 * @see Modules/Crm/Http/Controllers/CrmSettingsController::updateSettings()
 */
const CFB_BIZ = 98;
const CFB_OUTRO = 99;
const CFB_ROTA = '/crm/settings';
const CFB_GRAVA = '/crm/update-settings';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: business.crm_settings requer schema MySQL UltimatePOS.');
    }
    if (! Schema::hasTable('business') || ! Schema::hasColumn('business', 'crm_settings')) {
        $this->markTestSkipped('Schema incompleto — business.crm_settings ausente.');
    }
    cfbNegocio(CFB_BIZ);
    cfbNegocio(CFB_OUTRO);
    $this->cfbAntes = DB::table('business')->whereIn('id', [CFB_BIZ, CFB_OUTRO])->pluck('crm_settings', 'id')->all();
});

afterEach(function () {
    foreach ($this->cfbAntes ?? [] as $id => $valor) {
        DB::table('business')->where('id', $id)->update(['crm_settings' => $valor]);
    }
});

/** Mesmo conserto do CrmPainelContratoTest::paiNegocio (`id` é guarded; owner_id obrigatório). */
function cfbNegocio(int $biz): void
{
    if (Business::whereKey($biz)->exists()) {
        return;
    }
    $dono = (int) DB::table('users')->min('id');
    (new Business)->forceFill(['id' => $biz, 'name' => 'Tenant fictício crm '.$biz, 'currency_id' => 1, 'owner_id' => $dono])->save();
}

function cfbUsuario(string $username, bool $admin, int $biz = CFB_BIZ): User
{
    cfbNegocio($biz);
    // `user_type`/`allow_login` explícitos: o CheckUserLogin barra o model recém-criado sem eles.
    $user = User::firstOrCreate(['username' => $username], [
        'email' => $username.'@test.local', 'password' => bcrypt('secret'),
        'business_id' => $biz, 'first_name' => 'Cfb', 'last_name' => 'Teste',
        'user_type' => 'user', 'allow_login' => 1,
    ]);
    $user->syncPermissions([]);
    if ($admin) {
        $role = Role::firstOrCreate(['name' => 'Admin#'.$biz, 'guard_name' => 'web'], ['business_id' => $biz]);
        $user->syncRoles([$role]);
    } else {
        $user->syncRoles([]);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user->fresh();
}

function cfbGravado(int $biz): array
{
    $raw = DB::table('business')->where('id', $biz)->value('crm_settings');

    return $raw ? (array) json_decode((string) $raw, true) : [];
}

it('BASE-CFG-01 · quem não é Admin# do negócio recebe 403 ao abrir e ao gravar', function () {
    $comum = cfbUsuario('cfb_comum_test', false);
    DB::table('business')->where('id', CFB_BIZ)->update(['crm_settings' => json_encode(['order_request_prefix' => 'ANTES'])]);

    $this->actingAs($comum)->get(CFB_ROTA)->assertForbidden();
    $this->actingAs($comum)->post(CFB_GRAVA, ['order_request_prefix' => 'INVADIU', 'enable_order_request' => 1])->assertForbidden();

    expect(cfbGravado(CFB_BIZ))->toBe(['order_request_prefix' => 'ANTES']);
});

it('BASE-CFG-02 · grava só no negócio da sessão, ignorando business_id no corpo [T0]', function () {
    $admin = cfbUsuario('cfb_admin_test', true);
    DB::table('business')->where('id', CFB_OUTRO)->update(['crm_settings' => json_encode(['order_request_prefix' => 'VIZ-'])]);

    $this->actingAs($admin)
        ->post(CFB_GRAVA, ['order_request_prefix' => 'SO-', 'enable_order_request' => 1, 'business_id' => CFB_OUTRO])
        ->assertRedirect();

    expect(cfbGravado(CFB_BIZ))->toBe(['order_request_prefix' => 'SO-', 'enable_order_request' => 1]);
    expect(cfbGravado(CFB_OUTRO))->toBe(['order_request_prefix' => 'VIZ-']);
});

it('BASE-CFG-03 · checkbox ausente grava desligado, e o JSON fica só com as chaves do form', function () {
    $admin = cfbUsuario('cfb_admin_test', true);
    DB::table('business')->where('id', CFB_BIZ)->update(['crm_settings' => json_encode(['order_request_prefix' => 'X-', 'enable_order_request' => 1])]);

    $this->actingAs($admin)->post(CFB_GRAVA, ['order_request_prefix' => 'PED-'])->assertRedirect();

    expect(cfbGravado(CFB_BIZ))->toBe(['order_request_prefix' => 'PED-']);
});

it('BASE-CFG-04 · o Admin# abre a Blade com os valores gravados', function () {
    $admin = cfbUsuario('cfb_admin_test', true);
    DB::table('business')->where('id', CFB_BIZ)->update(['crm_settings' => json_encode(['order_request_prefix' => 'PFX98-', 'enable_order_request' => 1])]);

    $this->actingAs($admin)
        ->withSession(['currency' => ['code' => 'BRL', 'symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ',']])
        ->get(CFB_ROTA)
        ->assertOk()
        ->assertViewIs('crm::settings.index')
        ->assertSee('PFX98-', false);
});
