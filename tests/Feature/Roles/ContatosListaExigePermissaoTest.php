<?php

declare(strict_types=1);

// Tests\TestCase já é aplicado globalmente em tests/Pest.php (uses(TestCase::class)->in('Feature')). NÃO redeclarar aqui.

/**
 * /contacts (React · Cliente/Index) só entrega a lista para quem tem permissão de ver contato.
 *
 * O QUE ESTAVA ERRADO (achado 2026-10-01, PR #8428 da conta demo, DEMO-03 vermelho):
 * ContactController::index() tem dois caminhos. O antigo (AJAX do DataTable) chama
 * indexCustomer()/indexSupplier(), que abortam 403 sem `customer.view|view_own` /
 * `supplier.view|view_own`. O caminho React (flag `mwart.cliente_index`, ligada para todos os
 * tenants em prod — medido 2026-08-26, config/mwart.php) renderiza Cliente/Index e serve as
 * props `customers`/`kpis`/`tab_counts` sem checar permissão nenhuma. Qualquer usuário logado do
 * negócio — inclusive um colaborador que só bate ponto — recebia nome, celular e e-mail dos
 * clientes ao pedir a prop `customers` (carga parcial do Inertia, que é o que o navegador faz).
 *
 * Os headers são os do @inertiajs/core de verdade, incluindo X-Requested-With (§5 2026-09-08:
 * teste que monta requisição que o browser não envia fica verde e a tela quebra em prod).
 *
 * Controle POSITIVO obrigatório: com `customer.view` a mesma requisição devolve o contato. Sem
 * ele, um gate que barrasse todo mundo passaria no negativo.
 *
 * Tenant fictício 98 (ADR 0358). Transação revertida por caso.
 */

use App\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

const CLP_BIZ = 98;

function clpUsuario(array $permissoes): User
{
    $user = User::factory()->create(['business_id' => CLP_BIZ]);
    if ($permissoes !== []) {
        $papel = Role::create(['name' => 'ClpTeste' . uniqid() . '#' . CLP_BIZ, 'business_id' => CLP_BIZ, 'guard_name' => 'web']);
        foreach ($permissoes as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $papel->syncPermissions($permissoes);
        $user->assignRole($papel);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return User::findOrFail($user->id);
}

/** Carga parcial da prop `customers`, com os headers que o navegador manda. */
function clpPedirClientes($test)
{
    $manifest = public_path('build-inertia/manifest.json');
    $versao = file_exists($manifest) ? md5_file($manifest) : '1';

    return $test->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => $versao,
        'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia-Partial-Component' => 'Cliente/Index',
        'X-Inertia-Partial-Data' => 'customers',
        'Accept' => 'text/html, application/xhtml+xml',
    ])->get('/contacts?type=customer');
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Schema UltimatePOS + Spatie exigem MySQL (ADR 0358).');
    }
    if (! DB::table('business')->where('id', CLP_BIZ)->exists()) {
        $this->markTestSkipped('Tenant fictício 98 ausente nesta lane.');
    }
    DB::beginTransaction();
    config()->set('mwart.cliente_index.enabled', true);
    config()->set('mwart.cliente_index.business_ids', []);

    $this->marcador = 'CLP Cliente Sigiloso ' . uniqid();
    DB::table('contacts')->insert([
        'business_id' => CLP_BIZ, 'type' => 'customer', 'name' => $this->marcador,
        'contact_id' => 'CLP' . random_int(1000, 9999), 'mobile' => '00000000000',
        'created_by' => (int) DB::table('users')->where('business_id', CLP_BIZ)->value('id'),
        'is_customer' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
});

afterEach(function () {
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
});

it('NEGATIVO: usuário SEM permissão de contato não recebe a lista de clientes', function () {
    $u = clpUsuario([]);
    expect($u->can('customer.view'))->toBeFalse();
    $this->actingAs($u);
    session(['user.business_id' => CLP_BIZ, 'business.id' => CLP_BIZ]);

    $r = clpPedirClientes($this);

    expect($r->getStatusCode())->toBe(403);
    expect($r->getContent())->not->toContain($this->marcador);
});

it('POSITIVO: com customer.view a mesma requisição devolve o contato', function () {
    $u = clpUsuario(['customer.view']);
    expect($u->can('customer.view'))->toBeTrue();
    $this->actingAs($u);
    session(['user.business_id' => CLP_BIZ, 'business.id' => CLP_BIZ]);

    $r = clpPedirClientes($this);

    expect($r->getStatusCode())->toBe(200);
    expect($r->getContent())->toContain($this->marcador);
});

it('NEGATIVO (casca Blade, flag desligada): sem permissão de contato, /contacts não abre', function () {
    // Com `cliente_index` desligada o index cai na casca Blade, que antes abria 200 para qualquer
    // usuário e expunha nomes dos usuários e grupos de cliente (DEMO-03 do #8428, 2026-10-01).
    config()->set('mwart.cliente_index.enabled', false);
    $u = clpUsuario([]);
    $this->actingAs($u);
    session(['user.business_id' => CLP_BIZ, 'business.id' => CLP_BIZ]);

    expect($this->get('/contacts?type=customer')->getStatusCode())->toBe(403);
});

it('POSITIVO (casca Blade, flag desligada): com customer.view, /contacts abre', function () {
    config()->set('mwart.cliente_index.enabled', false);
    $u = clpUsuario(['customer.view']);
    $this->actingAs($u);
    session(['user.business_id' => CLP_BIZ, 'business.id' => CLP_BIZ]);

    expect($this->get('/contacts?type=customer')->getStatusCode())->toBe(200);
});
