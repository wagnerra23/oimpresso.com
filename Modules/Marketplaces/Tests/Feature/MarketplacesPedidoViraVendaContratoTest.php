<?php

declare(strict_types=1);
// @covers-us US-MKT-007

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Marketplaces\Entities\ContaVendedor;
use Modules\Marketplaces\Entities\Marketplace;
use Modules\Marketplaces\Entities\PedidoMarketplace;
use Modules\Marketplaces\Services\PedidoParaVenda;
use Tests\Support\EstoqueFixture;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Contrato do pedido pago do Mercado Livre que vira venda (US-MKT-007 · ADR 0439).
 *
 * ⚠️ Eixo VALOR e ESTOQUE (regra mestre). Dupla prova de cada número:
 *  - valor: (a) `final_total` gravado na venda e (b) soma de quantidade × preço das linhas
 *    gravadas, os dois iguais ao `total_amount` do ML (2 × 49,90 = 99,80);
 *  - estoque: (a) saldo lido de `variation_location_details` e (b) conta à mão (10 − 2 = 8).
 * Vermelho aqui é achado, não teste a "consertar": a correção volta a [W].
 *
 * Decisões [W] 2026-10-26: baixa quando o ML confirma pago; local escolhido na conta.
 * Tenant 98 contra o 99 (ADR 0358). SKIP em SQLite.
 */
