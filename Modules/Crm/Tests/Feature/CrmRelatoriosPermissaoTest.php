<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(Tests\TestCase::class);

/**
 * Os endpoints de dados dos Relatórios do CRM pedem o mesmo portão da tela — achado C1 da thread Crm/13.
 *
 * Âncora: memory/requisitos/Crm/relatorios-parity.md §Achados C1 + RUNBOOK-relatorios.md §5.
 * A tela `/crm/reports` exige `is_admin` (ReportController::index). Os 4 endpoints ajax que a
 * alimentam não checavam nada: qualquer usuário logado do negócio lia as matrizes por usuário,
 * por contato e as conversões. Decisão do gerente da fila (13/10): mesmo gate `is_admin`, sem
 * trocar o nome da permissão (crm.view_reports × is_admin segue com o [W] — C3).
 *
 * Tenant 98 (fictício, ADR 0358). NUNCA biz=4.
 * ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
 *
 * @see Modules/Crm/Http/Controllers/ReportController
 */
const RELP_BIZ = 98;
const RELP_AJAX = ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'];

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: relatórios do CRM requerem schema MySQL UltimatePOS.');
    }
    foreach (['business', 'users', 'contacts', 'crm_schedules', 'crm_schedule_users', 'roles'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema incompleto — tabela {$t} ausente.");
        }
    }
});

/** Mesmo conserto do CrmPainelContratoTest::paiNegocio — `id` é guarded. */
function relpNegocio(int $biz): void
{
    if (Business::whereKey($biz)->exists()) {
        return;
    }
    $dono = (int) DB::table('users')->min('id');
    (new Business)->forceFill(['id' => $biz, 'name' => 'Tenant fictício crm '.$biz, 'currency_id' => 1, 'owner_id' => $dono])->save();
}

function relpUsuario(string $username, bool $admin): User
{
    relpNegocio(RELP_BIZ);
    $user = User::firstOrCreate(['username' => $username], [
        'email' => $username.'@test.local', 'password' => bcrypt('secret'),
        'business_id' => RELP_BIZ, 'first_name' => 'Relp', 'last_name' => 'Teste',
        'user_type' => 'user', 'allow_login' => 1,
    ]);
    // Sem nenhuma permissão: o portão em teste é o papel Admin#98, não uma permissão.
    $user->syncPermissions([]);
    if ($admin) {
        $role = Role::firstOrCreate(['name' => 'Admin#'.RELP_BIZ, 'guard_name' => 'web'], ['business_id' => RELP_BIZ]);
        $user->syncRoles([$role]);
    } else {
        $user->syncRoles([]);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user->fresh();
}

dataset('relp_endpoints', [
    'acompanhamentos por usuário' => ['/crm/follow-ups-by-user'],
    'acompanhamentos por contato' => ['/crm/follow-ups-by-contact'],
    'conversão de leads' => ['/crm/lead-to-customer-report'],
    'detalhe da conversão' => ['/crm/lead-to-customer-details/{self}'],
]);

it('UC-RELP-01 · quem não é admin do negócio não lê os dados dos relatórios', function (string $rota) {
    $comum = relpUsuario('relp_comum_test', false);

    $this->actingAs($comum)->get(str_replace('{self}', (string) $comum->id, $rota), RELP_AJAX)->assertForbidden();
})->with('relp_endpoints');

it('UC-RELP-02 · o admin do negócio continua lendo os dados dos relatórios', function (string $rota) {
    $admin = relpUsuario('relp_admin_test', true);

    $this->actingAs($admin)->get(str_replace('{self}', (string) $admin->id, $rota), RELP_AJAX)->assertOk();
})->with('relp_endpoints');

it('UC-RELP-03 · a tela segue com o mesmo portão (não-admin 403, admin 200)', function () {
    $this->actingAs(relpUsuario('relp_comum_test', false))->get('/crm/reports')->assertForbidden();
    $this->actingAs(relpUsuario('relp_admin_test', true))
        ->withSession(['currency' => ['code' => 'BRL', 'symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ',']])
        ->get('/crm/reports')
        ->assertOk();
});
