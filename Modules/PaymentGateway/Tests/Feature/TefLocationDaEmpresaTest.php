<?php
// @covers-us US-SELL-075

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EstoqueFixture;

/**
 * TEF: a loja (location_id) enviada pelo caixa tem de ser da empresa da sessão.
 *
 * Auditoria PaymentGateway 2026-11-19, lote 3 item 3.3 (achado A14): o endpoint
 * aceitava qualquer inteiro e gravava em tef_transacoes.location_id.
 *
 * Tenant 98 (ADR 0358) contra o adversário 99 criado pelo próprio teste.
 * Fica em Modules/PaymentGateway/Tests para rodar na lane do PaymentGateway.
 */
uses(DatabaseTransactions::class, \Tests\Support\WithSeededTenant::class);

beforeEach(function () {
    if (! EstoqueFixture::schemaReady() || ! Schema::hasTable('tef_transacoes')) {
        $this->markTestSkipped('Schema ausente — roda na lane MySQL do PaymentGateway.');
    }
    $this->biz = EstoqueFixture::businessId();
    config([
        'sells.tef.enabled'      => true,
        'sells.tef.business_ids' => [$this->biz],
        'sells.tef.driver'       => 'simulador',
    ]);
    $this->user = $this->usuarioComPermissoes(['sell.create'], \App\Business::findOrFail($this->biz));
    $this->caixa = fn () => $this->actingAs($this->user)
        ->withSession(['user.business_id' => $this->biz, 'business.id' => $this->biz]);
});

it('loja de outra empresa é recusada e nenhuma transação é gravada', function () {
    $adversario = $this->seededSupportClientTenant();
    $lojaAlheia = EstoqueFixture::locationId((int) $adversario->id, '-tef');

    ($this->caixa)()->postJson('/paymentgateway/tef/cobrancas', [
        'valor' => '10.00', 'modalidade' => 'debito', 'idempotency_key' => 'k-loja-99',
        'location_id' => $lojaAlheia,
    ])->assertUnprocessable()->assertJsonValidationErrors('location_id');

    expect(DB::table('tef_transacoes')->where('idempotency_key', 'k-loja-99')->count())->toBe(0);
});

it('loja inexistente é recusada', function () {
    $semLoja = (int) DB::table('business_locations')->max('id') + 1000;

    ($this->caixa)()->postJson('/paymentgateway/tef/cobrancas', [
        'valor' => '10.00', 'modalidade' => 'debito', 'idempotency_key' => 'k-loja-x',
        'location_id' => $semLoja,
    ])->assertUnprocessable()->assertJsonValidationErrors('location_id');

    expect(DB::table('tef_transacoes')->where('idempotency_key', 'k-loja-x')->count())->toBe(0);
});

it('loja da própria empresa é aceita e gravada na transação', function () {
    $loja = EstoqueFixture::locationId($this->biz, '-tef');

    $id = ($this->caixa)()->postJson('/paymentgateway/tef/cobrancas', [
        'valor' => '10.00', 'modalidade' => 'debito', 'idempotency_key' => 'k-loja-98',
        'location_id' => $loja,
    ])->assertCreated()->json('id');

    expect(DB::table('tef_transacoes')->where('id', $id)->value('location_id'))->toBe($loja)
        ->and(DB::table('tef_transacoes')->where('id', $id)->value('business_id'))->toBe($this->biz);
});
