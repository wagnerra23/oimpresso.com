<?php

declare(strict_types=1);

use App\User;
use App\Utils\ModuleUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\EstoqueFixture;

/**
 * Casos: Pages/Stock{Adjustment,Transfer}/{Index,Create}.casos.md. Só LEITURA, nenhum caso grava
 * quantidade. Âncora: DOC-RAIZ-ESTOQUE §6 (INV-6) + RUNBOOK §3. Tenants 98 × 99, nunca biz=4.
 * Transporte do browser (§5 2026-09-08): a lista abre por URL `?v=2` sem XHR (a visita XHR cai no
 * ramo DataTables — `Index.casos.md` §Backlog); o form abre por `router.visit`.
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS ausente — roda na lane Estoque · MySQL.');
    }
    $this->biz = (int) $this->seededTenant()->id;
    $this->outro = (int) $this->seededSupportClientTenant()->id;
    $this->locBiz = EstoqueFixture::locationId($this->biz, '-MOVT');
    $this->locOutro = EstoqueFixture::locationId($this->outro, '-MOVT');
    $this->partialMock(ModuleUtil::class, fn ($m) => $m->shouldReceive('isSubscribed')->andReturn(true));
});

function movtLogin(object $test, int $bizId, array $permissoes): void
{
    // user_type/allow_login explícitos: o default é do BANCO, não do model em memória — sem
    // eles o CheckUserLogin aborta 403 antes do controller e os casos de 403 passam por vácuo.
    $user = User::factory()->create(['business_id' => $bizId, 'user_type' => 'user', 'allow_login' => 1]);
    foreach ($permissoes as $p) {
        Permission::findOrCreate($p, 'web');
        $user->givePermissionTo($p);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $test->actingAs($user);
    session(['user.business_id' => $bizId, 'user.id' => $user->id]);
}

/** Página Inertia: 1ª carga (JSON no <script data-page>) ou visita XHR (JSON direto). */
function movtPagina(object $test, string $url, string $componente, bool $xhr): array
{
    $manifest = public_path('build-inertia/manifest.json');
    $headers = $xhr ? ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia-Version' => file_exists($manifest) ? md5_file($manifest) : '1'] : [];
    $r = $test->withHeaders($headers)->get($url);
    $r->assertStatus(200);
    $json = $r->getContent();
    if (! $xhr) {
        preg_match('/<script data-page="[^"]*" type="application\/json">(.*?)<\/script>/s', $json, $m);
        $json = $m[1] ?? 'null';
    }
    $page = json_decode($json, true);
    expect($page['component'] ?? null)->toBe($componente);

    return $page['props'];
}

/** Movimentação SEM linhas (não toca saldo). Devolve id e ref_no marcador. */
function movtTransacao(int $bizId, string $tipo, int $locationId, ?int $parentId = null): array
{
    $ref = 'MOVT-' . strtoupper(bin2hex(random_bytes(4)));
    $id = (int) DB::table('transactions')->insertGetId([
        'business_id' => $bizId, 'type' => $tipo, 'status' => 'final', 'location_id' => $locationId,
        'ref_no' => $ref, 'payment_status' => 'paid', 'transaction_date' => now(), 'final_total' => 0,
        'transfer_parent_id' => $parentId, 'adjustment_type' => $tipo === 'stock_adjustment' ? 'normal' : null,
        'created_by' => EstoqueFixture::userId($bizId), 'essentials_duration' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return ['id' => $id, 'ref' => $ref];
}

it('UC-AJIDX-01 [T0] a lista de ajustes só mostra ajustes do próprio business', function () {
    $meu = movtTransacao($this->biz, 'stock_adjustment', $this->locBiz);
    $alheio = movtTransacao($this->outro, 'stock_adjustment', $this->locOutro);
    movtLogin($this, $this->biz, ['purchase.view', 'access_all_locations']);

    $refs = array_column(movtPagina($this, '/stock-adjustments?v=2', 'StockAdjustment/Index', false)['rows'], 'ref_no');

    expect($refs)->toContain($meu['ref']); // anti-vácuo: a lista não veio vazia por acidente
    expect($refs)->not->toContain($alheio['ref']);
});

it('UC-TRIDX-01 [T0] a lista de transferências só mostra transferências do próprio business', function () {
    $meu = movtTransacao($this->biz, 'sell_transfer', $this->locBiz);
    movtTransacao($this->biz, 'purchase_transfer', EstoqueFixture::locationId($this->biz, '-MOVT2'), $meu['id']);
    $alheio = movtTransacao($this->outro, 'sell_transfer', $this->locOutro);
    movtTransacao($this->outro, 'purchase_transfer', EstoqueFixture::locationId($this->outro, '-MOVT2'), $alheio['id']);
    movtLogin($this, $this->biz, ['purchase.view', 'access_all_locations']);

    $refs = array_column(movtPagina($this, '/stock-transfers?v=2', 'StockTransfer/Index', false)['rows'], 'ref_no');

    expect($refs)->toContain($meu['ref']);
    expect($refs)->not->toContain($alheio['ref']);
});

it('UC-AJIDX-02 e UC-TRIDX-02 sem permissão de compra as duas listas respondem 403', function () {
    movtLogin($this, $this->biz, []);
    $this->get('/stock-adjustments?v=2')->assertStatus(403);
    $this->get('/stock-transfers?v=2')->assertStatus(403);
});

it('UC-AJCRT-01 e UC-TRCRT-01 sem purchase.create os dois formulários respondem 403', function () {
    movtLogin($this, $this->biz, ['purchase.view']);
    $this->get('/stock-adjustments/create')->assertStatus(403);
    $this->get('/stock-transfers/create')->assertStatus(403);
});

it('UC-AJCRT-02 [T0] o formulário de ajuste só oferece filiais do próprio business', function () {
    movtLogin($this, $this->biz, ['purchase.create', 'access_all_locations']);

    $props = movtPagina($this, '/stock-adjustments/create', 'StockAdjustment/Create', true);
    $ids = array_map('intval', array_keys((array) $props['business_locations']));

    expect($ids)->toContain($this->locBiz);
    expect($ids)->not->toContain($this->locOutro);
});

it('UC-TRCRT-02 [T0] origem e destino só oferecem filiais do próprio business, com os 3 status', function () {
    movtLogin($this, $this->biz, ['purchase.create', 'access_all_locations']);

    $props = movtPagina($this, '/stock-transfers/create', 'StockTransfer/Create', true);
    $ids = array_map('intval', array_keys((array) $props['business_locations']));

    expect($ids)->toContain($this->locBiz);
    expect($ids)->not->toContain($this->locOutro);
    expect(array_keys((array) $props['statuses']))->toBe(['pending', 'in_transit', 'completed']);
});
