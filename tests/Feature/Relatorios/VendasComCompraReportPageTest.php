<?php

declare(strict_types=1);
// Cobre UC-RVC-01, UC-RVC-02, UC-RVC-03, UC-RVC-04 (resources/js/Pages/Relatorios/VendasComCompra/Index.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EstoqueFixture;

// Quantidade por três caminhos: JSON do DataTable da Blade × props da Page × conta direta em
// transaction_sell_lines_purchase_lines. Cliente próprio do teste (filtro da Blade). Tenant 98 × 99 (ADR 0358).

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasColumn('transactions', 'essentials_duration')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->user = $this->usuarioComPermissoes(['purchase_n_sell_report.view', 'access_all_locations'], $this->business);
    $this->versaoInertia = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $this->inertia = ['X-Inertia' => 'true', 'X-Inertia-Version' => $this->versaoInertia, 'X-Requested-With' => 'XMLHttpRequest'];

    $this->actingAs($this->user);
    session(['user.business_id' => $this->business->id, 'user.id' => $this->user->id, 'business.id' => $this->business->id]);
    session(['business.date_format' => 'd/m/Y', 'business.time_format' => 24]);
});

function rvcContato(int $businessId, int $criador, string $tipo): int
{
    return DB::table('contacts')->insertGetId([
        'business_id' => $businessId, 'type' => $tipo, 'name' => 'RVC '.uniqid(), 'mobile' => '0',
        'created_by' => $criador, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

/**
 * Uma venda de um item ao cliente, abastecida pelas origens [ref, tipo da compra, quantidade]. Devolve o id do item.
 */
function rvcVenda(int $businessId, int $criador, int $cliente, string $numero, string $data, array $origens, string $status = 'final'): int
{
    $produto = EstoqueFixture::singleProduct($businessId);
    $variacao = $produto->variations[0]['variation_id'];
    $local = EstoqueFixture::locationId($businessId);
    $fornecedor = rvcContato($businessId, $criador, 'supplier');
    $venda = DB::table('transactions')->insertGetId([
        'business_id' => $businessId, 'location_id' => $local, 'type' => 'sell', 'status' => $status, 'payment_status' => 'paid',
        'contact_id' => $cliente, 'transaction_date' => $data, 'final_total' => 0, 'total_before_tax' => 0, 'created_by' => $criador,
        'essentials_duration' => 0, 'invoice_no' => $numero, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $item = DB::table('transaction_sell_lines')->insertGetId([
        'transaction_id' => $venda, 'product_id' => $produto->productId, 'variation_id' => $variacao,
        'quantity' => array_sum(array_column($origens, 2)), 'quantity_returned' => 0, 'unit_price' => 1, 'unit_price_inc_tax' => 1,
        'unit_price_before_discount' => 1, 'item_tax' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    foreach ($origens as [$ref, $tipo, $qtd]) {
        $compra = DB::table('transactions')->insertGetId([
            'business_id' => $businessId, 'location_id' => $local, 'type' => $tipo, 'status' => 'received', 'payment_status' => 'paid',
            'contact_id' => $fornecedor, 'transaction_date' => '2099-01-01 10:00:00', 'final_total' => 0, 'total_before_tax' => 0,
            'created_by' => $criador, 'essentials_duration' => 0, 'ref_no' => $ref, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $linhaCompra = DB::table('purchase_lines')->insertGetId([
            'transaction_id' => $compra, 'product_id' => $produto->productId, 'variation_id' => $variacao, 'quantity' => $qtd,
            'purchase_price' => 1, 'purchase_price_inc_tax' => 1, 'item_tax' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('transaction_sell_lines_purchase_lines')->insert([
            'sell_line_id' => $item, 'purchase_line_id' => $linhaCompra, 'quantity' => $qtd, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    return $item;
}

function rvcPage($teste, int $cliente, int $pagina = 1): array
{
    $r = $teste->withHeaders($teste->inertia)->get('/reports/product-sell-report-with-purchase?'.http_build_query(['tela' => 'nova', 'customer_id' => $cliente, 'page' => $pagina]));
    $r->assertOk();
    expect($r->json('component'))->toBe('Relatorios/VendasComCompra/Index');

    return $r->json('props');
}

test('UC-RVC-01 — quantidade por compra de origem = JSON do DataTable da Blade = conta direta; rascunho fora', function () {
    $cliente = rvcContato($this->business->id, $this->user->id, 'customer');
    $item = rvcVenda($this->business->id, $this->user->id, $cliente, 'RVC-A-'.uniqid(), '2099-12-05 10:00:00', [['RVC-C1', 'purchase', 3], ['RVC-C2', 'purchase', 2]]);
    rvcVenda($this->business->id, $this->user->id, $cliente, 'RVC-R-'.uniqid(), '2099-12-06 10:00:00', [['RVC-C3', 'purchase', 9]], 'draft');

    // Caminho 1: o JSON que o DataTable da Blade pede, com as colunas e a ordem do report.js.
    $colunas = [];
    foreach ([['product_name', 'p.name'], ['sub_sku', 'v.sub_sku'], ['customer', 'c.name'], ['invoice_no', 't.invoice_no'], ['transaction_date', 't.transaction_date'], ['ref_no', 'purchase.ref_no'], ['lot_number', 'pl.lot_number'], ['supplier_name', 'supplier.name'], ['purchase_quantity', 'tspl.quantity']] as $i => [$d, $n]) {
        $colunas[$i] = ['data' => $d, 'name' => $n, 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']];
    }
    $dt = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->get('/reports/product-sell-report-with-purchase?'.http_build_query(['draw' => 1, 'start' => 0, 'length' => -1, 'columns' => $colunas,
            'order' => [['column' => 4, 'dir' => 'desc']], 'search' => ['value' => '', 'regex' => 'false'], 'customer_id' => $cliente]));
    $dt->assertOk();
    expect($dt->json('error'))->toBeNull();
    $blade = collect($dt->json('data'))->mapWithKeys(fn ($r) => [trim(strip_tags((string) $r['ref_no'])) => (float) (preg_match('/data-orig-value="([^"]*)"/', (string) $r['purchase_quantity'], $m) ? $m[1] : 'NaN')])->sortKeys()->all();

    // Caminho 2: a Page.
    $props = rvcPage($this, $cliente);
    $page = collect($props['linhas'])->mapWithKeys(fn ($l) => [$l['compra'] => (float) $l['quantidade']])->sortKeys()->all();

    // Caminho 3: conta direta nos vínculos do item.
    $direto = (float) DB::table('transaction_sell_lines_purchase_lines')->where('sell_line_id', $item)->sum('quantity');

    expect($page)->toBe($blade);
    // Conta à mão: 3 da RVC-C1 + 2 da RVC-C2 = 5; a venda em rascunho (9) não entra.
    expect($page)->toBe(['RVC-C1' => 3.0, 'RVC-C2' => 2.0]);
    expect(array_sum($page))->toEqual($direto);
});

test('UC-RVC-02 — estoque inicial no lugar da ref. da compra', function () {
    $cliente = rvcContato($this->business->id, $this->user->id, 'customer');
    rvcVenda($this->business->id, $this->user->id, $cliente, 'RVC-E-'.uniqid(), '2099-12-05 10:00:00', [['', 'opening_stock', 4]]);

    $linha = rvcPage($this, $cliente)['linhas'][0];
    expect($linha['estoque_inicial'])->toBeTrue();
    expect($linha['compra'])->toBe(__('lang_v1.opening_stock'));
    expect((float) $linha['quantidade'])->toEqual(4.0);
});

test('UC-RVC-03 — 25 por página pela data da venda decrescente', function () {
    $cliente = rvcContato($this->business->id, $this->user->id, 'customer');
    for ($i = 0; $i < 26; $i++) {
        rvcVenda($this->business->id, $this->user->id, $cliente, sprintf('RVC-%02d', $i), sprintf('2099-11-%02d 10:00:00', $i + 1), [['RVC-P'.$i, 'purchase', 1]]);
    }

    $p1 = rvcPage($this, $cliente, 1);
    expect($p1['linhas'])->toHaveCount(25);
    expect($p1['paginacao'])->toMatchArray(['atual' => 1, 'ultima' => 2, 'total' => 26]);
    expect($p1['linhas'][0]['venda'])->toBe('RVC-25');

    $p2 = rvcPage($this, $cliente, 2);
    expect($p2['linhas'])->toHaveCount(1);
    expect($p2['linhas'][0]['venda'])->toBe('RVC-00');
});

test('UC-RVC-04 Tier 0 — venda do negócio 99 não aparece; permissão da Blade', function () {
    $alheio = $this->seededSupportClientTenant();
    expect($alheio->id)->not->toBe($this->business->id);
    $donoAlheio = \App\User::factory()->create(['business_id' => $alheio->id])->id;
    $clienteAlheio = rvcContato($alheio->id, $donoAlheio, 'customer');
    rvcVenda($alheio->id, $donoAlheio, $clienteAlheio, 'RVC-X-'.uniqid(), '2099-12-05 10:00:00', [['RVC-X1', 'purchase', 2]]);

    $props = rvcPage($this, $clienteAlheio);
    expect($props['linhas'])->toBe([]);
    expect(collect($props['clientes'])->pluck('id'))->not->toContain($clienteAlheio);

    // Sem a permissão: 403 na tela nova e no endpoint da Blade.
    $this->actingAs($this->usuarioComPermissoes([], $this->business));
    $this->withHeaders($this->inertia)->get('/reports/product-sell-report-with-purchase?tela=nova')->assertForbidden();
    $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])->get('/reports/product-sell-report-with-purchase')->assertForbidden();
});
