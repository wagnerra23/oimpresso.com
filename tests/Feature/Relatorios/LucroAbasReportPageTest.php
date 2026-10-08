<?php

declare(strict_types=1);
// Cobre UC-RLA-01, UC-RLA-02, UC-RLA-03, UC-RLA-04 (resources/js/Pages/Relatorios/LucroAbas/Index.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EstoqueFixture;

// Lucro por três caminhos: JSON do DataTable da Blade × props da Page × conta à mão. Local próprio do teste e janela em
// 2099-08. Tenant 98 × 99 (ADR 0358). A permissão do endpoint é coberta pelo LucroAbasSegurancaTest.

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasColumn('transactions', 'essentials_duration')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->user = $this->usuarioComPermissoes(['profit_loss_report.view', 'access_all_locations'], $this->business);
    $this->versaoInertia = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $this->inertia = ['X-Inertia' => 'true', 'X-Inertia-Version' => $this->versaoInertia, 'X-Requested-With' => 'XMLHttpRequest'];
    $this->periodo = ['start_date' => '2099-08-01', 'end_date' => '2099-08-31'];

    $this->actingAs($this->user);
    session(['user.business_id' => $this->business->id, 'user.id' => $this->user->id, 'business.id' => $this->business->id]);
    session(['business.date_format' => 'd/m/Y', 'business.time_format' => 24,
        'currency' => ['symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ',']]);
});

/** Venda de $qtd unidades a $preco, abastecida por uma compra a $custo, com desconto opcional da venda. */
function rlaVenda(int $businessId, int $local, int $criador, string $numero, float $qtd, float $preco, float $custo, string $tipoDesconto = 'fixed', float $desconto = 0, string $data = '2099-08-10 10:00:00'): void
{
    $produto = EstoqueFixture::singleProduct($businessId);
    $variacao = $produto->variations[0]['variation_id'];
    $compra = DB::table('transactions')->insertGetId([
        'business_id' => $businessId, 'location_id' => $local, 'type' => 'purchase', 'status' => 'received', 'payment_status' => 'paid',
        'transaction_date' => '2099-07-01 10:00:00', 'final_total' => $qtd * $custo, 'total_before_tax' => $qtd * $custo,
        'created_by' => $criador, 'essentials_duration' => 0, 'ref_no' => 'RLA-'.uniqid(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    $linhaCompra = DB::table('purchase_lines')->insertGetId([
        'transaction_id' => $compra, 'product_id' => $produto->productId, 'variation_id' => $variacao, 'quantity' => $qtd,
        'purchase_price' => $custo, 'purchase_price_inc_tax' => $custo, 'item_tax' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $venda = DB::table('transactions')->insertGetId([
        'business_id' => $businessId, 'location_id' => $local, 'type' => 'sell', 'status' => 'final', 'payment_status' => 'paid',
        'transaction_date' => $data, 'final_total' => $qtd * $preco, 'total_before_tax' => $qtd * $preco, 'discount_type' => $tipoDesconto,
        'discount_amount' => $desconto, 'created_by' => $criador, 'essentials_duration' => 0, 'invoice_no' => $numero,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $linhaVenda = DB::table('transaction_sell_lines')->insertGetId([
        'transaction_id' => $venda, 'product_id' => $produto->productId, 'variation_id' => $variacao, 'quantity' => $qtd,
        'quantity_returned' => 0, 'unit_price' => $preco, 'unit_price_inc_tax' => $preco, 'unit_price_before_discount' => $preco,
        'item_tax' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('transaction_sell_lines_purchase_lines')->insert([
        'sell_line_id' => $linhaVenda, 'purchase_line_id' => $linhaCompra, 'quantity' => $qtd, 'qty_returned' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** O JSON que o DataTable da aba pede, com as colunas do profit_loss.blade.php. */
function rlaBlade($teste, string $aba, string $coluna, string $nome, int $local): array
{
    $r = $teste->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->get('/reports/get-profit/'.$aba.'?'.http_build_query(['draw' => 1, 'start' => 0, 'length' => -1, 'location_id' => $local,
            'columns' => [
                ['data' => $coluna, 'name' => $nome, 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'gross_profit', 'name' => 'gross_profit', 'searchable' => 'false', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']],
            ],
            'order' => [['column' => 0, 'dir' => 'asc']], 'search' => ['value' => '', 'regex' => 'false']] + $teste->periodo));
    $r->assertOk();
    expect($r->json('error'))->toBeNull();

    return collect($r->json('data'))->map(fn ($l) => (float) (preg_match('/data-orig-value="([^"]*)"/', (string) $l['gross_profit'], $m) ? $m[1] : 'NaN'))->all();
}

function rlaTela($teste, string $aba, int $local): array
{
    $r = $teste->withHeaders($teste->inertia)->get('/reports/get-profit/'.$aba.'?'.http_build_query(['tela' => 'nova', 'location_id' => $local] + $teste->periodo));
    $r->assertOk();
    expect($r->json('component'))->toBe('Relatorios/LucroAbas/Index');

    return $r->json('props');
}

test('UC-RLA-01 valor — lucro por produto = JSON do DataTable da Blade = conta à mão', function () {
    $local = EstoqueFixture::locationId($this->business->id, '-RLA-'.uniqid());
    rlaVenda($this->business->id, $local, $this->user->id, 'RLA-P-'.uniqid(), 3, 50, 30);

    $blade = rlaBlade($this, 'product', 'product', 'product', $local);
    $tela = array_map(fn ($l) => (float) $l['lucro'], rlaTela($this, 'product', $local)['linhas']);
    expect($tela)->toEqual($blade);
    // Conta à mão: 3 × (50 − 30) = 60.
    expect($tela)->toEqual([60.0]);
});

test('UC-RLA-02 valor — aba "por venda" com o desconto da venda = JSON da Blade', function () {
    $local = EstoqueFixture::locationId($this->business->id, '-RLA-'.uniqid());
    rlaVenda($this->business->id, $local, $this->user->id, 'RLA-A', 3, 50, 30, 'fixed', 10);
    rlaVenda($this->business->id, $local, $this->user->id, 'RLA-B', 3, 50, 30, 'percentage', 10);

    $blade = rlaBlade($this, 'invoice', 'invoice_no', 'sale.invoice_no', $local);
    $props = rlaTela($this, 'invoice', $local);
    $tela = array_map(fn ($l) => (float) $l['lucro'], $props['linhas']);
    expect($tela)->toEqual($blade);
    // Conta à mão: RLA-A = 60 − 10 = 50; RLA-B = 60 − 10% de 150 = 45.
    expect(array_map(fn ($l) => [$l['rotulo'], (float) $l['lucro']], $props['linhas']))->toBe([['RLA-A', 50.0], ['RLA-B', 45.0]]);
    expect((float) $props['rodape']['lucro'])->toEqual(95.0);
});

test('UC-RLA-03 — aba "por dia" com os sete dias', function () {
    $local = EstoqueFixture::locationId($this->business->id, '-RLA-'.uniqid());
    rlaVenda($this->business->id, $local, $this->user->id, 'RLA-D-'.uniqid(), 3, 50, 30); // 10/08/2099 é segunda

    $linhas = rlaTela($this, 'day', $local)['linhas'];
    expect($linhas)->toHaveCount(7);
    expect(array_map(fn ($l) => (float) $l['lucro'], $linhas))->toEqual([60.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0]);
    expect($linhas[0]['rotulo'])->toBe(__('lang_v1.monday'));
});

test('UC-RLA-04 Tier 0 — venda do negócio 99 não aparece', function () {
    $alheio = $this->seededSupportClientTenant();
    expect($alheio->id)->not->toBe($this->business->id);
    $donoAlheio = \App\User::factory()->create(['business_id' => $alheio->id])->id;
    $localAlheio = EstoqueFixture::locationId($alheio->id, '-RLA-'.uniqid());
    rlaVenda($alheio->id, $localAlheio, $donoAlheio, 'RLA-X-'.uniqid(), 2, 40, 10);

    expect(rlaTela($this, 'product', $localAlheio)['linhas'])->toBe([]);
    expect(array_sum(array_map(fn ($l) => (float) $l['lucro'], rlaTela($this, 'day', $localAlheio)['linhas'])))->toEqual(0.0);
});
