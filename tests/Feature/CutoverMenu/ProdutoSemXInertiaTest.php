<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;

/**
 * Cutover pelo menu · thread 01 — a lista de Produtos abre a Page React por padrão
 * (playbook `cutover-menu`, D1 [W] 2026-10-07: "por tela: React vira padrão, Blade só com
 * ?classico=1"). Molde: `UnitController@index`.
 *
 * O menu navega com carga cheia (`<a href>`, sem `X-Inertia`). Antes, `/products` só devolvia
 * a Page com o cabeçalho, então pelo menu caía sempre no Blade.
 *
 * `/products/create` NÃO virou: a Page React não envia preço (`single_dpp`/`single_dsp`/
 * `profit_percent` — `Create.casos.md` §"preço") e a US-PROD-029 ([F] 2026-08-24) protege o
 * cadastro da Larissa. O último caso abaixo trava que o GET comum do novo segue no Blade.
 *
 * Os caminhos que importam:
 *   GET comum                 → Page React
 *   ?classico=1               → Blade
 *   AJAX sem X-Inertia        → JSON do DataTable (a lista Blade continua lendo daqui)
 *   visita Inertia real       → Page (X-Inertia **e** X-Requested-With — §5 2026-09-08)
 *   reload de props deferidas → Page com as props pedidas (antes caía no JSON do DataTable)
 *
 * Tenant semeado do CI (ADR 0358). Nunca biz=4. Pest só no CI / CT 100 (proibicoes.md §Ambiente).
 */
uses(DatabaseTransactions::class);

function psxUsuario(int $bizId, array $permissoes): User
{
    $id = DB::table('users')->insertGetId([
        'first_name' => 'PSX Cutover',
        'username' => 'psx_' . uniqid(),
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

function psxLogin(object $test, User $user): object
{
    session([
        'user.business_id' => (int) $user->business_id,
        'user.id' => $user->id,
        'currency' => ['code' => 'BRL', 'symbol' => 'R$', 'decimal_separator' => ',', 'thousand_separator' => '.'],
    ]);

    return $test->actingAs($user);
}

function psxVersao(): string
{
    $manifest = public_path('build-inertia/manifest.json');

    return file_exists($manifest) ? md5_file($manifest) : '1';
}

/** A tela da thread: rota, componente, Blade e as permissões que a abrem. */
function psxTelas(): array
{
    return [
        'lista' => ['/products', 'Produto/Index', 'product.index', ['product.view', 'access_all_locations']],
    ];
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: requer schema MySQL UltimatePOS (ADR 0358).');
    }
    foreach (['products', 'business', 'business_locations', 'users'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema UltimatePOS ausente ({$t}) — roda na lane MySQL / CT 100.");
        }
    }

    $this->bizId = (int) $this->seededTenant()->id;
});

it('GET comum (como o menu navega) abre a Page React da lista', function () {
    foreach (psxTelas() as $nome => [$rota, $componente, , $permissoes]) {
        $resp = psxLogin($this, psxUsuario($this->bizId, $permissoes))->get($rota);
        $resp->assertOk();
        $resp->assertInertia(fn (AssertableInertia $p) => $p->component($componente, false));
    }
});

it('?classico=1 mantém o Blade da lista', function () {
    foreach (psxTelas() as $nome => [$rota, , $blade, $permissoes]) {
        psxLogin($this, psxUsuario($this->bizId, $permissoes))
            ->get($rota . '?classico=1')
            ->assertOk()
            ->assertViewIs($blade);
    }
});

it('a visita Inertia (X-Inertia + X-Requested-With) recebe a Page — a lista não cai mais no JSON do DataTable', function () {
    foreach (psxTelas() as $nome => [$rota, $componente, , $permissoes]) {
        $resp = psxLogin($this, psxUsuario($this->bizId, $permissoes))->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => psxVersao(),
            'X-Requested-With' => 'XMLHttpRequest',
        ])->get($rota);

        $resp->assertOk();
        $page = json_decode($resp->getContent(), true);
        expect($page)->toBeArray();
        expect($page['component'] ?? null)->toBe($componente);
    }
});

it('o reload das props deferidas da lista devolve as props pedidas, não o DataTable', function () {
    // É a requisição que o `<Deferred>` da Page faz logo depois de abrir: X-Inertia + parcial,
    // com o X-Requested-With que o Inertia sempre manda. Com o `ajax()` antes da decisão ela
    // recebia o JSON do DataTable e a lista ficava no esqueleto.
    $resp = psxLogin($this, psxUsuario($this->bizId, ['product.view', 'access_all_locations']))->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => psxVersao(),
        'X-Inertia-Partial-Component' => 'Produto/Index',
        'X-Inertia-Partial-Data' => 'kpis,rows,categorias',
        'X-Requested-With' => 'XMLHttpRequest',
    ])->get('/products');

    $resp->assertOk();
    $page = json_decode($resp->getContent(), true);
    expect($page)->toBeArray();
    expect($page['component'] ?? null)->toBe('Produto/Index');
    expect($page['props'] ?? [])->toHaveKeys(['kpis', 'rows', 'categorias']);
    // O DataTable responde `{draw, recordsTotal, data}`; a Page nunca tem `recordsTotal`.
    expect($page)->not->toHaveKey('recordsTotal');
});

it('AJAX sem X-Inertia (o DataTable do Blade) continua recebendo o JSON da lista', function () {
    $resp = psxLogin($this, psxUsuario($this->bizId, ['product.view', 'access_all_locations']))->withHeaders([
        'X-Requested-With' => 'XMLHttpRequest',
        'Accept' => 'application/json',
    ])->get('/products?draw=1&start=0&length=10');

    $resp->assertOk();
    $json = json_decode($resp->getContent(), true);
    expect($json)->toBeArray();
    expect($json)->toHaveKeys(['draw', 'recordsTotal', 'data']);
    expect($json)->not->toHaveKey('component');
});

it('o novo produto segue no Blade pelo menu (fora do cutover até o React mandar preço)', function () {
    // Trava a decisão desta thread: virar `/products/create` faria produto nascer sem preço
    // pelo menu (a Page não envia single_dpp/single_dsp/profit_percent). Quando o React do
    // cadastro mandar preço e a US-PROD-029 liberar, este caso muda junto com o controller.
    psxLogin($this, psxUsuario($this->bizId, ['product.create', 'access_all_locations']))
        ->get('/products/create')
        ->assertOk()
        ->assertViewIs('product.create');
});
