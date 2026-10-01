<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Contrato da tela Pedido de venda (`GET /sales-order` → `SalesOrderController@index`).
 *
 * UCs: resources/js/Pages/SalesOrder/Index.casos.md (UC-SORD-01..04). Derivados do charter
 * revisado (cowork-inbox/venda-menu/PedidoVenda.charter.md) + do controller real.
 *
 * Tenants: 98 (canônico de teste, ADR 0358) × 99 (adversário, `seededSupportClientTenant()`,
 * criado se faltar). Nunca biz=4.
 *
 * Toda chamada usa o par de headers que o browser manda (Inertia envia X-Inertia E
 * X-Requested-With — §5 2026-09-08: teste que omite um deles mede uma requisição que nunca
 * acontece).
 *
 * Não roda local (proibicoes.md: Pest só no CT 100 / CI). Lane: sells-pest.yml (MySQL).
 */
uses(DatabaseTransactions::class);

/** Versão Inertia igual à do servidor — evita o 409 antes de o controller rodar. */
function sordInertiaVersion(): string
{
    $manifest = public_path('build-inertia/manifest.json');

    return file_exists($manifest) ? md5_file($manifest) : '1';
}

function sordGet(object $test)
{
    return $test->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => sordInertiaVersion(),
        'X-Requested-With' => 'XMLHttpRequest',
    ])->get('/sales-order');
}

/** Props da página, com pré-condição anti-vácuo: a tela certa renderizou. */
function sordProps(object $test): array
{
    $response = sordGet($test);
    $response->assertStatus(200);
    $page = json_decode($response->getContent(), true);

    expect($page['component'] ?? null)->toBe('SalesOrder/Index');

    return $page['props'];
}

function sordUsuario(int $bizId, array $permissoes, bool $admin = false): User
{
    $id = DB::table('users')->insertGetId([
        'first_name' => 'SORD Pedido',
        'username' => 'sord_' . uniqid(),
        'password' => bcrypt('ci'),
        'business_id' => $bizId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $user = User::findOrFail($id);
    foreach ($permissoes as $p) {
        Permission::findOrCreate($p, 'web');
        $user->givePermissionTo($p);
    }
    if ($admin) {
        $role = Role::firstOrCreate(['name' => 'Admin#' . $bizId, 'guard_name' => 'web'], ['business_id' => $bizId]);
        $user->assignRole($role);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

function sordLogin(object $test, User $user): void
{
    $test->actingAs($user);
    session(['user.business_id' => (int) $user->business_id, 'user.id' => $user->id]);
}

function sordPedido(int $bizId, int $userId, string $status): int
{
    return DB::table('transactions')->insertGetId([
        'business_id' => $bizId,
        'created_by' => $userId,
        'type' => 'sales_order',
        'status' => $status,
        'payment_status' => 'due',
        'invoice_no' => 'SORD-' . uniqid(),
        'transaction_date' => now(),
        'total_before_tax' => 10,
        'final_total' => 10,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function sordPosSettings(int $bizId, array $settings): void
{
    DB::table('business')->where('id', $bizId)->update(['pos_settings' => json_encode($settings)]);
}

function sordPutStatus(object $test, int $id, string $status)
{
    return $test->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->put('/update-sales-orders/' . $id . '/status', ['status' => $status]);
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: requer schema MySQL UltimatePOS (ADR 0101).');
    }
    foreach (['transactions', 'business', 'users', 'roles'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema UltimatePOS ausente ({$t}) — roda na lane MySQL / CT 100.");
        }
    }

    $this->bizId = (int) $this->seededTenant()->id;
    $this->outroBizId = (int) $this->seededSupportClientTenant()->id; // cria o 99 se faltar

    // PRÉ-CONDIÇÃO: os dois papéis são empresas DISTINTAS (senão o [T0] é tautológico).
    expect($this->outroBizId)->not->toBe($this->bizId);
});

it('UC-SORD-01 a tela informa se enable_sales_order está ligado no negócio', function () {
    sordLogin($this, sordUsuario($this->bizId, ['so.view_all']));

    sordPosSettings($this->bizId, ['enable_sales_order' => 1]);
    expect(sordProps($this)['salesOrderEnabled'])->toBeTrue();

    sordPosSettings($this->bizId, ['enable_sales_order' => 0]);
    expect(sordProps($this)['salesOrderEnabled'])->toBeFalse();

    // O flag é do business da SESSÃO: ligar no 99 não liga no 98.
    sordPosSettings($this->outroBizId, ['enable_sales_order' => 1]);
    expect(sordProps($this)['salesOrderEnabled'])->toBeFalse();
});

it('UC-SORD-03 render Inertia entrega filtros, permissões e o endpoint legado da lista', function () {
    sordLogin($this, sordUsuario($this->bizId, ['so.view_own']));

    $p = sordProps($this);

    expect(array_keys($p['filters']['statuses']))->toBe(['ordered', 'partial', 'completed']);
    expect($p['permissions']['view_own'])->toBeTrue();
    expect($p['permissions']['view_all'])->toBeFalse();
    expect($p['permissions']['create'])->toBeFalse();
    expect($p['permissions']['edit_status'])->toBeFalse();
    expect($p['urls']['datatable'])->toBe('/sells?sale_type=sales_order');
    expect($p['urls']['updateStatus'])->toBe('/update-sales-orders/{id}/status');
});

it('UC-SORD-04 sem so.view_own, so.view_all nem so.create a tela devolve 403', function () {
    sordLogin($this, sordUsuario($this->bizId, []));

    sordGet($this)->assertStatus(403);
});

it('UC-SORD-02 [T0] mudar status grava só no pedido do próprio business', function () {
    $admin = sordUsuario($this->bizId, ['so.view_all'], true);
    sordLogin($this, $admin);

    // CONTROLE POSITIVO — pedido do 98: o PUT grava e devolve success (a linha muda sem recarregar).
    $meu = sordPedido($this->bizId, $admin->id, 'ordered');
    sordPutStatus($this, $meu, 'partial')->assertStatus(200)->assertJsonPath('success', 1);
    expect(DB::table('transactions')->where('id', $meu)->value('status'))->toBe('partial');

    // CONTRATO — pedido do 99 com o mesmo admin do 98: não encontra e não grava.
    $alheio = sordPedido($this->outroBizId, $admin->id, 'ordered');
    sordPutStatus($this, $alheio, 'completed')->assertStatus(404);
    expect(DB::table('transactions')->where('id', $alheio)->value('status'))->toBe('ordered');
});

it('UC-SORD-02 sem ser admin o status não muda', function () {
    $user = sordUsuario($this->bizId, ['so.view_all']);
    sordLogin($this, $user);

    $pedido = sordPedido($this->bizId, $user->id, 'ordered');
    sordPutStatus($this, $pedido, 'completed')->assertStatus(403);
    expect(DB::table('transactions')->where('id', $pedido)->value('status'))->toBe('ordered');
});
