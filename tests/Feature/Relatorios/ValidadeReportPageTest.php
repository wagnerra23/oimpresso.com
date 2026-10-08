<?php

declare(strict_types=1);
// Cobre UC-RVL-01, UC-RVL-02, UC-RVL-03, UC-RVL-04 (resources/js/Pages/Relatorios/Validade/Index.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EstoqueFixture;

// Saldo por três caminhos: JSON do DataTable da Blade × props da Page × conta direta em purchase_lines, mais a conta à
// mão. O relatório não tem filtro de fornecedor: cada teste cria uma marca própria e filtra por ela. Tenant 98 × 99.

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasColumn('transactions', 'essentials_duration')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->user = $this->usuarioComPermissoes(['stock_report.view', 'access_all_locations'], $this->business);
    $this->versaoInertia = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $this->inertia = ['X-Inertia' => 'true', 'X-Inertia-Version' => $this->versaoInertia, 'X-Requested-With' => 'XMLHttpRequest'];

    $this->actingAs($this->user);
    session(['user.business_id' => $this->business->id, 'user.id' => $this->user->id, 'business.id' => $this->business->id]);
    session(['business.date_format' => 'd/m/Y', 'business.time_format' => 24]);
});

function rvlMarca(int $businessId, int $criador): int
{
    return (int) DB::table('brands')->insertGetId([
        'business_id' => $businessId, 'name' => 'RVL '.uniqid(), 'created_by' => $criador, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** Um lote: compra de um produto da marca, com [comprado, vendido, ajustado, devolvido], validade e lote. */
function rvlLote(int $businessId, int $criador, int $marca, array $qtds, string $validade, string $lote): int
{
    $produto = EstoqueFixture::singleProduct($businessId);
    DB::table('products')->where('id', $produto->productId)->update(['brand_id' => $marca, 'enable_stock' => 1]);
    $compra = DB::table('transactions')->insertGetId([
        'business_id' => $businessId, 'location_id' => EstoqueFixture::locationId($businessId), 'type' => 'purchase', 'status' => 'received',
        'payment_status' => 'paid', 'transaction_date' => '2099-01-01 10:00:00', 'final_total' => 0, 'total_before_tax' => 0,
        'created_by' => $criador, 'essentials_duration' => 0, 'ref_no' => 'RVL-'.uniqid(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    [$comprado, $vendido, $ajustado, $devolvido] = $qtds;

    return DB::table('purchase_lines')->insertGetId([
        'transaction_id' => $compra, 'product_id' => $produto->productId, 'variation_id' => $produto->variations[0]['variation_id'],
        'quantity' => $comprado, 'quantity_sold' => $vendido, 'quantity_adjusted' => $ajustado, 'quantity_returned' => $devolvido,
        'purchase_price' => 1, 'purchase_price_inc_tax' => 1, 'item_tax' => 0, 'exp_date' => $validade, 'lot_number' => $lote,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function rvlPage($teste, array $filtros, int $pagina = 1): array
{
    $r = $teste->withHeaders($teste->inertia)->get('/reports/stock-expiry?'.http_build_query(['tela' => 'nova', 'page' => $pagina] + $filtros));
    $r->assertOk();
    expect($r->json('component'))->toBe('Relatorios/Validade/Index');

    return $r->json('props');
}

test('UC-RVL-01 estoque — saldo = JSON do DataTable da Blade = conta direta; lote zerado fora', function () {
    $marca = rvlMarca($this->business->id, $this->user->id);
    $pl = rvlLote($this->business->id, $this->user->id, $marca, [10, 3, 1, 1], '2099-06-01', 'RVL-A');
    rvlLote($this->business->id, $this->user->id, $marca, [4, 4, 0, 0], '2099-06-02', 'RVL-Z');

    // Caminho 1: o JSON que o DataTable da Blade pede, com as colunas e a ordem do report.js.
    $colunas = [];
    foreach ([['product', 'p.name'], ['sku', 'p.sku'], ['location', 'l.name'], ['stock_left', 'stock_left'], ['lot_number', 'lot_number'], ['exp_date', 'exp_date'], ['mfg_date', 'mfg_date']] as $i => [$d, $n]) {
        $colunas[$i] = ['data' => $d, 'name' => $n, 'searchable' => $d === 'stock_left' ? 'false' : 'true', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']];
    }
    $dt = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->get('/reports/stock-expiry?'.http_build_query(['draw' => 1, 'start' => 0, 'length' => -1, 'columns' => $colunas,
            'order' => [['column' => 5, 'dir' => 'asc']], 'search' => ['value' => '', 'regex' => 'false'], 'brand_id' => $marca]));
    $dt->assertOk();
    expect($dt->json('error'))->toBeNull();
    expect($dt->json('data'))->toHaveCount(1);
    $saldoBlade = (float) (preg_match('/data-orig-value="([^"]*)"/', (string) $dt->json('data.0.stock_left'), $m) ? $m[1] : 'NaN');

    // Caminho 2: a Page.
    $props = rvlPage($this, ['brand_id' => $marca]);
    expect($props['linhas'])->toHaveCount(1);
    expect((float) $props['linhas'][0]['saldo'])->toEqual($saldoBlade);

    // Caminho 3: conta direta no item de compra.
    $item = DB::table('purchase_lines')->where('id', $pl)->first();
    expect((float) $props['linhas'][0]['saldo'])->toEqual((float) ($item->quantity - $item->quantity_sold - $item->quantity_adjusted - $item->quantity_returned));

    // Conta à mão: 10 − 3 − 1 − 1 = 5; o lote RVL-Z (4 − 4 = 0) não aparece.
    expect([(float) $props['linhas'][0]['saldo'], $props['linhas'][0]['lote']])->toBe([5.0, 'RVL-A']);
});

test('UC-RVL-02 — filtro de faixa de validade', function () {
    $marca = rvlMarca($this->business->id, $this->user->id);
    rvlLote($this->business->id, $this->user->id, $marca, [1, 0, 0, 0], now()->addDays(5)->format('Y-m-d'), 'RVL-PERTO');
    rvlLote($this->business->id, $this->user->id, $marca, [1, 0, 0, 0], now()->addYears(2)->format('Y-m-d'), 'RVL-LONGE');

    $semana = collect(rvlPage($this, ['brand_id' => $marca])['faixas'])->firstWhere('nome', __('report.expiring_in_1_week'))['valor'];
    $props = rvlPage($this, ['brand_id' => $marca, 'exp_date_filter' => $semana]);
    expect(array_column($props['linhas'], 'lote'))->toBe(['RVL-PERTO']);
});

test('UC-RVL-03 — 25 por página pela validade crescente; rodapé só da página', function () {
    $marca = rvlMarca($this->business->id, $this->user->id);
    for ($i = 0; $i < 26; $i++) {
        rvlLote($this->business->id, $this->user->id, $marca, [$i + 1, 0, 0, 0], date('Y-m-d', strtotime('2099-01-01 +'.$i.' days')), sprintf('RVL-%02d', $i));
    }

    $p1 = rvlPage($this, ['brand_id' => $marca], 1);
    expect($p1['linhas'])->toHaveCount(25);
    expect($p1['paginacao'])->toMatchArray(['atual' => 1, 'ultima' => 2, 'total' => 26]);
    expect($p1['linhas'][0]['lote'])->toBe('RVL-00');
    expect((float) array_sum(array_column($p1['rodape']['por_unidade'], 'saldo')))->toEqual((float) array_sum(range(1, 25))); // sem o RVL-25

    $p2 = rvlPage($this, ['brand_id' => $marca], 2);
    expect(array_column($p2['linhas'], 'lote'))->toBe(['RVL-25']);
});

test('UC-RVL-04 Tier 0 — lote do negócio 99 não aparece; permissão da Blade', function () {
    $alheio = $this->seededSupportClientTenant();
    expect($alheio->id)->not->toBe($this->business->id);
    $donoAlheio = \App\User::factory()->create(['business_id' => $alheio->id])->id;
    $marcaAlheia = rvlMarca($alheio->id, $donoAlheio);
    rvlLote($alheio->id, $donoAlheio, $marcaAlheia, [5, 0, 0, 0], '2099-06-01', 'RVL-X');

    $props = rvlPage($this, ['brand_id' => $marcaAlheia]);
    expect($props['linhas'])->toBe([]);
    expect(collect($props['marcas'])->pluck('id'))->not->toContain($marcaAlheia);

    // Sem a permissão: 403 na tela nova e na Blade.
    $this->actingAs($this->usuarioComPermissoes([], $this->business));
    $this->withHeaders($this->inertia)->get('/reports/stock-expiry?tela=nova')->assertForbidden();
    $this->withHeaders([])->get('/reports/stock-expiry')->assertForbidden();
});