beforeEach(function () {
    if (! EstoqueFixture::schemaReady() || DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasColumn('mkt_account_credentials', 'location_id')) {
        $this->markTestSkipped('Precisa do schema MySQL com mkt_* e estoque (ADR 0358).');
    }
    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    $this->user98 = EstoqueFixture::userId($this->b98);
    $this->loc98 = EstoqueFixture::locationId($this->b98, '-ML');
    $this->ml = Marketplace::query()->where('slug', Marketplace::MERCADO_LIVRE)->firstOrFail();

    if (! DB::table('contacts')->where('business_id', $this->b98)->where('is_default', 1)->exists()) {
        DB::table('contacts')->insert([
            'business_id' => $this->b98, 'type' => 'customer', 'name' => 'Consumidor Final', 'contact_id' => 'CF-ML',
            'mobile' => '', 'is_default' => 1, 'created_by' => $this->user98, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    // Produto com 10 no local e lastro de compra (sem ele o mapPurchaseSell aborta).
    $this->produto = EstoqueFixture::singleProduct($this->b98);
    EstoqueFixture::setStock($this->produto, 0, $this->loc98, 10.0);
    $compra = (int) DB::table('transactions')->insertGetId([
        'business_id' => $this->b98, 'type' => 'purchase', 'status' => 'received', 'location_id' => $this->loc98,
        'payment_status' => 'paid', 'transaction_date' => now()->subDay(), 'total_before_tax' => 0, 'final_total' => 0,
        'created_by' => $this->user98, 'essentials_duration' => 0, 'created_at' => now()->subDay(), 'updated_at' => now()->subDay(),
    ]);
    DB::table('purchase_lines')->insert([
        'transaction_id' => $compra, 'product_id' => $this->produto->productId,
        'variation_id' => $this->produto->variations[0]['variation_id'], 'quantity' => 10, 'quantity_sold' => 0,
        'quantity_adjusted' => 0, 'quantity_returned' => 0, 'purchase_price' => 8, 'purchase_price_inc_tax' => 8,
        'item_tax' => 0, 'created_at' => now()->subDay(), 'updated_at' => now()->subDay(),
    ]);
    $this->sku = (string) DB::table('variations')->where('id', $this->produto->variations[0]['variation_id'])->value('sub_sku');

    // SUPERADMIN: fixture da conta conectada da 98 com o local escolhido.
    $this->conta = ContaVendedor::withoutGlobalScopes()->create([
        'business_id' => $this->b98, 'marketplace_id' => $this->ml->id, 'seller_id_external' => '7001',
        'location_id' => $this->loc98, 'access_token' => 't', 'refresh_token' => 'r',
    ]);

    $this->pedido = function (array $extra = [], ?string $sku = null) {
        // SUPERADMIN: fixture do pedido já gravado pela US-MKT-006.
        return PedidoMarketplace::withoutGlobalScopes()->create($extra + [
            'business_id' => $this->b98, 'account_id' => $this->conta->id, 'marketplace_id' => $this->ml->id,
            'order_id_external' => 'ML-'.uniqid(), 'status_external' => 'paid', 'total_amount' => 99.80,
            'date_created_external' => now(),
            'raw_payload' => ['order_items' => [['item' => ['id' => 'MLB-SEM-VINCULO', 'seller_sku' => $sku ?? $this->sku], 'quantity' => 2, 'unit_price' => 49.9]]],
        ]);
    };
    $this->saldo = fn () => EstoqueFixture::currentStock($this->produto, 0, $this->loc98);
});

it('US-MKT-007 · pedido pago vira venda final com o total do ML e baixa o estoque (dupla prova)', function () {
    $pedido = ($this->pedido)();

    expect(app(PedidoParaVenda::class)->converter($pedido))->toBe(PedidoParaVenda::OK);

    // SUPERADMIN: releitura direta do pedido e da venda gravados.
    $txId = (int) PedidoMarketplace::withoutGlobalScopes()->find($pedido->id)->transaction_id;
    $venda = DB::table('transactions')->where('id', $txId)->first();
    $somaLinhas = (float) DB::table('transaction_sell_lines')->where('transaction_id', $txId)->selectRaw('SUM(quantity * unit_price_inc_tax) s')->value('s');

    expect((int) $venda->business_id)->toBe($this->b98)
        ->and($venda->type)->toBe('sell')
        ->and($venda->status)->toBe('final')
        ->and((int) $venda->location_id)->toBe($this->loc98)
        ->and(round((float) $venda->final_total, 2))->toBe(99.8)
        ->and(round($somaLinhas, 2))->toBe(99.8)
        ->and($venda->payment_status)->toBe('due');

    expect(($this->saldo)())->toBe(8.0);
});

it('US-MKT-007 · a mesma notificação de novo não cria segunda venda nem baixa de novo', function () {
    $pedido = ($this->pedido)();
    app(PedidoParaVenda::class)->converter($pedido);

    expect(app(PedidoParaVenda::class)->converter($pedido))->toBe('pedido já virou venda')
        ->and(DB::table('transactions')->where('business_id', $this->b98)->where('source', 'mercado_livre')->where('staff_note', 'Pedido Mercado Livre '.$pedido->order_id_external)->count())->toBe(1)
        ->and(($this->saldo)())->toBe(8.0);
});

it('US-MKT-007 · total que não bate, pedido não pago ou conta sem local não criam venda nem mexem no estoque', function () {
    $semBater = ($this->pedido)(['total_amount' => 120.00]);
    $naoPago = ($this->pedido)(['status_external' => 'payment_required']);

    expect(app(PedidoParaVenda::class)->converter($semBater))->toStartWith('total das linhas (99.8) não bate')
        ->and(app(PedidoParaVenda::class)->converter($naoPago))->toBe('pedido ainda não está pago no Mercado Livre');

    $this->conta->forceFill(['location_id' => null])->save();
    $semLocal = ($this->pedido)();
    expect(app(PedidoParaVenda::class)->converter($semLocal))->toBe('conta sem local de venda escolhido');

    // SUPERADMIN: confere que nenhum dos três ganhou venda.
    expect(PedidoMarketplace::withoutGlobalScopes()->whereIn('id', [$semBater->id, $naoPago->id, $semLocal->id])->whereNotNull('transaction_id')->count())->toBe(0)
        ->and(($this->saldo)())->toBe(10.0);
});

it('US-MKT-007 · SKU de produto de outra empresa não é usado (98 nunca vende produto da 99)', function () {
    $produto99 = EstoqueFixture::singleProduct($this->b99);
    $sku99 = (string) DB::table('variations')->where('id', $produto99->variations[0]['variation_id'])->value('sub_sku');
    $pedido = ($this->pedido)([], $sku99);

    expect(app(PedidoParaVenda::class)->converter($pedido))->toStartWith('item MLB-SEM-VINCULO sem anúncio vinculado')
        ->and(($this->saldo)())->toBe(10.0);
});
