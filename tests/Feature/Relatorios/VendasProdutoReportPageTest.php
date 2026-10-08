<?php

declare(strict_types=1);
// Cobre UC-RVP-01, UC-RVP-02, UC-RVP-03, UC-RVP-04 (resources/js/Pages/Relatorios/VendasProduto/Index.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EstoqueFixture;

// Valor e quantidade por três caminhos: JSON do DataTable da Blade × props da Page × conta direta em
// transaction_sell_lines, mais a conta à mão. Cliente próprio do teste (filtro da Blade). Tenant 98 × 99 (ADR 0358).

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
    // A Blade formata data (format_datetime) e valor (num_f) com o que o SetSessionData põe na sessão no login.
    session(['business.date_format' => 'd/m/Y', 'business.time_format' => 24,
        'currency' => ['symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ',']]);
});

function rvpCliente(int $businessId, int $criador): int
{
    return DB::table('contacts')->insertGetId([
        'business_id' => $businessId, 'type' => 'customer', 'name' => 'RVP '.uniqid(), 'mobile' => '0',
        'created_by' => $criador, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** Uma venda do cliente com os itens [vendido, devolvido, preco_com_imposto, mae?]; devolve os ids das linhas. */
function rvpVenda(int $businessId, int $criador, int $cliente, string $numero, array $itens, string $status = 'final'): array
{
    $venda = DB::table('transactions')->insertGetId([
        'business_id' => $businessId, 'location_id' => EstoqueFixture::locationId($businessId), 'type' => 'sell',
        'status' => $status, 'payment_status' => 'paid', 'contact_id' => $cliente, 'transaction_date' => '2099-12-05 10:00:00',
        'final_total' => 0, 'total_before_tax' => 0, 'created_by' => $criador, 'essentials_duration' => 0, 'invoice_no' => $numero,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('transaction_payments')->insert([
        'transaction_id' => $venda, 'business_id' => $businessId, 'amount' => 1, 'method' => 'cash', 'is_return' => 0,
        'paid_on' => '2099-12-05 10:00:00', 'created_by' => $criador, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $ids = [];
    foreach ($itens as [$vendido, $devolvido, $preco, $mae]) {
        // Um produto por item: uk_tsl_dup_prevent não aceita o mesmo produto duas vezes na venda (o combo tem mãe e filha).
        $produto = EstoqueFixture::singleProduct($businessId);
        $ids[] = DB::table('transaction_sell_lines')->insertGetId([
            'transaction_id' => $venda, 'product_id' => $produto->productId, 'variation_id' => $produto->variations[0]['variation_id'],
            'quantity' => $vendido, 'quantity_returned' => $devolvido, 'unit_price' => $preco, 'unit_price_inc_tax' => $preco,
            'unit_price_before_discount' => $preco, 'item_tax' => 0, 'parent_sell_line_id' => $mae ? $ids[0] : null,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    return $ids;
}

function rvpPage($teste, int $cliente, int $pagina = 1): array
{
    $r = $teste->withHeaders($teste->inertia)->get('/reports/product-sell-report?'.http_build_query(['tela' => 'nova', 'customer_id' => $cliente, 'page' => $pagina]));
    $r->assertOk();
    expect($r->json('component'))->toBe('Relatorios/VendasProduto/Index');

    return $r->json('props');
}

test('UC-RVP-01 valor — quantidade e subtotal = JSON do DataTable da Blade = conta direta; rascunho fora', function () {
    $cliente = rvpCliente($this->business->id, $this->user->id);
    [$linhaId] = rvpVenda($this->business->id, $this->user->id, $cliente, 'RVP-A-'.uniqid(), [[5, 1, 20, false]]);
    rvpVenda($this->business->id, $this->user->id, $cliente, 'RVP-R-'.uniqid(), [[9, 0, 50, false]], 'draft');

    // Caminho 1: o JSON que o DataTable da Blade pede, com as colunas e a ordem do report.js.
    $colunas = [];
    foreach ([['product_name', 'p.name'], ['sub_sku', 'v.sub_sku'], ['product_custom_field1', 'p.product_custom_field1'], ['product_custom_field2', 'p.product_custom_field2'], ['customer', 'c.name'], ['contact_id', 'c.contact_id'], ['invoice_no', 't.invoice_no'], ['transaction_date', 't.transaction_date'], ['sell_qty', 'transaction_sell_lines.quantity'], ['unit_price', 'transaction_sell_lines.unit_price_before_discount'], ['discount_amount', 'transaction_sell_lines.line_discount_amount'], ['tax', 'tax_rates.name'], ['unit_sale_price', 'transaction_sell_lines.unit_price_inc_tax'], ['subtotal', 'subtotal'], ['payment_methods', 'payment_methods']] as $i => [$d, $n]) {
        $colunas[$i] = ['data' => $d, 'name' => $n, 'searchable' => in_array($d, ['subtotal', 'payment_methods'], true) ? 'false' : 'true', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']];
    }
    $dt = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->get('/reports/product-sell-report?'.http_build_query(['draw' => 1, 'start' => 0, 'length' => -1, 'columns' => $colunas,
            'order' => [['column' => 6, 'dir' => 'desc']], 'search' => ['value' => '', 'regex' => 'false'], 'customer_id' => $cliente]));
    $dt->assertOk();
    expect($dt->json('error'))->toBeNull();
    expect($dt->json('data'))->toHaveCount(1);
    $orig = fn (string $c) => (preg_match('/data-orig-value="([^"]*)"/', (string) $dt->json('data.0.'.$c), $m) ? $m[1] : 'NaN');

    // Caminho 2: a Page.
    $props = rvpPage($this, $cliente);
    expect($props['linhas'])->toHaveCount(1);
    $linha = $props['linhas'][0];
    expect((float) $linha['quantidade'])->toEqual((float) $orig('sell_qty'));
    expect((float) $linha['subtotal'])->toEqual((float) $orig('subtotal'));
    expect($linha['pagamento'])->toBe($orig('payment_methods'));

    // Caminho 3: conta direta no item de venda.
    $item = DB::table('transaction_sell_lines')->where('id', $linhaId)->first();
    expect((float) $linha['subtotal'])->toEqual((float) (($item->quantity - $item->quantity_returned) * $item->unit_price_inc_tax));

    // Conta à mão: quantidade 5 − 1 = 4; subtotal 4 × 20 = 80. O rascunho (9 × 50) não entra.
    expect([(float) $linha['quantidade'], (float) $linha['subtotal'], (float) $props['rodape']['subtotal']])->toEqual([4.0, 80.0, 80.0]);
});

test('UC-RVP-02 valor — rodapé da página sem a linha filha de combo', function () {
    $cliente = rvpCliente($this->business->id, $this->user->id);
    rvpVenda($this->business->id, $this->user->id, $cliente, 'RVP-C-'.uniqid(), [[2, 0, 30, false], [2, 0, 10, true]]);

    $props = rvpPage($this, $cliente);
    expect($props['linhas'])->toHaveCount(2);
    expect(collect($props['linhas'])->where('combo_filho', true))->toHaveCount(1);
    // Só a linha mãe: quantidade 2, subtotal 2 × 30 = 60 (a filha, 2 × 10, fica fora).
    expect((float) $props['rodape']['subtotal'])->toEqual(60.0);
    expect(array_sum(array_column($props['rodape']['por_unidade'], 'quantidade')))->toEqual(2.0);
});

test('UC-RVP-03 — 25 por página pelo nº da venda decrescente', function () {
    $cliente = rvpCliente($this->business->id, $this->user->id);
    for ($i = 0; $i < 26; $i++) {
        rvpVenda($this->business->id, $this->user->id, $cliente, sprintf('RVP-%02d', $i), [[1, 0, 10 + $i, false]]);
    }

    $p1 = rvpPage($this, $cliente, 1);
    expect($p1['linhas'])->toHaveCount(25);
    expect($p1['paginacao'])->toMatchArray(['atual' => 1, 'ultima' => 2, 'total' => 26]);
    expect($p1['linhas'][0]['venda'])->toBe('RVP-25');
    expect((float) $p1['rodape']['subtotal'])->toEqual((float) array_sum(range(11, 35))); // sem a RVP-00, que cai na página 2

    $p2 = rvpPage($this, $cliente, 2);
    expect($p2['linhas'])->toHaveCount(1);
    expect($p2['linhas'][0]['venda'])->toBe('RVP-00');
});

test('UC-RVP-04 Tier 0 — venda do negócio 99 não aparece; permissão da Blade', function () {
    $alheio = $this->seededSupportClientTenant();
    expect($alheio->id)->not->toBe($this->business->id);
    $donoAlheio = \App\User::factory()->create(['business_id' => $alheio->id])->id;
    $clienteAlheio = rvpCliente($alheio->id, $donoAlheio);
    rvpVenda($alheio->id, $donoAlheio, $clienteAlheio, 'RVP-X-'.uniqid(), [[3, 0, 7, false]]);

    $props = rvpPage($this, $clienteAlheio);
    expect($props['linhas'])->toBe([]);
    expect(collect($props['clientes'])->pluck('id'))->not->toContain($clienteAlheio);

    // Sem a permissão: 403 na tela nova e na Blade.
    $this->actingAs($this->usuarioComPermissoes([], $this->business));
    $this->withHeaders($this->inertia)->get('/reports/product-sell-report?tela=nova')->assertForbidden();
    $this->withHeaders([])->get('/reports/product-sell-report')->assertForbidden();
});
