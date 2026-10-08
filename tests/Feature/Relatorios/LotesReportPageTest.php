<?php

declare(strict_types=1);
// Cobre UC-RLT-01, UC-RLT-02, UC-RLT-03 (resources/js/Pages/Relatorios/Lotes/Index.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EstoqueFixture;

// Estoque por três caminhos: JSON do DataTable da Blade × props da Page × conta direta em
// purchase_lines / transaction_sell_lines_purchase_lines, mais a conta à mão. Local próprio do teste
// (filtrado por location_id, como a Blade faz). Tenant 98 × 99 (ADR 0358).

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
});

/**
 * Um produto no local, com um lote por item de $lotes: [numero, comprado, vendido, devolvido_da_venda, ajustado].
 *
 * @return array{0: int, 1: string} [purchase_line_id do 1º lote, sku]
 */
function rltCenario(int $businessId, int $local, int $criador, array $lotes): array
{
    $produto = EstoqueFixture::singleProduct($businessId);
    DB::table('product_locations')->insert(['product_id' => $produto->productId, 'location_id' => $local]);
    $compra = DB::table('transactions')->insertGetId([
        'business_id' => $businessId, 'location_id' => $local, 'type' => 'purchase', 'status' => 'received',
        'payment_status' => 'paid', 'transaction_date' => '2099-12-01 10:00:00', 'final_total' => 0, 'total_before_tax' => 0,
        'created_by' => $criador, 'essentials_duration' => 0, 'ref_no' => 'RLT-'.uniqid(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    $primeira = null;
    foreach ($lotes as [$numero, $comprado, $vendido, $devolvido, $ajustado]) {
        $pl = DB::table('purchase_lines')->insertGetId([
            'transaction_id' => $compra, 'product_id' => $produto->productId, 'variation_id' => $produto->variations[0]['variation_id'],
            'quantity' => $comprado, 'purchase_price' => 1, 'item_tax' => 0, 'lot_number' => $numero, 'exp_date' => '2099-12-31',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $primeira ??= $pl;
        if ($vendido > 0) {
            DB::table('transaction_sell_lines_purchase_lines')->insert(['sell_line_id' => 999999999, 'purchase_line_id' => $pl, 'quantity' => $vendido, 'qty_returned' => $devolvido, 'created_at' => now(), 'updated_at' => now()]);
        }
        if ($ajustado > 0) {
            DB::table('transaction_sell_lines_purchase_lines')->insert(['stock_adjustment_line_id' => 999999999, 'purchase_line_id' => $pl, 'quantity' => $ajustado, 'qty_returned' => 0, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    return [$primeira, (string) DB::table('variations')->where('id', $produto->variations[0]['variation_id'])->value('sub_sku')];
}

function rltPage($teste, int $local, int $pagina = 1): array
{
    $r = $teste->withHeaders($teste->inertia)->get('/reports/lot-report?'.http_build_query(['tela' => 'nova', 'location_id' => $local, 'page' => $pagina]));
    $r->assertOk();
    expect($r->json('component'))->toBe('Relatorios/Lotes/Index');

    return $r->json('props');
}

test('UC-RLT-01 estoque — estoque, vendido e ajustado = JSON do DataTable da Blade = conta direta', function () {
    $local = EstoqueFixture::locationId($this->business->id, '-RLT1');
    [$pl] = rltCenario($this->business->id, $local, $this->user->id, [['RLT-A', 100, 30, 5, 10]]);

    // Caminho 1: o JSON que o DataTable da Blade pede, com as colunas do report.js.
    $colunas = [];
    foreach ([['sub_sku', 'v.sub_sku'], ['product', 'products.name'], ['lot_number', 'pl.lot_number'], ['exp_date', 'pl.exp_date'], ['stock', 'stock'], ['total_sold', 'total_sold'], ['total_adjusted', 'total_adjusted']] as $i => [$d, $n]) {
        $colunas[$i] = ['data' => $d, 'name' => $n, 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']];
    }
    $dt = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->get('/reports/lot-report?'.http_build_query(['draw' => 1, 'start' => 0, 'length' => -1, 'columns' => $colunas,
            'order' => [['column' => 0, 'dir' => 'asc']], 'search' => ['value' => '', 'regex' => 'false'], 'location_id' => $local]));
    $dt->assertOk();
    expect($dt->json('error'))->toBeNull();
    expect($dt->json('data'))->toHaveCount(1);
    $orig = fn (string $c) => (float) (preg_match('/data-orig-value="([^"]*)"/', (string) $dt->json('data.0.'.$c), $m) ? $m[1] : 'NaN');

    // Caminho 2: a Page.
    $linha = rltPage($this, $local)['linhas'][0];
    expect((float) $linha['estoque'])->toEqual($orig('stock'));
    expect((float) $linha['vendido'])->toEqual($orig('total_sold'));
    expect((float) $linha['ajustado'])->toEqual($orig('total_adjusted'));

    // Caminho 3: conta direta — comprado − devolvido do lote − baixas (venda líquida + ajuste).
    $comprado = (float) DB::table('purchase_lines')->where('id', $pl)->value(DB::raw('quantity - quantity_returned'));
    $baixas = (float) DB::table('transaction_sell_lines_purchase_lines')->where('purchase_line_id', $pl)->sum(DB::raw('quantity - qty_returned'));
    expect((float) $linha['estoque'])->toEqual($comprado - $baixas);

    // Conta à mão: 100 − ((30 − 5) + 10) = 65; vendido 25; ajustado 10.
    expect([(float) $linha['estoque'], (float) $linha['vendido'], (float) $linha['ajustado']])->toEqual([65.0, 25.0, 10.0]);
});

test('UC-RLT-02 — 25 por página em ordem de SKU e lote; rodapé por unidade só da página', function () {
    $local = EstoqueFixture::locationId($this->business->id, '-RLT2');
    $lotes = [];
    for ($i = 0; $i < 26; $i++) {
        $lotes[] = [sprintf('RLT-%02d', $i), 10 + $i, 0, 0, 0];
    }
    rltCenario($this->business->id, $local, $this->user->id, $lotes);

    $p1 = rltPage($this, $local, 1);
    expect($p1['linhas'])->toHaveCount(25);
    expect($p1['paginacao'])->toMatchArray(['atual' => 1, 'ultima' => 2, 'total' => 26]);
    expect($p1['linhas'][0]['lote'])->toBe('RLT-00');
    expect($p1['rodape'])->toHaveCount(1);
    expect((float) $p1['rodape'][0]['estoque'])->toEqual((float) array_sum(range(10, 34)));

    $p2 = rltPage($this, $local, 2);
    expect($p2['linhas'])->toHaveCount(1);
    expect($p2['linhas'][0]['lote'])->toBe('RLT-25');
});

test('UC-RLT-03 Tier 0 — lote do negócio 99 não aparece; permissão da Blade', function () {
    $alheio = $this->seededSupportClientTenant();
    expect($alheio->id)->not->toBe($this->business->id);
    $donoAlheio = \App\User::factory()->create(['business_id' => $alheio->id])->id;
    $localAlheio = EstoqueFixture::locationId($alheio->id, '-RLT3');
    rltCenario($alheio->id, $localAlheio, $donoAlheio, [['RLT-X', 50, 0, 0, 0]]);

    $props = rltPage($this, $localAlheio);
    expect($props['linhas'])->toBe([]);
    expect(collect($props['locais'])->pluck('id'))->not->toContain($localAlheio);

    // Sem a permissão: 403 na tela nova e na Blade.
    $this->actingAs($this->usuarioComPermissoes([], $this->business));
    $this->withHeaders($this->inertia)->get('/reports/lot-report?tela=nova')->assertForbidden();
    $this->withHeaders([])->get('/reports/lot-report')->assertForbidden();
});
