<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Modules\PaymentGateway\Events\CobrancaPaga;
use Modules\PaymentGateway\Models\Cobranca;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;

uses(Tests\TestCase::class);

/**
 * Polling de reconciliação PIX Inter (fallback do webhook).
 *
 * Comando `paymentgateway:inter-reconcile-pix` consulta o Inter (GET
 * /pix/v2/cob/{txid}) pelas cobranças PIX emitidas e marca as pagas.
 *
 * Multi-tenant Tier 0 (ADR 0093/0101): biz=1 (Wagner) — nunca biz=4 cliente.
 */

beforeEach(function () {
    $this->cred = PaymentGatewayCredential::query()->create([
        'business_id'  => 1,
        'gateway_key'  => 'inter',
        'ambiente'     => 'sandbox',
        'ativo'        => true,
        'nome_display' => 'Inter Reconcile Test',
        'config_json'  => [
            'client_id'     => 'fake-client',
            'client_secret' => 'fake-secret',
        ],
    ]);
});

/**
 * Http::fake — OAuth token + GET /pix/v2/cob/{txid}.
 * Txid contendo "paga" → CONCLUIDA; senão → ATIVA.
 */
function fakeInterPixConsulta(): void
{
    Http::fake(function ($request) {
        $url = $request->url();

        if (str_contains($url, '/oauth/v2/token')) {
            return Http::response(['access_token' => 'fake-token', 'expires_in' => 3600], 200);
        }

        if (str_contains($url, '/pix/v2/cob/')) {
            if (str_contains($url, 'paga')) {
                return Http::response([
                    'status' => 'CONCLUIDA',
                    'valor'  => ['original' => '150.00'],
                    'pix'    => [[
                        'endToEndId' => 'E00000000202606011000abc',
                        'txid'       => 'txid-paga-001',
                        'valor'      => '150.00',
                        'horario'    => '2026-06-01T10:00:00Z',
                    ]],
                ], 200);
            }

            return Http::response([
                'status' => 'ATIVA',
                'valor'  => ['original' => '99.00'],
            ], 200);
        }

        return Http::response([], 404);
    });
}

function novaCobrancaEmitida(PaymentGatewayCredential $cred, string $txid, int $valorCentavos = 15000): Cobranca
{
    return Cobranca::query()->create([
        'business_id'                   => $cred->business_id,
        'payment_gateway_credential_id' => $cred->id,
        'gateway_external_id'           => $txid,
        'tipo'                          => 'pix_cob',
        'status'                        => 'emitida',
        'valor_centavos'                => $valorCentavos,
        'idempotency_key'               => 'idem-' . $txid,
        'payload_gateway'               => [],
    ]);
}

it('marca cobrança PIX paga quando Inter retorna CONCLUIDA + dispara CobrancaPaga', function () {
    Event::fake([CobrancaPaga::class]);
    fakeInterPixConsulta();

    $cobranca = novaCobrancaEmitida($this->cred, 'txid-paga-001');

    $this->artisan('paymentgateway:inter-reconcile-pix', ['--business' => '1'])
        ->assertExitCode(0);

    $cobranca->refresh();
    expect($cobranca->status)->toBe('paga');
    expect($cobranca->forma_pagamento)->toBe('pix');
    expect($cobranca->valor_pago_centavos)->toBe(15000);
    expect($cobranca->paga_em)->not->toBeNull();

    Event::assertDispatched(CobrancaPaga::class, function (CobrancaPaga $e) use ($cobranca) {
        return $e->cobrancaId === $cobranca->id
            && $e->businessId === 1
            && $e->formaPagamento === 'pix';
    });
});

it('NÃO marca cobrança ainda ATIVA (não paga no Inter)', function () {
    Event::fake([CobrancaPaga::class]);
    fakeInterPixConsulta();

    $cobranca = novaCobrancaEmitida($this->cred, 'txid-ativa-002');

    $this->artisan('paymentgateway:inter-reconcile-pix', ['--business' => '1'])
        ->assertExitCode(0);

    $cobranca->refresh();
    expect($cobranca->status)->toBe('emitida');
    Event::assertNotDispatched(CobrancaPaga::class);
});

it('dry-run não altera nada nem dispara evento', function () {
    Event::fake([CobrancaPaga::class]);
    fakeInterPixConsulta();

    $cobranca = novaCobrancaEmitida($this->cred, 'txid-paga-003');

    $this->artisan('paymentgateway:inter-reconcile-pix', ['--business' => '1', '--dry-run' => true])
        ->assertExitCode(0);

    $cobranca->refresh();
    expect($cobranca->status)->toBe('emitida');
    Event::assertNotDispatched(CobrancaPaga::class);
});

it('processa lote misto: paga a CONCLUIDA, deixa a ATIVA', function () {
    Event::fake([CobrancaPaga::class]);
    fakeInterPixConsulta();

    $paga = novaCobrancaEmitida($this->cred, 'txid-paga-010');
    $ativa = novaCobrancaEmitida($this->cred, 'txid-ativa-011');

    $this->artisan('paymentgateway:inter-reconcile-pix')
        ->assertExitCode(0);

    expect($paga->fresh()->status)->toBe('paga');
    expect($ativa->fresh()->status)->toBe('emitida');
    Event::assertDispatchedTimes(CobrancaPaga::class, 1);
});

it('multi-tenant: --business=1 não toca cobrança de outro tenant', function () {
    Event::fake([CobrancaPaga::class]);
    fakeInterPixConsulta();

    $credBiz99 = PaymentGatewayCredential::query()->create([
        'business_id'  => 99,
        'gateway_key'  => 'inter',
        'ambiente'     => 'sandbox',
        'ativo'        => true,
        'nome_display' => 'Inter biz=99',
        'config_json'  => ['client_id' => 'a', 'client_secret' => 'b'],
    ]);
    $cobrancaBiz99 = novaCobrancaEmitida($credBiz99, 'txid-paga-biz99');

    $this->artisan('paymentgateway:inter-reconcile-pix', ['--business' => '1'])
        ->assertExitCode(0);

    expect($cobrancaBiz99->fresh()->status)->toBe('emitida');
    Event::assertNotDispatched(CobrancaPaga::class);
});

it('idempotente: cobrança já paga não é re-consultada nem re-dispara evento', function () {
    Event::fake([CobrancaPaga::class]);
    fakeInterPixConsulta();

    $cobranca = novaCobrancaEmitida($this->cred, 'txid-paga-020');
    $cobranca->update(['status' => 'paga', 'valor_pago_centavos' => 15000, 'forma_pagamento' => 'pix', 'paga_em' => now()]);

    $this->artisan('paymentgateway:inter-reconcile-pix', ['--business' => '1'])
        ->assertExitCode(0);

    Event::assertNotDispatched(CobrancaPaga::class);
});
