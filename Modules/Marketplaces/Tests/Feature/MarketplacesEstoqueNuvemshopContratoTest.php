<?php

declare(strict_types=1);
// @covers-us US-MKT-029

use App\VariationLocationDetails;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Modules\Marketplaces\Entities\Anuncio;
use Modules\Marketplaces\Entities\ContaVendedor;
use Modules\Marketplaces\Entities\Marketplace;
use Modules\Marketplaces\Jobs\EnviarEstoqueNuvemshopJob;
use Tests\Support\EstoqueFixture;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Contrato do estoque do ERP para a loja Nuvemshop (US-MKT-029 · ADR 0443, fatia d).
 *
 * Só LÊ o estoque do ERP e envia: nada é gravado no saldo. Dupla prova do número enviado:
 * (a) `quantidadeParaLoja()` e (b) o `value` do corpo enviado à loja, os dois iguais à parte
 * inteira do saldo lido de `variation_location_details` no local da conta (7,3 → 7).
 * Agenda só com a flag ligada, a conta com `enviar_estoque` e o local da conta; 98 nunca envia o
 * anúncio da 99. Corpo e endpoint conforme a documentação pública (resources/product-variant).
 * Tenant 98 contra 99 (ADR 0358). SKIP em SQLite.
 */
beforeEach(function () {
    if (! EstoqueFixture::schemaReady() || DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasColumn('mkt_account_credentials', 'enviar_estoque')) {
        $this->markTestSkipped('Precisa do schema MySQL com mkt_* e estoque (ADR 0358).');
    }
    config(['marketplaces.nuvemshop.ativo' => true, 'marketplaces.nuvemshop.user_agent' => 'oimpresso ERP (teste@example.com)']);
    Queue::fake();
    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    $this->loc98 = EstoqueFixture::locationId($this->b98, '-NSE');
    $this->nuvem = (int) Marketplace::query()->where('slug', Marketplace::NUVEMSHOP)->value('id');

    $this->produto = EstoqueFixture::singleProduct($this->b98);
    $this->variacao = (int) $this->produto->variations[0]['variation_id'];
    EstoqueFixture::setStock($this->produto, 0, $this->loc98, 7.3);

    // SUPERADMIN: fixture da loja conectada da 98, com local e envio de estoque ligado.
    $this->conta = ContaVendedor::withoutGlobalScopes()->create([
        'business_id' => $this->b98, 'marketplace_id' => $this->nuvem, 'seller_id_external' => '817495',
        'location_id' => $this->loc98, 'enviar_estoque' => true, 'access_token' => 'token-loja', 'status' => ContaVendedor::STATUS_ATIVA,
    ]);
    $this->anuncio = Anuncio::withoutGlobalScopes()->create([
        'business_id' => $this->b98, 'account_id' => $this->conta->id, 'marketplace_id' => $this->nuvem,
        'product_id' => $this->produto->productId, 'variation_id' => $this->variacao,
        'item_id_external' => '144', 'status' => 'active', 'attributes' => ['product_id' => '555'],
    ]);
    $this->mudarSaldo = function (float $qtd) {
        VariationLocationDetails::query()->where('variation_id', $this->variacao)->where('location_id', $this->loc98)->firstOrFail()
            ->update(['qty_available' => $qtd]);
    };
});

it('US-MKT-029 · saldo mudou no local da conta agenda o envio do anúncio', function () {
    ($this->mudarSaldo)(5);

    Queue::assertPushed(EnviarEstoqueNuvemshopJob::class, fn ($j) => $j->businessId === $this->b98 && $j->anuncioId === (int) $this->anuncio->id);
});

it('US-MKT-029 · conta sem envio de estoque ou flag desligada não agenda nada', function () {
    $this->conta->forceFill(['enviar_estoque' => false])->save();
    ($this->mudarSaldo)(5);
    Queue::assertNotPushed(EnviarEstoqueNuvemshopJob::class);

    $this->conta->forceFill(['enviar_estoque' => true])->save();
    config(['marketplaces.nuvemshop.ativo' => false]);
    ($this->mudarSaldo)(4);
    Queue::assertNotPushed(EnviarEstoqueNuvemshopJob::class);
});

it('US-MKT-029 · envia a parte inteira do saldo para a variante da loja (dupla prova)', function () {
    Http::fake(['api.nuvemshop.com.br/*' => Http::response([['id' => 144, 'stock' => 7]])]);
    $saldoLido = (float) DB::table('variation_location_details')->where('variation_id', $this->variacao)->where('location_id', $this->loc98)->value('qty_available');

    expect(EnviarEstoqueNuvemshopJob::quantidadeParaLoja($this->b98, (int) $this->anuncio->id))->toBe((int) floor($saldoLido))->toBe(7);

    (new EnviarEstoqueNuvemshopJob($this->b98, (int) $this->anuncio->id))->handle();

    Http::assertSent(fn (HttpRequest $r) => $r->url() === 'https://api.nuvemshop.com.br/2025-03/817495/products/555/variants/stock'
        && $r['action'] === 'replace' && $r['value'] === 7 && $r['id'] === 144
        && $r->hasHeader('Authorization', 'Bearer token-loja') && $r->hasHeader('User-Agent', 'oimpresso ERP (teste@example.com)'));
    expect((int) $this->anuncio->fresh()->stock_marketplace)->toBe(7)
        ->and((float) DB::table('variation_location_details')->where('variation_id', $this->variacao)->where('location_id', $this->loc98)->value('qty_available'))->toBe(7.3);
});

it('US-MKT-029 · a 98 não envia o anúncio da 99', function () {
    Http::fake();
    // SUPERADMIN: fixture da conta e do anúncio da 99.
    $conta99 = ContaVendedor::withoutGlobalScopes()->create([
        'business_id' => $this->b99, 'marketplace_id' => $this->nuvem, 'seller_id_external' => '999',
        'location_id' => $this->loc98, 'enviar_estoque' => true, 'access_token' => 'token-99', 'status' => ContaVendedor::STATUS_ATIVA,
    ]);
    $anuncio99 = Anuncio::withoutGlobalScopes()->create([
        'business_id' => $this->b99, 'account_id' => $conta99->id, 'marketplace_id' => $this->nuvem,
        'product_id' => $this->produto->productId, 'variation_id' => $this->variacao, 'item_id_external' => '777', 'status' => 'active',
    ]);

    expect(EnviarEstoqueNuvemshopJob::quantidadeParaLoja($this->b98, (int) $anuncio99->id))->toBeNull();
    (new EnviarEstoqueNuvemshopJob($this->b98, (int) $anuncio99->id))->handle();
    Http::assertNothingSent();
});
