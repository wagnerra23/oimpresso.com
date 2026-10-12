<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;

/**
 * Cutover pelo menu · thread 03 — Compras (lista e nova) abrem a Page React por padrão
 * (playbook `cutover-menu`, D1 [W] 2026-10-07: "por tela: React vira padrão, Blade só com
 * ?classico=1"). Molde: `UnitController@index`.
 *
 * O menu navega com carga cheia (`<a href>`, sem `X-Inertia`). Antes, `/purchases` e
 * `/purchases/create` só devolviam a Page com o cabeçalho ou com `?v=2`, então pelo menu caíam
 * sempre no Blade.
 *
 * Os caminhos que importam, por tela:
 *   GET comum            → Page React
 *   ?classico=1          → Blade
 *   visita Inertia real  → Page (X-Inertia **e** X-Requested-With — §5 2026-09-08)
 *   ?v=2                 → Page (o opt-in antigo continua valendo)
 * e, na lista, o AJAX sem X-Inertia (DataTable do Blade) → JSON.
 *
 * @covers-uc UC-PURIDX-01  o dual path da lista, agora com React por padrão
 * @covers-uc UC-PURCRE-01  o dual path da nova compra, agora com React por padrão
 *
 * Tenant semeado do CI (ADR 0358). Nunca biz=4. Pest só no CI / CT 100 (proibicoes.md §Ambiente).
 */
uses(DatabaseTransactions::class);

function csxUsuario(int $bizId, array $permissoes): User
{
    $id = DB::table('users')->insertGetId([
        'first_name' => 'CSX Cutover',
        'username' => 'csx_' . uniqid(),
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

function csxLogin(object $test, User $user): object
{
    session([
        'user.business_id' => (int) $user->business_id,
        'user.id' => $user->id,
        'currency' => ['code' => 'BRL', 'symbol' => 'R$', 'decimal_separator' => ',', 'thousand_separator' => '.'],
    ]);

    return $test->actingAs($user);
}

function csxVersao(): string
{
    $manifest = public_path('build-inertia/manifest.json');

    return file_exists($manifest) ? md5_file($manifest) : '1';
}

/** As 2 telas: rota, componente, Blade e as permissões que abrem cada uma. */
function csxTelas(): array
{
    return [
        'lista' => ['/purchases', 'Purchase/Index', 'purchase.index', ['purchase.view', 'access_all_locations']],
        'nova' => ['/purchases/create', 'Purchase/Create', 'purchase.create', ['purchase.create', 'access_all_locations']],
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

it('GET comum (como o menu navega) abre a Page React da lista e da nova compra', function () {
    foreach (csxTelas() as $nome => [$rota, $componente, , $permissoes]) {
        $resp = csxLogin($this, csxUsuario($this->bizId, $permissoes))->get($rota);
        $resp->assertOk();
        $resp->assertInertia(fn (AssertableInertia $p) => $p->component($componente, false));
    }
});

it('?classico=1 mantém o Blade da lista e da nova compra', function () {
    foreach (csxTelas() as $nome => [$rota, , $blade, $permissoes]) {
        csxLogin($this, csxUsuario($this->bizId, $permissoes))
            ->get($rota . '?classico=1')
            ->assertOk()
            ->assertViewIs($blade);
    }
});

it('a visita Inertia (X-Inertia + X-Requested-With) recebe a Page das duas telas', function () {
    foreach (csxTelas() as $nome => [$rota, $componente, , $permissoes]) {
        $resp = csxLogin($this, csxUsuario($this->bizId, $permissoes))->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => csxVersao(),
            'X-Requested-With' => 'XMLHttpRequest',
        ])->get($rota);

        $resp->assertOk();
        $page = json_decode($resp->getContent(), true);
        expect($page)->toBeArray();
        expect($page['component'] ?? null)->toBe($componente);
    }
});

it('?v=2 (o opt-in antigo) continua abrindo a Page das duas telas', function () {
    foreach (csxTelas() as $nome => [$rota, $componente, , $permissoes]) {
        $resp = csxLogin($this, csxUsuario($this->bizId, $permissoes))->get($rota . '?v=2');
        $resp->assertOk();
        $resp->assertInertia(fn (AssertableInertia $p) => $p->component($componente, false));
    }
});

it('AJAX sem X-Inertia (o DataTable do Blade) continua recebendo o JSON da lista', function () {
    $resp = csxLogin($this, csxUsuario($this->bizId, ['purchase.view', 'access_all_locations']))->withHeaders([
        'X-Requested-With' => 'XMLHttpRequest',
        'Accept' => 'application/json',
    ])->get('/purchases?draw=1&start=0&length=10');

    $resp->assertOk();
    $json = json_decode($resp->getContent(), true);
    expect($json)->toBeArray();
    expect($json)->toHaveKeys(['draw', 'recordsTotal', 'data']);
    expect($json)->not->toHaveKey('component');
});
