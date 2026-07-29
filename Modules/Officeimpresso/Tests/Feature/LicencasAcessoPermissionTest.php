<?php

declare(strict_types=1);

use App\User;
use Illuminate\Support\Facades\DB;
use Modules\Officeimpresso\Http\Controllers\DataController;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

/**
 * Autorização da gestão de licenças desktop (Officeimpresso).
 *
 * Contexto (2026-07-29): o grupo de rotas /officeimpresso/* pedia apenas
 * `auth` — o menu escondia os links, mas QUALQUER usuário autenticado, de
 * qualquer business, abria a lista de máquinas de TODOS os clientes e podia
 * bloquear/liberar o Delphi de qualquer um deles pela URL direta. Esconder
 * link não é autorização.
 *
 * Ao mesmo tempo o suporte (que precisa ver a máquina do cliente pra dar
 * assistência) não enxergava nada, porque o menu só era montado pra
 * `superadmin`. Este teste trava os dois lados.
 *
 * Níveis:
 *   - `officeimpresso.access` ............... leitura de todas as empresas
 *   - `officeimpresso.licencas.gerenciar` ... liberar/bloquear máquina
 *   - `superadmin` .......................... empresa inteira + exclusão
 *
 * NÃO usa RefreshDatabase — UltimatePOS legacy (100+ migrations/triggers não
 * rodam em sqlite). Roda contra DB real (CT 100 / dev). biz=1 (Wagner WR2) —
 * NUNCA biz=4 (ROTA LIVRE prod) — ADR 0101.
 *
 * @see Modules\Officeimpresso\Http\Controllers\LicencaComputadorController::authorizeAccess()
 * @see Modules\Officeimpresso\Tests\Feature\ClientesLiberarPermissionTest
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */

defined('PERM_OI_ACCESS') || define('PERM_OI_ACCESS', 'officeimpresso.access');
defined('PERM_OI_GERENCIAR') || define('PERM_OI_GERENCIAR', 'officeimpresso.licencas.gerenciar');

// ID que não existe: o gate roda ANTES do service, então o caso "com permissão"
// atravessa a guarda e morre no findOrFail (try/catch → redirect), sem escrever
// em nenhuma licença real.
defined('LICENCA_INEXISTENTE') || define('LICENCA_INEXISTENTE', 999999999);

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema MySQL UltimatePOS necessário (ADR 0101).');
    }
});

it('declara as duas permissões no user_permissions (assináveis na UI de Funções)', function () {
    $values = array_column((new DataController())->user_permissions(), 'value');

    expect($values)->toContain(PERM_OI_ACCESS)
        ->and($values)->toContain(PERM_OI_GERENCIAR);
});

it('concede acesso às licenças SEM abrir superadmin nem Financeiro (no-leak)', function () {
    $business = $this->seededTenant();

    Permission::firstOrCreate(['name' => PERM_OI_ACCESS, 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'financeiro.titulo.aprovar', 'guard_name' => 'web']);

    $user = makeOiAcessoTestUser($business->id);
    $user->givePermissionTo(PERM_OI_ACCESS);

    expect($user->can(PERM_OI_ACCESS))->toBeTrue()
        ->and($user->can('superadmin'))->toBeFalse()
        ->and($user->can('financeiro.titulo.aprovar'))->toBeFalse()
        // Ver NÃO dá direito de mexer.
        ->and($user->can(PERM_OI_GERENCIAR))->toBeFalse();

    $user->forceDelete();
});

it('barra usuário autenticado sem permissão nas telas de licença e log', function () {
    $business = $this->seededTenant();

    $user = makeOiAcessoTestUser($business->id);
    $this->actingAs($user);
    session(['user.business_id' => $business->id]);

    // Regressão do buraco: antes destas guardas, um autenticado qualquer abria
    // as três telas — inclusive a visão cross-empresa.
    $this->get('/officeimpresso/licenca_computador')->assertForbidden();
    $this->get('/officeimpresso/licenca_log')->assertForbidden();
    $this->get('/officeimpresso/businessall')->assertForbidden();

    $user->forceDelete();
});

it('libera as telas de leitura pra quem tem officeimpresso.access (caso do suporte)', function () {
    $business = $this->seededTenant();

    Permission::firstOrCreate(['name' => PERM_OI_ACCESS, 'guard_name' => 'web']);

    $user = makeOiAcessoTestUser($business->id);
    $user->givePermissionTo(PERM_OI_ACCESS);
    $this->actingAs($user);
    session(['user.business_id' => $business->id]);

    expect($this->get('/officeimpresso/licenca_computador')->status())->not->toBe(403)
        ->and($this->get('/officeimpresso/licenca_log')->status())->not->toBe(403)
        ->and($this->get('/officeimpresso/businessall')->status())->not->toBe(403);

    $user->forceDelete();
});

it('exige licencas.gerenciar pra liberar/bloquear máquina — ver não basta', function () {
    $business = $this->seededTenant();

    Permission::firstOrCreate(['name' => PERM_OI_ACCESS, 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => PERM_OI_GERENCIAR, 'guard_name' => 'web']);

    // Só leitura → 403 no toggle.
    $leitor = makeOiAcessoTestUser($business->id);
    $leitor->givePermissionTo(PERM_OI_ACCESS);
    $this->actingAs($leitor);
    session(['user.business_id' => $business->id]);
    $this->get('/officeimpresso/licenca_computador/' . LICENCA_INEXISTENTE . '/toggle-block')
        ->assertForbidden();
    $leitor->forceDelete();

    // Com a permissão de suporte → atravessa o gate.
    $suporte = makeOiAcessoTestUser($business->id);
    $suporte->givePermissionTo(PERM_OI_GERENCIAR);
    $this->actingAs($suporte);
    session(['user.business_id' => $business->id]);
    expect($this->get('/officeimpresso/licenca_computador/' . LICENCA_INEXISTENTE . '/toggle-block')->status())
        ->not->toBe(403);
    $suporte->forceDelete();
});

it('mantém bloqueio da EMPRESA inteira como superadmin-only', function () {
    $business = $this->seededTenant();

    Permission::firstOrCreate(['name' => PERM_OI_GERENCIAR, 'guard_name' => 'web']);

    // Suporte pode mexer em máquina individual, mas não derrubar o cliente todo.
    $suporte = makeOiAcessoTestUser($business->id);
    $suporte->givePermissionTo(PERM_OI_GERENCIAR);
    $this->actingAs($suporte);
    session(['user.business_id' => $business->id]);

    $this->get('/officeimpresso/licenca_computador/businessbloqueado/' . $business->id)
        ->assertForbidden();

    $suporte->forceDelete();
});

/**
 * Cria um user de teste SEM nenhuma role (pra o Gate::before não fazer bypass).
 */
function makeOiAcessoTestUser(int $businessId): User
{
    return User::create([
        'business_id' => $businessId,
        'first_name'  => 'OI',
        'surname'     => 'Acesso',
        'username'    => 'oi_acesso_'.$businessId.'_'.uniqid(),
        'email'       => 'oi_acesso_'.$businessId.'_'.uniqid().'@test.local',
        'password'    => bcrypt('test12345'),
        'language'    => 'pt_BR',
    ]);
}
