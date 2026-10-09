<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;

/**
 * Cutover pelo menu · thread 05 — o relatório Compra × Venda abre a Page React por padrão
 * (playbook `cutover-menu`, D1 [W] 2026-10-07: "por tela: React vira padrão, Blade só com
 * ?classico=1"). Molde: `UnitController@index`.
 *
 * O menu navega com carga cheia (`<a href>`, sem `X-Inertia`). Antes, `/reports/purchase-sell`
 * só devolvia a Page com `?tela=nova`, então pelo menu caía sempre no Blade.
 *
 * Os caminhos que importam:
 *   GET comum            → Page React
 *   ?classico=1          → Blade
 *   visita Inertia real  → Page (X-Inertia **e** X-Requested-With — §5 2026-09-08)
 *   ?tela=nova           → Page (o opt-in antigo continua valendo)
 *   fetch da Page        → JSON dos totais (X-Requested-With, sem X-Inertia — é assim que a
 *                          Page busca os números, `Relatorios/CompraVenda/Index.tsx`)
 *
 * Representantes (`/reports/sales-representative`) ficou FORA: a Page só tem o resumo, e as 4
 * abas de listagem só existem na Blade. O último caso trava que o GET comum segue no Blade.
 *
 * Tenant semeado do CI (ADR 0358). Nunca biz=4. Pest só no CI / CT 100 (proibicoes.md §Ambiente).
 */
uses(DatabaseTransactions::class);

function rsxUsuario(int $bizId, array $permissoes): User
{
    $id = DB::table('users')->insertGetId([
        'first_name' => 'RSX Cutover',
        'username' => 'rsx_' . uniqid(),
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

function rsxLogin(object $test, User $user): object
{
    session([
        'user.business_id' => (int) $user->business_id,
        'user.id' => $user->id,
        'business.id' => (int) $user->business_id,
        'currency' => ['code' => 'BRL', 'symbol' => 'R$', 'decimal_separator' => ',', 'thousand_separator' => '.'],
    ]);

    return $test->actingAs($user);
}

function rsxVersao(): string
{
    $manifest = public_path('build-inertia/manifest.json');

    return file_exists($manifest) ? md5_file($manifest) : '1';
}

function rsxCompraVenda(object $test): object
{
    return rsxLogin($test, rsxUsuario($test->bizId, ['purchase_n_sell_report.view', 'access_all_locations']));
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

it('GET comum (como o menu navega) abre a Page React do Compra × Venda', function () {
    $resp = rsxCompraVenda($this)->get('/reports/purchase-sell');
    $resp->assertOk();
    $resp->assertInertia(fn (AssertableInertia $p) => $p->component('Relatorios/CompraVenda/Index', false));
});

it('?classico=1 mantém o Blade do Compra × Venda', function () {
    rsxCompraVenda($this)->get('/reports/purchase-sell?classico=1')
        ->assertOk()
        ->assertViewIs('report.purchase_sell');
});

it('a visita Inertia (X-Inertia + X-Requested-With) recebe a Page do Compra × Venda', function () {
    $resp = rsxCompraVenda($this)->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => rsxVersao(),
        'X-Requested-With' => 'XMLHttpRequest',
    ])->get('/reports/purchase-sell');

    $resp->assertOk();
    $page = json_decode($resp->getContent(), true);
    expect($page)->toBeArray();
    expect($page['component'] ?? null)->toBe('Relatorios/CompraVenda/Index');
});

it('?tela=nova (o opt-in antigo) continua abrindo a Page do Compra × Venda', function () {
    $resp = rsxCompraVenda($this)->get('/reports/purchase-sell?tela=nova');
    $resp->assertOk();
    $resp->assertInertia(fn (AssertableInertia $p) => $p->component('Relatorios/CompraVenda/Index', false));
});

it('o fetch da Page (X-Requested-With, sem X-Inertia) continua recebendo o JSON dos totais', function () {
    $resp = rsxCompraVenda($this)->withHeaders([
        'X-Requested-With' => 'XMLHttpRequest',
        'Accept' => 'application/json',
    ])->get('/reports/purchase-sell?' . http_build_query(['start_date' => '2099-04-01', 'end_date' => '2099-04-30', 'location_id' => '']));

    $resp->assertOk();
    $json = json_decode($resp->getContent(), true);
    expect($json)->toBeArray();
    expect($json)->toHaveKeys(['purchase', 'sell']);
    expect($json)->not->toHaveKey('component');
});

it('Representantes segue no Blade pelo menu (fora do cutover até as 4 abas serem portadas)', function () {
    // Trava a decisão desta thread: a Page `Report/SalesRepresentative/Index` só tem o resumo.
    // Virar padrão esconderia as abas de listagem de quem entra pelo menu.
    rsxLogin($this, rsxUsuario($this->bizId, ['sales_representative.view', 'access_all_locations']))
        ->get('/reports/sales-representative')
        ->assertOk()
        ->assertViewIs('report.sales_representative');
});
