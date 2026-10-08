<?php

declare(strict_types=1);
// Regra: o resumo do relatório de estoque (/reports/get-stock-value) só com stock_report.view E view_product_stock_value.

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EstoqueFixture;

// getStockValue não conferia permissão nenhuma; a Blade só mostra o resumo a quem tem as duas permissões (bloco @can de
// stock_report.blade.php). Único chamador: get_stock_value() em public/js/report.js. Tenant 98 (ADR 0358).

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasColumn('transactions', 'essentials_duration')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->local = EstoqueFixture::locationId($this->business->id, '-RER-'.uniqid());
    $criador = \App\User::factory()->create(['business_id' => $this->business->id])->id;
    $p = EstoqueFixture::singleProduct($this->business->id);
    $variacao = $p->variations[0]['variation_id'];
    DB::table('variations')->where('id', $variacao)->update(['sell_price_inc_tax' => 20]);
    EstoqueFixture::setStock($p, 0, $this->local, 7);
    $compra = DB::table('transactions')->insertGetId([
        'business_id' => $this->business->id, 'location_id' => $this->local, 'type' => 'purchase', 'status' => 'received',
        'payment_status' => 'paid', 'transaction_date' => now()->subDay()->toDateTimeString(), 'final_total' => 84, 'total_before_tax' => 84,
        'created_by' => $criador, 'essentials_duration' => 0, 'ref_no' => 'RER-'.uniqid(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('purchase_lines')->insert([
        'transaction_id' => $compra, 'product_id' => $p->productId, 'variation_id' => $variacao, 'quantity' => 7,
        'purchase_price' => 12, 'purchase_price_inc_tax' => 12, 'item_tax' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
});

function rerPedir($teste, array $permissoes)
{
    $u = $teste->usuarioComPermissoes($permissoes, $teste->business);
    $teste->actingAs($u);
    session(['user.business_id' => $teste->business->id, 'user.id' => $u->id, 'business.id' => $teste->business->id]);

    return $teste->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->get('/reports/get-stock-value?'.http_build_query(['location_id' => $teste->local]));
}

test('as duas permissões: os mesmos 4 números (util direto e conta à mão)', function () {
    $r = rerPedir($this, ['stock_report.view', 'view_product_stock_value', 'access_all_locations']);
    $r->assertOk();

    // Os mesmos argumentos que getStockValue usa, chamados direto no util.
    $util = app(\App\Utils\TransactionUtil::class);
    $hoje = \Carbon::now()->format('Y-m-d');
    $porCompra = (float) $util->getOpeningClosingStock($this->business->id, $hoje, $this->local, false, false, [], 'all');
    $porVenda = (float) $util->getOpeningClosingStock($this->business->id, $hoje, $this->local, false, true, [], 'all');
    expect([(float) $r->json('closing_stock_by_pp'), (float) $r->json('closing_stock_by_sp')])->toEqual([$porCompra, $porVenda]);

    // Conta à mão: 7 × 12 = 84 pela compra; 7 × 20 = 140 pela venda; lucro 56; margem 56 ÷ 140 = 40%.
    expect([(float) $r->json('closing_stock_by_pp'), (float) $r->json('closing_stock_by_sp'), (float) $r->json('potential_profit'), (float) $r->json('profit_margin')])
        ->toEqual([84.0, 140.0, 56.0, 40.0]);
});

test('Tier 0 — sem view_product_stock_value, ou sem nenhuma, 403', function () {
    rerPedir($this, ['stock_report.view', 'access_all_locations'])->assertForbidden();
    rerPedir($this, [])->assertForbidden();
});
