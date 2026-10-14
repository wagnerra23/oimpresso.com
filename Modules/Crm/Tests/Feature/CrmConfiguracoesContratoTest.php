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
 * Contrato de `/crm/settings` (Configurações do CRM) — thread Crm/15, F3: Inertia atrás da flag
 * `useV2CrmConfiguracoes`, ligada aqui pelo override `feature-flags.forced_on` (inerte em produção).
 *
 * Os UCs vêm do contrato, não do código:
 *   Modules/Crm/Resources/js/Pages/Crm/Configuracoes/Index.casos.md
 * O lado Blade segue travado pelo CrmConfiguracoesBaselineTest (F2).
 *
 * Tenant 98 (fictício, ADR 0358) e 99 como o "outro negócio". NUNCA biz=4.
 * O `crm_settings` dos dois é guardado antes e devolvido depois de cada caso.
 * ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
 *
 * @see Modules/Crm/Http/Controllers/CrmSettingsController::index()
 */
const CFC_BIZ = 98;
const CFC_OUTRO = 99;
const CFC_ROTA = '/crm/settings';
const CFC_GRAVA = '/crm/update-settings';
const CFC_FLAG = 'useV2CrmConfiguracoes';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: business.crm_settings requer schema MySQL UltimatePOS.');
    }
    if (! Schema::hasTable('business') || ! Schema::hasColumn('business', 'crm_settings')) {
        $this->markTestSkipped('Schema incompleto — business.crm_settings ausente.');
    }
    config(['feature-flags.forced_on' => CFC_FLAG]);
    cfcNegocio(CFC_BIZ);
    cfcNegocio(CFC_OUTRO);
    $this->cfcAntes = DB::table('business')->whereIn('id', [CFC_BIZ, CFC_OUTRO])->pluck('crm_settings', 'id')->all();
});

afterEach(function () {
    foreach ($this->cfcAntes ?? [] as $id => $valor) {
        DB::table('business')->where('id', $id)->update(['crm_settings' => $valor]);
    }
});

/** Mesmo conserto do CrmPainelContratoTest::paiNegocio (`id` é guarded; owner_id obrigatório). */
function cfcNegocio(int $biz): void
{
    if (Business::whereKey($biz)->exists()) {
        return;
    }
    $dono = (int) DB::table('users')->min('id');
    (new Business)->forceFill(['id' => $biz, 'name' => 'Tenant fictício crm '.$biz, 'currency_id' => 1, 'owner_id' => $dono])->save();
}

function cfcUsuario(string $username, bool $admin, int $biz = CFC_BIZ): User
{
    cfcNegocio($biz);
    $user = User::firstOrCreate(['username' => $username], [
        'email' => $username.'@test.local', 'password' => bcrypt('secret'),
        'business_id' => $biz, 'first_name' => 'Cfc', 'last_name' => 'Teste',
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

function cfcGravado(int $biz): array
{
    $raw = DB::table('business')->where('id', $biz)->value('crm_settings');

    return $raw ? (array) json_decode((string) $raw, true) : [];
}

/** POST como o useForm do Inertia manda. */
function cfcPostInertia(User $user, array $dados)
{
    $versao = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());

    return test()->actingAs($user)->from(CFC_ROTA)->post(CFC_GRAVA, $dados, [
        'X-Inertia' => 'true',
        'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia-Version' => (string) $versao,
    ]);
}

it('UC-CRMCFG-01 · com a flag ligada, a tela abre em Inertia com o que está gravado', function () {
    DB::table('business')->where('id', CFC_BIZ)->update(['crm_settings' => json_encode(['order_request_prefix' => 'PFX-', 'enable_order_request' => 1])]);

    $this->actingAs(cfcUsuario('cfc_admin_test', true))
        ->get(CFC_ROTA)
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $p) => $p->component('Crm/Configuracoes/Index', false)
            ->where('configuracoes.ativar_pedido', true)
            ->where('configuracoes.prefixo', 'PFX-'));
});

it('UC-CRMCFG-02 · quem não é Admin# não abre a tela nova', function () {
    $this->actingAs(cfcUsuario('cfc_comum_test', false))->get(CFC_ROTA)->assertForbidden();
});

it('UC-CRMCFG-03 · a gravação da tela nova vale só para o negócio da sessão [T0]', function () {
    $admin = cfcUsuario('cfc_admin_test', true);
    DB::table('business')->where('id', CFC_OUTRO)->update(['crm_settings' => json_encode(['order_request_prefix' => 'VIZ-'])]);

    cfcPostInertia($admin, ['order_request_prefix' => 'NOVO-', 'enable_order_request' => 1, 'business_id' => CFC_OUTRO])
        ->assertRedirect(CFC_ROTA);

    expect(cfcGravado(CFC_BIZ))->toBe(['order_request_prefix' => 'NOVO-', 'enable_order_request' => 1]);
    expect(cfcGravado(CFC_OUTRO))->toBe(['order_request_prefix' => 'VIZ-']);
});

it('UC-CRMCFG-04 · desmarcar (campo ausente) desliga a solicitação de pedido', function () {
    $admin = cfcUsuario('cfc_admin_test', true);
    DB::table('business')->where('id', CFC_BIZ)->update(['crm_settings' => json_encode(['order_request_prefix' => 'A-', 'enable_order_request' => 1])]);

    cfcPostInertia($admin, ['order_request_prefix' => 'A-'])->assertRedirect(CFC_ROTA);

    $gravado = cfcGravado(CFC_BIZ);
    $this->assertArrayNotHasKey('enable_order_request', $gravado, 'o checkbox desmarcado deixou o pedido ligado');
    expect($gravado['order_request_prefix'] ?? null)->toBe('A-');
});

it('UC-CRMCFG-05 · flag desligada, ou ?classico=1 com ela ligada, seguem na Blade', function () {
    $sessao = ['currency' => ['code' => 'BRL', 'symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ',']];
    $admin = cfcUsuario('cfc_admin_test', true);

    $this->actingAs($admin)->withSession($sessao)->get(CFC_ROTA.'?classico=1')
        ->assertOk()->assertViewIs('crm::settings.index');

    config(['feature-flags.forced_on' => '']);
    $this->actingAs($admin)->withSession($sessao)->get(CFC_ROTA)
        ->assertOk()->assertViewIs('crm::settings.index');
});
