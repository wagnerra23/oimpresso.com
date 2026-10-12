<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;

/**
 * Cutover pelo menu · thread 04 — Transferências e Ajustes de estoque (lista e novo) abrem a
 * Page React por padrão
 * (playbook `cutover-menu`, D1 [W] 2026-10-07: "por tela: React vira padrão, Blade só com
 * ?classico=1"). Molde: `UnitController@index`.
 *
 * O menu navega com carga cheia (`<a href>`, sem `X-Inertia`). Antes, as 4 rotas só devolviam a
 * Page com o cabeçalho ou com `?v=2`, então pelo menu caíam sempre no Blade. E a lista recebia o
 * JSON do DataTable na visita Inertia (filtro da Page): o `ajax()` vinha antes do `X-Inertia`.
 *
 * Os caminhos que importam, por tela:
 *   GET comum            → Page React
 *   ?classico=1          → Blade
 *   visita Inertia real  → Page (X-Inertia **e** X-Requested-With — §5 2026-09-08)
 *   ?v=2                 → Page (o opt-in antigo continua valendo)
 * e, nas listas, o AJAX sem X-Inertia (DataTable do Blade) → JSON.
 *
 * Tenant semeado do CI (ADR 0358). Nunca biz=4. Pest só no CI / CT 100 (proibicoes.md §Ambiente).
 */
uses(DatabaseTransactions::class);

function esxUsuario(int $bizId, array $permissoes): User
{
    $id = DB::table('users')->insertGetId([
        'first_name' => 'ESX Cutover',
        'username' => 'esx_' . uniqid(),
        'password' => bcrypt('ci'),
        'business_id' => $bizId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $user = User::findOrFail($id);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    foreach ($permissoes as $p) {
        Permission::findOrCreate($p, 'web');
        $user->givePermissionTo($p);
    }
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

function esxLogin(object $test, User $user): object
{
    session([
        'user.business_id' => (int) $user->business_id,
        'user.id' => $user->id,
        'currency' => ['code' => 'BRL', 'symbol' => 'R$', 'decimal_separator' => ',', 'thousand_separator' => '.'],
    ]);

    return $test->actingAs($user);
}

function esxVersao(): string
{
    $manifest = public_path('build-inertia/manifest.json');

    return file_exists($manifest) ? md5_file($manifest) : '1';
}

/** As 4 telas: rota, componente, Blade e as permissões que abrem cada uma. */
function esxTelas(): array
{
    return [
        'transferências' => ['/stock-transfers', 'StockTransfer/Index', 'stock_transfer.index', ['purchase.view', 'access_all_locations']],
        'nova transferência' => ['/stock-transfers/create', 'StockTransfer/Create', 'stock_transfer.create', ['purchase.create', 'access_all_locations']],
        'ajustes' => ['/stock-adjustments', 'StockAdjustment/Index', 'stock_adjustment.index', ['purchase.view', 'access_all_locations']],
        'novo ajuste' => ['/stock-adjustments/create', 'StockAdjustment/Create', 'stock_adjustment.create', ['purchase.create', 'access_all_locations']],
    ];
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: requer schema MySQL UltimatePOS (ADR 0358).');
    }
    foreach (['transactions', 'business', 'business_locations', 'users'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema UltimatePOS ausente ({$t}) — roda na lane MySQL / CT 100.");
        }
    }

    $this->bizId = (int) $this->seededTenant()->id;
});

it('GET comum (como o menu navega) abre a Page React das 4 telas', function () {
    foreach (esxTelas() as $nome => [$rota, $componente, , $permissoes]) {
        $resp = esxLogin($this, esxUsuario($this->bizId, $permissoes))->get($rota);
        $resp->assertOk();
        $resp->assertInertia(fn (AssertableInertia $p) => $p->component($componente, false));
    }
});

it('?classico=1 mantém o Blade das 4 telas', function () {
    foreach (esxTelas() as $nome => [$rota, , $blade, $permissoes]) {
        esxLogin($this, esxUsuario($this->bizId, $permissoes))
            ->get($rota . '?classico=1')
            ->assertOk()
            ->assertViewIs($blade);
    }
});

it('a visita Inertia (X-Inertia + X-Requested-With) recebe a Page — as listas não caem mais no JSON do DataTable', function () {
    foreach (esxTelas() as $nome => [$rota, $componente, , $permissoes]) {
        $resp = esxLogin($this, esxUsuario($this->bizId, $permissoes))->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => esxVersao(),
            'X-Requested-With' => 'XMLHttpRequest',
        ])->get($rota);

        $resp->assertOk();
        $page = json_decode($resp->getContent(), true);
        expect($page)->toBeArray();
        expect($page['component'] ?? null)->toBe($componente);
    }
});

it('?v=2 (o opt-in antigo) continua abrindo a Page das 4 telas', function () {
    foreach (esxTelas() as $nome => [$rota, $componente, , $permissoes]) {
        $resp = esxLogin($this, esxUsuario($this->bizId, $permissoes))->get($rota . '?v=2');
        $resp->assertOk();
        $resp->assertInertia(fn (AssertableInertia $p) => $p->component($componente, false));
    }
});

it('AJAX sem X-Inertia (o DataTable do Blade) continua recebendo o JSON das duas listas', function () {
    foreach (['/stock-transfers', '/stock-adjustments'] as $rota) {
        $resp = esxLogin($this, esxUsuario($this->bizId, ['purchase.view', 'access_all_locations']))->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])->get($rota . '?draw=1&start=0&length=10');

        $resp->assertOk();
        $json = json_decode($resp->getContent(), true);
        expect($json)->toBeArray();
        expect($json)->toHaveKeys(['draw', 'recordsTotal', 'data']);
        expect($json)->not->toHaveKey('component');
    }
});
