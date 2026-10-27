<?php

declare(strict_types=1);
// @covers-us US-MKT-028

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Modules\Marketplaces\Entities\ContaVendedor;
use Modules\Marketplaces\Entities\Marketplace;
use Modules\Marketplaces\Entities\PedidoMarketplace;
use Modules\Marketplaces\Entities\WebhookRecebido;
use Modules\Marketplaces\Jobs\ProcessarNotificacaoNuvemshopJob;
use Modules\Marketplaces\Jobs\VirarVendaJob;
use Modules\Marketplaces\Services\PedidoParaVenda;
use Tests\Support\EstoqueFixture;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Contrato do pedido pago da Nuvemshop que vira venda (US-MKT-028 · ADR 0443, fatia c).
 *
 * ⚠️ Eixo VALOR e ESTOQUE (regra mestre). Pedido: 2 × 49,90 = 99,80 de itens, desconto 9,80,
 * frete 15,00, total 105,00. Dupla prova:
 *  - valor: (a) soma das linhas gravadas = 99,80 = `subtotal` da loja; (b) `final_total` gravado
 *    = 105,00 = 99,80 − 9,80 + 15,00 = `total` da loja, com desconto e frete gravados na venda;
 *  - estoque: (a) saldo lido de `variation_location_details`; (b) conta à mão 10 − 2 = 8.
 * Vermelho aqui é achado, não teste a "consertar": a correção volta a [W].
 *
 * O pedido segue o formato do exemplo da documentação pública (resources/order), com dados
 * pessoais fictícios. Tenant 98 contra o 99 (ADR 0358). SKIP em SQLite.
 */
