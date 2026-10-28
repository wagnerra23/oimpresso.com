<?php

declare(strict_types=1);
// @covers-us US-MKT-027

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Marketplaces\Entities\ContaVendedor;
use Modules\Marketplaces\Entities\Marketplace;
use Modules\Marketplaces\Entities\WebhookRecebido;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Contrato do receptor de notificações da Nuvemshop (US-MKT-027 · ADR 0443, fatia b).
 *
 * Prova: flag desligada responde 404 e não grava; sem HMAC válido responde 401 e não grava; com
 * HMAC válido grava o log PENDENTE na empresa da conta ligada à loja (98) e nunca em outra (99);
 * `store/redact` desconecta a conta e apaga o token; `customers/redact` fica pendente e o log NÃO
 * guarda e-mail, telefone nem documento do comprador.
 *
 * Os corpos são os exemplos da documentação pública (tiendanube.github.io/api-documentation/
 * resources/webhook). Tenant 98 contra 99 (ADR 0358). ⚠️ SKIP em SQLite (schema UltimatePOS).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema MySQL do UltimatePOS (ADR 0358).');
    }
    if (! Schema::hasTable('mkt_webhook_log')) {
        $this->markTestSkipped('Tabelas mkt_* ausentes — migrations do Modules/Marketplaces não rodaram.');
    }
    config(['services.nuvemshop.ativo' => true, 'services.nuvemshop.client_secret' => 'segredo-app-teste']);
    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    $this->nuvem = (int) Marketplace::query()->where('slug', Marketplace::NUVEMSHOP)->value('id');

    // SUPERADMIN: monta as contas das duas empresas para o teste; a 99 é de OUTRA loja.
    $this->conta98 = ContaVendedor::withoutGlobalScopes()->create([
        'business_id' => $this->b98, 'marketplace_id' => $this->nuvem, 'seller_id_external' => '123',
        'access_token' => 'token-loja-98', 'status' => ContaVendedor::STATUS_ATIVA,
    ]);
    ContaVendedor::withoutGlobalScopes()->create([
        'business_id' => $this->b99, 'marketplace_id' => $this->nuvem, 'seller_id_external' => '555',
        'access_token' => 'token-loja-99', 'status' => ContaVendedor::STATUS_ATIVA,
    ]);
});

/** Envia o corpo cru com a assinatura do app (ou a assinatura dada). */
function nsPost($t, string $url, array $corpo, ?string $assinatura = null)
{
    $cru = json_encode($corpo);

    return $t->call('POST', $url, [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_LINKEDSTORE_HMAC_SHA256' => $assinatura ?? hash_hmac('sha256', (string) $cru, 'segredo-app-teste'),
    ], $cru);
}

function nsLogs(int $mkt)
{
    // SUPERADMIN: lê os logs das duas empresas para provar onde a notificação caiu.
    return WebhookRecebido::withoutGlobalScopes()->where('marketplace_id', $mkt)->get();
}

it('US-MKT-027 · flag desligada responde 404 e não grava nada', function () {
    config(['services.nuvemshop.ativo' => false]);

    nsPost($this, '/webhook/marketplaces/nuvemshop', ['store_id' => 123, 'event' => 'order/paid', 'id' => 871254203])->assertNotFound();

    expect(nsLogs($this->nuvem))->toHaveCount(0);
});

it('US-MKT-027 · assinatura inválida responde 401 e não grava nada', function () {
    nsPost($this, '/webhook/marketplaces/nuvemshop', ['store_id' => 123, 'event' => 'order/paid', 'id' => 871254203], str_repeat('0', 64))
        ->assertStatus(401);

    expect(nsLogs($this->nuvem))->toHaveCount(0);
});

it('US-MKT-027 · evento assinado grava o log pendente só na empresa da loja', function () {
    nsPost($this, '/webhook/marketplaces/nuvemshop', ['store_id' => 123, 'event' => 'order/paid', 'id' => 871254203])->assertOk();

    $logs = nsLogs($this->nuvem);
    expect($logs)->toHaveCount(1)
        ->and((int) $logs[0]->business_id)->toBe($this->b98)
        ->and($logs[0]->topic)->toBe('order/paid')
        ->and($logs[0]->resource_external)->toBe('/orders/871254203')
        ->and($logs[0]->processing_status)->toBe(WebhookRecebido::PENDENTE)
        ->and($logs[0]->signature_valid)->toBeTrue();
});

it('US-MKT-027 · loja não conectada responde 200 e não grava', function () {
    nsPost($this, '/webhook/marketplaces/nuvemshop', ['store_id' => 999, 'event' => 'order/paid', 'id' => 1])->assertOk();

    expect(nsLogs($this->nuvem))->toHaveCount(0);
});

it('US-MKT-027 · store/redact desconecta a conta da loja e apaga o token', function () {
    nsPost($this, '/webhook/marketplaces/nuvemshop/store-redact', ['store_id' => 123])->assertOk();

    $conta = $this->conta98->fresh();
    expect($conta->status)->toBe(ContaVendedor::STATUS_REVOGADA)
        ->and($conta->access_token)->toBeNull()
        ->and(nsLogs($this->nuvem)->first()->processing_status)->toBe(WebhookRecebido::PROCESSADO);
});

it('US-MKT-027 · customers/redact fica pendente e o log não guarda dado pessoal do comprador', function () {
    nsPost($this, '/webhook/marketplaces/nuvemshop/customers-redact', [
        'store_id' => 123,
        'customer' => ['id' => 1, 'email' => 'email@email.com', 'phone' => '+5511999990000', 'identification' => '11122233344'],
        'orders_to_redact' => [213, 3415, 21515],
    ])->assertOk();

    $log = nsLogs($this->nuvem)->first();
    $gravado = (string) DB::table('mkt_webhook_log')->where('id', $log->id)->value('payload');
    expect($log->topic)->toBe('customers/redact')
        ->and($log->processing_status)->toBe(WebhookRecebido::PENDENTE)
        ->and($log->payload['customer'])->toBe(['id' => 1])
        ->and($log->payload['orders_to_redact'])->toBe([213, 3415, 21515])
        ->and(str_contains($gravado, 'email@email.com'))->toBeFalse()
        ->and(str_contains($gravado, '11122233344'))->toBeFalse()
        ->and(str_contains($gravado, '+5511999990000'))->toBeFalse();
});
