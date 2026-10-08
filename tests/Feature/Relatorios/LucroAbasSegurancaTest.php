<?php

declare(strict_types=1);
// Regra: as abas de lucro (/reports/get-profit/{by}) só com profit_loss_report.view, e a busca por produto sem SQL cru.

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EstoqueFixture;

// getProfit não conferia permissão nenhuma, e o filterColumn da aba "product" punha o termo de busca do DataTable
// direto na string do SQL (LIKE '%{$keyword}%'). Único consumidor: as abas da página de lucro e prejuízo. Tenant 98.

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasColumn('transactions', 'essentials_duration')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->nome = 'RLS Produto '.uniqid();
    $produto = EstoqueFixture::singleProduct($this->business->id);
    DB::table('products')->where('id', $produto->productId)->update(['name' => $this->nome]);
    $criador = \App\User::factory()->create(['business_id' => $this->business->id])->id;
    $venda = DB::table('transactions')->insertGetId([
        'business_id' => $this->business->id, 'location_id' => EstoqueFixture::locationId($this->business->id), 'type' => 'sell',
        'status' => 'final', 'payment_status' => 'paid', 'transaction_date' => '2099-08-10 10:00:00', 'final_total' => 50,
        'total_before_tax' => 50, 'created_by' => $criador, 'essentials_duration' => 0, 'invoice_no' => 'RLS-'.uniqid(),
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('transaction_sell_lines')->insert([
        'transaction_id' => $venda, 'product_id' => $produto->productId, 'variation_id' => $produto->variations[0]['variation_id'],
        'quantity' => 1, 'quantity_returned' => 0, 'unit_price' => 50, 'unit_price_inc_tax' => 50, 'unit_price_before_discount' => 50,
        'item_tax' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
});

/** Pede a aba "product" como o DataTable da página de lucro, com o termo de busca dado. */
function rlsBusca($teste, array $permissoes, string $termo)
{
    $u = $teste->usuarioComPermissoes($permissoes, $teste->business);
    $teste->actingAs($u);
    session(['user.business_id' => $teste->business->id, 'user.id' => $u->id, 'business.id' => $teste->business->id]);

    return $teste->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->get('/reports/get-profit/product?'.http_build_query([
            'draw' => 1, 'start' => 0, 'length' => -1, 'start_date' => '2099-08-01', 'end_date' => '2099-08-31', 'location_id' => '',
            'columns' => [
                ['data' => 'product', 'name' => 'product', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'gross_profit', 'name' => 'gross_profit', 'searchable' => 'false', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']],
            ],
            'order' => [['column' => 0, 'dir' => 'asc']], 'search' => ['value' => $termo, 'regex' => 'false'],
        ]));
}

test('busca por produto — termo com aspas não vira SQL; o nome real continua achando', function () {
    $todas = ['profit_loss_report.view', 'access_all_locations'];

    // Termo que fecharia a aspa do LIKE e acrescentaria OR verdadeiro.
    $r = rlsBusca($this, $todas, "zz' OR '1'='1");
    $r->assertOk();
    expect($r->json('error'))->toBeNull();
    expect($r->json('recordsFiltered'))->toBe(0);

    // O nome do produto acha a linha dele.
    $r = rlsBusca($this, $todas, $this->nome);
    $r->assertOk();
    expect($r->json('error'))->toBeNull();
    expect(collect($r->json('data'))->pluck('product')->filter(fn ($p) => str_contains((string) $p, $this->nome))->count())->toBe(1);
});

test('Tier 0 — abas de lucro só com profit_loss_report.view', function () {
    rlsBusca($this, ['profit_loss_report.view', 'access_all_locations'], '')->assertOk();
    rlsBusca($this, ['stock_report.view', 'access_all_locations'], '')->assertForbidden();
    rlsBusca($this, [], '')->assertForbidden();
});