beforeEach(function () {
    if (! EstoqueFixture::schemaReady() || DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasColumn('mkt_account_credentials', 'location_id')) {
        $this->markTestSkipped('Precisa do schema MySQL com mkt_* e estoque (ADR 0358).');
    }
    config(['marketplaces.nuvemshop.user_agent' => 'oimpresso ERP (teste@example.com)']);
    $this->b98 = (int) $this->seededTenant()->id;
    $this->user98 = EstoqueFixture::userId($this->b98);
    $this->loc98 = EstoqueFixture::locationId($this->b98, '-NS');
    $this->nuvem = Marketplace::query()->where('slug', Marketplace::NUVEMSHOP)->firstOrFail();

    if (! DB::table('contacts')->where('business_id', $this->b98)->where('is_default', 1)->exists()) {
        DB::table('contacts')->insert([
            'business_id' => $this->b98, 'type' => 'customer', 'name' => 'Consumidor Final', 'contact_id' => 'CF-NS',
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

    // SUPERADMIN: fixture da loja conectada da 98 com o local escolhido.
    $this->conta = ContaVendedor::withoutGlobalScopes()->create([
        'business_id' => $this->b98, 'marketplace_id' => $this->nuvem->id, 'seller_id_external' => '817495',
        'location_id' => $this->loc98, 'access_token' => 'token-loja', 'status' => ContaVendedor::STATUS_ATIVA,
    ]);

    // Pedido no formato da API (GET /orders/{id}): preço unitário, subtotal, desconto, frete e total.
    $this->ordem = fn (array $extra = []) => $extra + [
        'id' => 871254203, 'number' => 306, 'store_id' => '817495', 'status' => 'open', 'payment_status' => 'paid',
        'subtotal' => '99.80', 'discount' => '9.80', 'shipping_cost_customer' => '15.00', 'total' => '105.00', 'currency' => 'BRL',
        'products' => [['product_id' => 111334785, 'variant_id' => '426215948', 'quantity' => '2', 'price' => '49.90', 'sku' => $this->sku, 'name' => 'Produto teste']],
        'customer' => ['id' => 105799009, 'name' => 'Cliente Ficticio', 'email' => 'cliente@example.com'],
        'created_at' => '2026-10-27T10:00:00+0000', 'paid_at' => '2026-10-27T10:05:00+0000',
    ];
    $this->pedido = function (array $extra = []) {
        $o = ($this->ordem)($extra);

        // SUPERADMIN: fixture do pedido já gravado pelo job da notificação.
        return PedidoMarketplace::withoutGlobalScopes()->create([
            'business_id' => $this->b98, 'account_id' => $this->conta->id, 'marketplace_id' => $this->nuvem->id,
            'order_id_external' => 'NS-'.uniqid(), 'status_external' => $o['payment_status'], 'total_amount' => $o['total'],
            'date_created_external' => now(), 'raw_payload' => $o,
        ]);
    };
    $this->saldo = fn () => EstoqueFixture::currentStock($this->produto, 0, $this->loc98);
});

it('US-MKT-028 · a notificação busca o pedido na loja com token e User-Agent, grava e manda virar venda', function () {
    Queue::fake();
    Http::fake(['api.nuvemshop.com.br/*' => Http::response(($this->ordem)())]);
    // SUPERADMIN: fixture da notificação recebida pela fatia b.
    $log = WebhookRecebido::withoutGlobalScopes()->create([
        'business_id' => $this->b98, 'marketplace_id' => $this->nuvem->id, 'topic' => 'order/paid',
        'resource_external' => '/orders/871254203', 'signature_valid' => true, 'processing_status' => WebhookRecebido::PENDENTE, 'received_at' => now(),
    ]);

    (new ProcessarNotificacaoNuvemshopJob($this->b98, (int) $this->conta->id, (int) $log->id))->handle();

    Http::assertSent(fn ($r) => $r->url() === 'https://api.nuvemshop.com.br/2025-03/817495/orders/871254203'
        && $r->hasHeader('Authorization', 'Bearer token-loja') && $r->hasHeader('User-Agent', 'oimpresso ERP (teste@example.com)'));
    // SUPERADMIN: releitura do pedido gravado na 98.
    $pedido = PedidoMarketplace::withoutGlobalScopes()->where('business_id', $this->b98)->where('order_id_external', '871254203')->firstOrFail();
    expect($pedido->status_external)->toBe('paid')
        ->and(round((float) $pedido->total_amount, 2))->toBe(105.0);
    Queue::assertPushed(VirarVendaJob::class, fn ($j) => $j->businessId === $this->b98 && $j->pedidoId === (int) $pedido->id);
});

it('US-MKT-028 · pedido pago vira venda com desconto e frete da loja e baixa o estoque (dupla prova)', function () {
    $pedido = ($this->pedido)();

    expect(app(PedidoParaVenda::class)->converter($pedido))->toBe(PedidoParaVenda::OK);

    // SUPERADMIN: releitura direta do pedido e da venda gravados.
    $txId = (int) PedidoMarketplace::withoutGlobalScopes()->find($pedido->id)->transaction_id;
    $venda = DB::table('transactions')->where('id', $txId)->first();
    $somaLinhas = (float) DB::table('transaction_sell_lines')->where('transaction_id', $txId)->selectRaw('SUM(quantity * unit_price_inc_tax) s')->value('s');

    expect((int) $venda->business_id)->toBe($this->b98)
        ->and($venda->source)->toBe('nuvemshop')
        ->and($venda->status)->toBe('final')
        ->and(round($somaLinhas, 2))->toBe(99.8)
        ->and(round((float) $venda->discount_amount, 2))->toBe(9.8)
        ->and(round((float) $venda->shipping_charges, 2))->toBe(15.0)
        ->and(round((float) $venda->final_total, 2))->toBe(105.0)
        ->and(round($somaLinhas - (float) $venda->discount_amount + (float) $venda->shipping_charges, 2))->toBe(105.0)
        ->and($venda->payment_status)->toBe('due');

    expect(($this->saldo)())->toBe(8.0);
});

it('US-MKT-028 · total que não fecha ou pedido não pago não criam venda nem mexem no estoque', function () {
    $semFechar = ($this->pedido)(['total' => '120.00']);
    $naoPago = ($this->pedido)(['payment_status' => 'pending']);

    expect(app(PedidoParaVenda::class)->converter($semFechar))->toStartWith('subtotal (99.8)')
        ->and(app(PedidoParaVenda::class)->converter($naoPago))->toBe('pedido ainda não está pago na Nuvemshop');

    // SUPERADMIN: confere que nenhum dos dois ganhou venda.
    expect(PedidoMarketplace::withoutGlobalScopes()->whereIn('id', [$semFechar->id, $naoPago->id])->whereNotNull('transaction_id')->count())->toBe(0)
        ->and(($this->saldo)())->toBe(10.0);
});
