<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;
use Modules\Officeimpresso\Http\Controllers\DataController;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

/**
 * Permissão delegável `officeimpresso.clientes.liberar` — separa o "liberar
 * clientes" (gestão das credenciais OAuth do Delphi) do `superadmin`, pra que
 * um funcionário com login próprio possa liberar clientes SEM enxergar o
 * Financeiro (que abre via `|| can('superadmin')`).
 *
 * Invariante central (no-leak): ter a permissão NÃO concede `superadmin` nem
 * `financeiro.*`.
 *
 * NÃO usa RefreshDatabase — UltimatePOS legacy (100+ migrations/triggers não
 * rodam em sqlite). Roda contra DB real (CT 100 / dev). biz=1 (Wagner WR2) —
 * NUNCA biz=4 (ROTA LIVRE prod) — ADR 0101.
 *
 * @see Modules\Officeimpresso\Http\Controllers\ClientController::authorizeLiberar()
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */

defined('PERM_OI_LIBERAR') || define('PERM_OI_LIBERAR', 'officeimpresso.clientes.liberar');

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema MySQL UltimatePOS necessário (ADR 0101).');
    }
});

it('declara officeimpresso.clientes.liberar no user_permissions (assinável na UI de Funções)', function () {
    $permissions = (new DataController())->user_permissions();
    $values = array_column($permissions, 'value');

    expect($values)->toContain(PERM_OI_LIBERAR);
});

it('concede liberar clientes SEM abrir superadmin nem Financeiro (no-leak)', function () {
    $business = Business::first();
    if (! $business) {
        $this->markTestSkipped('Nenhum business — precisa seeder UltimatePOS.');
    }

    Permission::firstOrCreate(['name' => PERM_OI_LIBERAR, 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'financeiro.titulo.aprovar', 'guard_name' => 'web']);

    // User fresco SEM role Admin (senão Gate::before faria bypass de tudo).
    $user = makeOiLiberarTestUser($business->id);
    $user->givePermissionTo(PERM_OI_LIBERAR);

    expect($user->can(PERM_OI_LIBERAR))->toBeTrue();
    expect($user->can('superadmin'))->toBeFalse();
    expect($user->can('financeiro.titulo.aprovar'))->toBeFalse();

    $user->forceDelete();
});

it('ClientController@index barra quem não tem a permissão e libera quem tem', function () {
    $business = Business::first();
    if (! $business) {
        $this->markTestSkipped('Nenhum business — precisa seeder UltimatePOS.');
    }

    Permission::firstOrCreate(['name' => PERM_OI_LIBERAR, 'guard_name' => 'web']);

    // SEM a permissão → 403 (gate do controller).
    $semPerm = makeOiLiberarTestUser($business->id);
    $this->actingAs($semPerm);
    session(['user.business_id' => $business->id]);
    $this->get('/officeimpresso/client')->assertForbidden();
    $semPerm->forceDelete();

    // COM a permissão → passa o gate (não 403; render pode ser 200/redirect).
    $comPerm = makeOiLiberarTestUser($business->id);
    $comPerm->givePermissionTo(PERM_OI_LIBERAR);
    $this->actingAs($comPerm);
    session(['user.business_id' => $business->id]);
    expect($this->get('/officeimpresso/client')->status())->not->toBe(403);
    $comPerm->forceDelete();
});

/**
 * Cria um user de teste SEM nenhuma role (pra o Gate::before não fazer bypass).
 */
function makeOiLiberarTestUser(int $businessId): User
{
    return User::create([
        'business_id' => $businessId,
        'first_name'  => 'OI',
        'surname'     => 'Liberar',
        'username'    => 'oi_liberar_'.$businessId.'_'.uniqid(),
        'email'       => 'oi_liberar_'.$businessId.'_'.uniqid().'@test.local',
        'password'    => bcrypt('test12345'),
        'language'    => 'pt_BR',
    ]);
}
