<?php

declare(strict_types=1);
// Cobre UC-RVA-01, UC-RVA-02, UC-RVA-03, UC-RVA-04 (resources/js/Pages/Relatorios/VendasAgrupado/Index.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EstoqueFixture;

// Quantidade e subtotal agrupados (variação × dia) por três caminhos: JSON do DataTable da Blade × props da Page ×
// conta direta em transaction_sell_lines, mais a conta à mão. Cliente próprio do teste. Tenant 98 × 99 (ADR 0358).

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
    session(['business.date_format' => 'd/m/Y', 'business.time_format' => 24,
        'currency' => ['symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ',']]);
});

function rvaCliente(int $businessId, int $criador): int
{
    return DB::table('contacts')->insertGetId([
        'business_id' => $businessId, 'type' => 'customer', 'name' => 'RVA '.uniqid(), 'mobile' => '0',
        'created_by' => $criador, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** Produto com SKU conhecido; devolve [product_id, variation_id]. */
function rvaProduto(int $businessId, string $sku): array
{
    $produto = EstoqueFixture::singleProduct($businessId);
    $variacao = $produto->variations[0]['variation_id'];
    DB::table('variations')->where('id', $variacao)->update(['sub_sku' => $sku]);

    return [$produto->productId, $variacao];
}

function rvaVenda(int $businessId, int $criador, int $cliente, array $produto, string $data, float $vendido, float $devolvido, float $preco, string $status = 'final'): int
{
    $venda = DB::table('transactions')->insertGetId([
        'business_id' => $businessId, 'location_id' => EstoqueFixture::locationId($businessId), 'type' => 'sell', 'status' => $status,
        'payment_status' => 'paid', 'contact_id' => $cliente, 'transaction_date' => $data, 'final_total' => 0, 'total_before_tax' => 0,
        'created_by' => $criador, 'essentials_duration' => 0, 'invoice_no' => 'RVA-'.uniqid(), 'created_at' => now(), 'updated_at' => now(),
    ]);

    return DB::table('transaction_sell_lines')->insertGetId([
        'transaction_id' => $venda, 'product_id' => $produto[0], 'variation_id' => $produto[1], 'quantity' => $vendido,
        'quantity_returned' => $devolvido, 'unit_price' => $preco, 'unit_price_inc_tax' => $preco, 'unit_price_before_discount' => $preco,
        'item_tax' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function rvaPage($teste, int $cliente, int $pagina = 1): array
{
    $r = $teste->withHeaders($teste->inertia)->get('/reports/product-sell-grouped-report?'.http_build_query(['tela' => 'nova', 'customer_id' => $cliente, 'page' => $pagina]));
    $r->assertOk();
    expect($r->json('component'))->toBe('Relatorios/VendasAgrupado/Index');

    return $r->json('props');
}

test('UC-RVA-01 valor — soma do dia = JSON do DataTable da Blade = conta direta; rascunho fora', function () {
    $cliente = rvaCliente($this->business->id, $this->user->id);
    $produto = rvaProduto($this->business->id, 'RVA-A-'.uniqid());
    $l1 = rvaVenda($this->business->id, $this->user->id, $cliente, $produto, '2099-12-05 09:00:00', 3, 0, 10);
    $l2 = rvaVenda($this->business->id, $this->user->id, $cliente, $produto, '2099-12-05 18:00:00', 2, 1, 10);
    rvaVenda($this->business->id, $this->user->id, $cliente, $produto, '2099-12-05 12:00:00', 9, 0, 50, 'draft');

    // Caminho 1: o JSON que o DataTable da Blade pede, com as colunas e a ordem do report.js.
    $colunas = [];
    foreach ([['product_name', 'p.name', 'true'], ['sub_sku', 'v.sub_sku', 'true'], ['transaction_date', 't.transaction_date', 'true'], ['current_stock', 'current_stock', 'false'], ['total_qty_sold', 'total_qty_sold', 'false'], ['subtotal', 'subtotal', 'false']] as $i => [$d, $n, $busca]) {
        $colunas[$i] = ['data' => $d, 'name' => $n, 'searchable' => $busca, 'orderable' => $d === 'current_stock' ? 'false' : 'true', 'search' => ['value' => '', 'regex' => 'false']];
    }
    $dt = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->get('/reports/product-sell-grouped-report?'.http_build_query(['draw' => 1, 'start' => 0, 'length' => -1, 'columns' => $colunas,
            'order' => [['column' => 1, 'dir' => 'desc']], 'search' => ['value' => '', 'regex' => 'false'], 'customer_id' => $cliente]));
    $dt->assertOk();
    expect($dt->json('error'))->toBeNull();
    expect($dt->json('data'))->toHaveCount(1);
    $orig = fn (string $c) => (float) (preg_match('/data-orig-value="([^"]*)"/', (string) $dt->json('data.0.'.$c), $m) ? $m[1] : 'NaN');

    // Caminho 2: a Page.
    $props = rvaPage($this, $cliente);
    expect($props['linhas'])->toHaveCount(1);
    $linha = $props['linhas'][0];
    expect((float) $linha['quantidade'])->toEqual($orig('total_qty_sold'));
    expect((float) $linha['subtotal'])->toEqual($orig('subtotal'));

    // Caminho 3: conta direta nos dois itens do dia.
    $direto = DB::table('transaction_sell_lines')->whereIn('id', [$l1, $l2])
        ->selectRaw('SUM(quantity - quantity_returned) as q, SUM((quantity - quantity_returned) * unit_price_inc_tax) as s')->first();
    expect([(float) $linha['quantidade'], (float) $linha['subtotal']])->toEqual([(float) $direto->q, (float) $direto->s]);

    // Conta à mão: (3 − 0) + (2 − 1) = 4; 4 × 10 = 40. O rascunho (9 × 50) não entra.
    expect([(float) $linha['quantidade'], (float) $linha['subtotal'], (float) $props['rodape']['subtotal']])->toEqual([4.0, 40.0, 40.0]);
});

test('UC-RVA-02 — o mesmo produto em dois dias vira duas linhas', function () {
    $cliente = rvaCliente($this->business->id, $this->user->id);
    $produto = rvaProduto($this->business->id, 'RVA-D-'.uniqid());
    rvaVenda($this->business->id, $this->user->id, $cliente, $produto, '2099-12-04 10:00:00', 1, 0, 10);
    rvaVenda($this->business->id, $this->user->id, $cliente, $produto, '2099-12-05 10:00:00', 2, 0, 10);

    $linhas = rvaPage($this, $cliente)['linhas'];
    expect(array_map(fn ($l) => [$l['data'], (float) $l['quantidade']], $linhas))->toBe([['05/12/2099', 2.0], ['04/12/2099', 1.0]]);
});

test('UC-RVA-03 — 25 por página pelo SKU decrescente', function () {
    $cliente = rvaCliente($this->business->id, $this->user->id);
    $sufixo = uniqid();
    for ($i = 0; $i < 26; $i++) {
        rvaVenda($this->business->id, $this->user->id, $cliente, rvaProduto($this->business->id, sprintf('RVA%02d-%s', $i, $sufixo)), '2099-12-05 10:00:00', 1, 0, 10 + $i);
    }

    $p1 = rvaPage($this, $cliente, 1);
    expect($p1['linhas'])->toHaveCount(25);
    expect($p1['paginacao'])->toMatchArray(['atual' => 1, 'ultima' => 2, 'total' => 26]);
    expect($p1['linhas'][0]['sku'])->toBe('RVA25-'.$sufixo);
    expect((float) $p1['rodape']['subtotal'])->toEqual((float) array_sum(range(11, 35))); // sem a RVA00, que cai na página 2

    $p2 = rvaPage($this, $cliente, 2);
    expect($p2['linhas'])->toHaveCount(1);
    expect($p2['linhas'][0]['sku'])->toBe('RVA00-'.$sufixo);
});

test('UC-RVA-04 Tier 0 — venda do negócio 99 não aparece; permissão da Blade', function () {
    $alheio = $this->seededSupportClientTenant();
    expect($alheio->id)->not->toBe($this->business->id);
    $donoAlheio = \App\User::factory()->create(['business_id' => $alheio->id])->id;
    $clienteAlheio = rvaCliente($alheio->id, $donoAlheio);
    rvaVenda($alheio->id, $donoAlheio, $clienteAlheio, rvaProduto($alheio->id, 'RVA-X-'.uniqid()), '2099-12-05 10:00:00', 3, 0, 7);

    $props = rvaPage($this, $clienteAlheio);
    expect($props['linhas'])->toBe([]);
    expect(collect($props['clientes'])->pluck('id'))->not->toContain($clienteAlheio);

    // Sem a permissão: 403 na tela nova e no endpoint da Blade.
    $this->actingAs($this->usuarioComPermissoes([], $this->business));
    $this->withHeaders($this->inertia)->get('/reports/product-sell-grouped-report?tela=nova')->assertForbidden();
    $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])->get('/reports/product-sell-grouped-report')->assertForbidden();
});
