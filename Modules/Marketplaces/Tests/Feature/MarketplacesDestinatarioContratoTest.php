<?php

declare(strict_types=1);
// @covers-us US-MKT-009

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Modules\Marketplaces\Entities\ContaVendedor;
use Modules\Marketplaces\Entities\Marketplace;
use Modules\Marketplaces\Entities\PedidoMarketplace;
use Modules\Marketplaces\Entities\WebhookRecebido;
use Modules\Marketplaces\Jobs\ProcessarNotificacaoMercadoLivreJob;
use Modules\Marketplaces\Services\MercadoLivre\DestinatarioMercadoLivre;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Contrato do destinatário da NF-e vindo do faturamento do Mercado Livre (US-MKT-009).
 *
 * Saída = chaves de `contacts`, a interface combinada com a frente Fiscal para
 * `NfeService::emitirParaTransaction(..., destinatarioExterno: [...])`.
 * ⚠️ Os dois formatos das fixtures seguem a documentação indexada do ML (a oficial deu 403 em
 * 2026-10-26) — reconferir contra uma resposta real quando o app existir. Documentos sintéticos.
 */
it('US-MKT-009 · formato novo (identification/address/taxes) vira as chaves de contacts', function () {
    $d = DestinatarioMercadoLivre::mapear(['billing_info' => [
        'name' => 'Loja Teste', 'identification' => ['type' => 'CNPJ', 'number' => '11.222.333/0001-81'],
        'address' => ['street_name' => 'Rua A', 'street_number' => '10', 'neighborhood' => 'Centro', 'city_name' => 'Tubarão',
            'state' => ['code' => 'BR-SC', 'name' => 'Santa Catarina'], 'zip_code' => '88700-000'],
        'taxes' => ['inscriptions' => ['state_registration' => '123456789']],
    ]]);

    expect($d)->toBe([
        'tax_number' => '11222333000181', 'name' => 'Loja Teste', 'inscricao_estadual' => '123456789', 'indicador_ie' => 1,
        'address_line_1' => 'Rua A', 'numero' => '10', 'neighborhood' => 'Centro', 'city' => 'Tubarão', 'city_code' => null,
        'state' => 'SC', 'zip_code' => '88700000', 'email' => null,
    ]);
});

it('US-MKT-009 · formato legado (doc_number + additional_info) e pessoa física sem IE', function () {
    $d = DestinatarioMercadoLivre::mapear(['billing_info' => ['doc_type' => 'CPF', 'doc_number' => '12345678909', 'additional_info' => [
        ['type' => 'FIRST_NAME', 'value' => 'Ana'], ['type' => 'LAST_NAME', 'value' => 'Teste'],
        ['type' => 'STREET_NAME', 'value' => 'Av. B'], ['type' => 'STREET_NUMBER', 'value' => '200'],
        ['type' => 'CITY_NAME', 'value' => 'São Paulo'], ['type' => 'STATE_CODE', 'value' => 'SP'], ['type' => 'ZIP_CODE', 'value' => '01001000'],
    ]]]);

    expect($d['tax_number'])->toBe('12345678909')
        ->and($d['name'])->toBe('Ana Teste')
        ->and($d['indicador_ie'])->toBe(9)
        ->and($d['inscricao_estadual'])->toBeNull()
        ->and($d['state'])->toBe('SP')
        ->and($d['address_line_1'])->toBe('Av. B')
        ->and($d['neighborhood'])->toBeNull();
});

it('US-MKT-009 · sem CPF ou CNPJ válido não há destinatário', function () {
    expect(DestinatarioMercadoLivre::mapear(['billing_info' => ['doc_number' => null]]))->toBeNull()
        ->and(DestinatarioMercadoLivre::mapear(['billing_info' => ['doc_number' => '123']]))->toBeNull()
        ->and(DestinatarioMercadoLivre::mapear([]))->toBeNull();
});

it('US-MKT-009 · pedido pago busca o faturamento uma vez e guarda documento e UF na empresa da conta', function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('mkt_orders')) {
        $this->markTestSkipped('Precisa do schema MySQL com as tabelas mkt_* (ADR 0358).');
    }
    Queue::fake();
    $biz = (int) $this->seededTenant()->id;
    $ml = Marketplace::query()->where('slug', Marketplace::MERCADO_LIVRE)->firstOrFail();
    // SUPERADMIN: fixture da conta e da notificação da 98.
    $conta = ContaVendedor::withoutGlobalScopes()->create([
        'business_id' => $biz, 'marketplace_id' => $ml->id, 'seller_id_external' => '8100',
        'access_token' => 't', 'refresh_token' => 'r', 'access_token_expires_at' => now()->addHours(3),
    ]);
    $log = WebhookRecebido::withoutGlobalScopes()->create([
        'business_id' => $biz, 'marketplace_id' => $ml->id, 'topic' => 'orders_v2', 'resource_external' => '/orders/3000001',
        'signature_valid' => true, 'received_at' => now(),
    ]);
    Http::fake([
        'api.mercadolibre.com/orders/3000001' => Http::response(['id' => 3000001, 'status' => 'paid', 'total_amount' => 10, 'seller' => ['id' => 8100], 'buyer' => ['id' => 5]]),
        'api.mercadolibre.com/orders/3000001/billing_info' => Http::response(['billing_info' => ['doc_number' => '12345678909', 'additional_info' => [['type' => 'STATE_CODE', 'value' => 'SP']]]]),
    ]);

    foreach ([1, 2] as $_) {
        app()->call([new ProcessarNotificacaoMercadoLivreJob($biz, (int) $conta->id, (int) $log->id), 'handle']);
    }

    // SUPERADMIN: releitura direta.
    $comprador = PedidoMarketplace::withoutGlobalScopes()->where('business_id', $biz)->where('order_id_external', '3000001')->firstOrFail()->buyer_external;
    expect($comprador['documento'])->toBe('12345678909')
        ->and($comprador['uf'])->toBe('SP')
        ->and($comprador['destinatario']['tax_number'])->toBe('12345678909');
    Http::assertSentCount(3); // pedido + faturamento + pedido de novo (o faturamento não é buscado na 2ª vez)
});
