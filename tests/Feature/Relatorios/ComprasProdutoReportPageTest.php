<?php

declare(strict_types=1);
// Cobre UC-RCP-01, UC-RCP-02, UC-RCP-03 (resources/js/Pages/Relatorios/ComprasProduto/Index.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EstoqueFixture;

// Valor e quantidade por três caminhos: JSON do DataTable da Blade × props da Page × conta direta em
// purchase_lines, mais a conta à mão. Fornecedor próprio do teste (filtro da Blade). Tenant 98 × 99 (ADR 0358).

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
    // A Blade formata data (format_date) e valor (num_f) com o que o SetSessionData põe na sessão no login.
    session(['business.date_format' => 'd/m/Y', 'business.time_format' => 24,
        'currency' => ['symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ',']]);
});

/**
 * Compras do fornecedor, uma por item de $itens: [ref, comprado, devolvido, ajustado, preco_com_imposto].
 *
 * @return array{0: int, 1: int[]} [id do fornecedor, ids das purchase_lines]
 */
function rcpCenario(int $businessId, int $criador, array $itens): array
{
    $fornecedor = DB::table('contacts')->insertGetId([
        'business_id' => $businessId, 'type' => 'supplier', 'name' => 'RCP '.uniqid(), 'mobile' => '0',
        'created_by' => $criador, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $produto = EstoqueFixture::singleProduct($businessId);
    $linhas = [];
    foreach ($itens as [$ref, $comprado, $devolvido, $ajustado, $preco]) {
        $compra = DB::table('transactions')->insertGetId([
            'business_id' => $businessId, 'location_id' => EstoqueFixture::locationId($businessId), 'type' => 'purchase',
            'status' => 'received', 'payment_status' => 'paid', 'contact_id' => $fornecedor, 'transaction_date' => '2099-12-05 10:00:00',
            'final_total' => 0, 'total_before_tax' => 0, 'created_by' => $criador, 'essentials_duration' => 0, 'ref_no' => $ref,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $linhas[] = DB::table('purchase_lines')->insertGetId([
            'transaction_id' => $compra, 'product_id' => $produto->productId, 'variation_id' => $produto->variations[0]['variation_id'],
            'quantity' => $comprado, 'quantity_returned' => $devolvido, 'quantity_adjusted' => $ajustado,
            'purchase_price' => $preco, 'purchase_price_inc_tax' => $preco, 'item_tax' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    return [$fornecedor, $linhas];
}

function rcpPage($teste, int $fornecedor, int $pagina = 1): array
{
    $r = $teste->withHeaders($teste->inertia)->get('/reports/product-purchase-report?'.http_build_query(['tela' => 'nova', 'supplier_id' => $fornecedor, 'page' => $pagina]));
    $r->assertOk();
    expect($r->json('component'))->toBe('Relatorios/ComprasProduto/Index');

    return $r->json('props');
}

test('UC-RCP-01 valor — quantidade, ajustado e subtotal = JSON do DataTable da Blade = conta direta', function () {
    [$fornecedor, [$pl]] = rcpCenario($this->business->id, $this->user->id, [['RCP-A-'.uniqid(), 10, 2, 1, 12]]);

    // Caminho 1: o JSON que o DataTable da Blade pede, com as colunas e a ordem do report.js.
    $colunas = [];
    foreach ([['product_name', 'p.name'], ['sub_sku', 'v.sub_sku'], ['supplier', 'c.name'], ['ref_no', 't.ref_no'], ['transaction_date', 't.transaction_date'], ['purchase_qty', 'purchase_lines.quantity'], ['quantity_adjusted', 'purchase_lines.quantity_adjusted'], ['unit_purchase_price', 'purchase_lines.purchase_price_inc_tax'], ['subtotal', 'subtotal']] as $i => [$d, $n]) {
        $colunas[$i] = ['data' => $d, 'name' => $n, 'searchable' => $d === 'subtotal' ? 'false' : 'true', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']];
    }
    $dt = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->get('/reports/product-purchase-report?'.http_build_query(['draw' => 1, 'start' => 0, 'length' => -1, 'columns' => $colunas,
            'order' => [['column' => 3, 'dir' => 'desc']], 'search' => ['value' => '', 'regex' => 'false'], 'supplier_id' => $fornecedor]));
    $dt->assertOk();
    expect($dt->json('error'))->toBeNull();
    expect($dt->json('data'))->toHaveCount(1);
    $orig = fn (string $c) => (float) (preg_match('/data-orig-value="([^"]*)"/', (string) $dt->json('data.0.'.$c), $m) ? $m[1] : 'NaN');

    // Caminho 2: a Page.
    $linha = rcpPage($this, $fornecedor)['linhas'][0];
    expect((float) $linha['quantidade'])->toEqual($orig('purchase_qty'));
    expect((float) $linha['ajustado'])->toEqual($orig('quantity_adjusted'));
    expect((float) $linha['subtotal'])->toEqual($orig('subtotal'));

    // Caminho 3: conta direta no item de compra.
    $item = DB::table('purchase_lines')->where('id', $pl)->first();
    expect((float) $linha['subtotal'])->toEqual((float) (($item->quantity - $item->quantity_returned - $item->quantity_adjusted) * $item->purchase_price_inc_tax));

    // Conta à mão: quantidade 10 − 2 = 8; subtotal (10 − 2 − 1) × 12 = 84.
    expect([(float) $linha['quantidade'], (float) $linha['ajustado'], (float) $linha['subtotal']])->toEqual([8.0, 1.0, 84.0]);
});

test('UC-RCP-02 — 25 por página pela ref. da compra decrescente; rodapé só da página', function () {
    $itens = [];
    for ($i = 0; $i < 26; $i++) {
        $itens[] = [sprintf('RCP-%02d', $i), 1, 0, 0, 10 + $i]; // subtotal = 10 + i
    }
    [$fornecedor] = rcpCenario($this->business->id, $this->user->id, $itens);

    $p1 = rcpPage($this, $fornecedor, 1);
    expect($p1['linhas'])->toHaveCount(25);
    expect($p1['paginacao'])->toMatchArray(['atual' => 1, 'ultima' => 2, 'total' => 26]);
    expect($p1['linhas'][0]['compra'])->toBe('RCP-25');
    expect((float) $p1['rodape']['subtotal'])->toEqual((float) array_sum(range(11, 35))); // sem o RCP-00, que cai na página 2

    $p2 = rcpPage($this, $fornecedor, 2);
    expect($p2['linhas'])->toHaveCount(1);
    expect($p2['linhas'][0]['compra'])->toBe('RCP-00');
});

test('UC-RCP-03 Tier 0 — compra do negócio 99 não aparece; permissão da Blade', function () {
    $alheio = $this->seededSupportClientTenant();
    expect($alheio->id)->not->toBe($this->business->id);
    $donoAlheio = \App\User::factory()->create(['business_id' => $alheio->id])->id;
    [$fornecedorAlheio] = rcpCenario($alheio->id, $donoAlheio, [['RCP-X-'.uniqid(), 5, 0, 0, 7]]);

    $props = rcpPage($this, $fornecedorAlheio);
    expect($props['linhas'])->toBe([]);
    expect(collect($props['fornecedores'])->pluck('id'))->not->toContain($fornecedorAlheio);

    // Sem a permissão: 403 na tela nova e na Blade.
    $this->actingAs($this->usuarioComPermissoes([], $this->business));
    $this->withHeaders($this->inertia)->get('/reports/product-purchase-report?tela=nova')->assertForbidden();
    $this->withHeaders([])->get('/reports/product-purchase-report')->assertForbidden();
});
