<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Modules\PaymentGateway\Dto\CardToken;
use Modules\PaymentGateway\Dto\EmitirCobrancaInput;
use Modules\PaymentGateway\Exceptions\CardDeclinedException;
use Modules\PaymentGateway\Exceptions\CredentialMisconfiguredException;
use Modules\PaymentGateway\Exceptions\DriverNotSupportedException;
use Modules\PaymentGateway\Exceptions\GatewayUnavailableException;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;
use Modules\PaymentGateway\Services\Drivers\PagarmeDriver;

uses(Tests\TestCase::class);

/**
 * Helper: cria PaymentGatewayCredential in-memory (sem save) — testes do
 * driver isoladamente NÃO precisam do registro persistido em DB. Isso evita
 * dependência de `RefreshDatabase` que, neste worktree, tropeça em migrations
 * MySQL-only do projeto (ALTER TABLE MODIFY COLUMN) incompatíveis com SQLite.
 */
function makeCred(array $configOverride = []): PaymentGatewayCredential
{
    $cred = new PaymentGatewayCredential();
    $cred->setRawAttributes([
        'id'           => 99,
        'business_id'  => 1,
        'gateway_key'  => 'pagarme',
        'ambiente'     => 'sandbox',
        'ativo'        => true,
        'nome_display' => 'Pagar.me Test',
        'config_json'  => json_encode(array_merge([
            'secret_key'     => 'sk_test_fake_pagarme_token',
            'webhook_secret' => 'whsec_fake_pagarme',
        ], $configOverride)),
    ], true);
    $cred->exists = true;

    return $cred;
}

beforeEach(function () {
    $this->cred = makeCred();
});

// ─── (1) key + supports ──────────────────────────────────────────────────

it('PagarmeDriver key=pagarme e supports boleto/pix_cob/card', function () {
    $d = new PagarmeDriver();
    expect($d->key())->toBe('pagarme');
    expect($d->supports('boleto'))->toBeTrue();
    expect($d->supports('pix_cob'))->toBeTrue();
    expect($d->supports('card'))->toBeTrue();
    expect($d->supports('pix_cobv'))->toBeFalse();
    expect($d->supports('pix_recv'))->toBeFalse();
});

// ─── (2) emitirBoleto ────────────────────────────────────────────────────

it('emitirBoleto Pagar.me pipeline OK', function () {
    Http::fake([
        '*/orders' => Http::response([
            'id'      => 'or_001',
            'code'    => 'sale:pagarme-boleto-001',
            'status'  => 'pending',
            'charges' => [[
                'id'             => 'ch_boleto_001',
                'status'         => 'pending',
                'payment_method' => 'boleto',
                'last_transaction' => [
                    'line'    => '03399.65656 12345.678901 12345.678901 1 99990000010000',
                    'barcode' => '03399999900000100001234567890123456789012345',
                    'pdf'     => 'https://api.pagar.me/core/v5/transactions/tr_001/pdf',
                    'url'     => 'https://pagar.me/boleto/visualizar/ch_boleto_001',
                ],
            ]],
        ], 200),
    ]);

    $d = new PagarmeDriver();
    $result = $d->emitirBoleto(new EmitirCobrancaInput(
        businessId: 1,
        contactId: 200,
        valorCentavos: 15000,
        vencimento: new DateTimeImmutable('+5 days'),
        descricao: 'Pagar.me teste boleto',
        idempotencyKey: 'sale:pagarme-boleto-001',
        meta: ['payer_cpf_cnpj' => '12345678900', 'payer_name' => 'João Pagador', 'payer_email' => 'joao@x.com'],
    ), $this->cred);

    expect($result->tipo)->toBe('boleto');
    expect($result->gatewayExternalId)->toBe('ch_boleto_001');
    expect($result->linhaDigitavel)->toContain('03399');
    expect($result->codigoBarras)->toContain('03399');
    expect($result->boletoPdfUrl)->toContain('pdf');

    // Garante shape do payload enviado (auth basic, items, customer, payment_method)
    Http::assertSent(function ($r) {
        return str_contains($r->url(), '/core/v5/orders')
            && $r->method() === 'POST'
            && $r['customer']['document'] === '12345678900'
            && $r['customer']['document_type'] === 'CPF'
            && $r['items'][0]['amount'] === 15000
            && $r['payments'][0]['payment_method'] === 'boleto'
            && ! empty($r['payments'][0]['boleto']['due_at']);
    });
});

// ─── (3) emitirPix tipo=cob ──────────────────────────────────────────────

it('emitirPix Pagar.me tipo=cob OK', function () {
    Http::fake([
        '*/orders' => Http::response([
            'id'      => 'or_pix_001',
            'status'  => 'pending',
            'charges' => [[
                'id'             => 'ch_pix_001',
                'status'         => 'pending',
                'payment_method' => 'pix',
                'last_transaction' => [
                    'qr_code'     => '00020126830014br.gov.bcb.pix...PAGARME-EMV-fake...6304A1B2',
                    'qr_code_url' => 'https://api.pagar.me/qr/ch_pix_001.png',
                ],
            ]],
        ], 200),
    ]);

    $d = new PagarmeDriver();
    $result = $d->emitirPix(new EmitirCobrancaInput(
        businessId: 1, contactId: 200, valorCentavos: 5000,
        vencimento: new DateTimeImmutable('+1 day'),
        descricao: 'PIX Pagar.me', idempotencyKey: 'sale:pagarme-pix-001',
        meta: ['payer_cpf_cnpj' => '12345678900', 'payer_name' => 'João Pix'],
    ), $this->cred, 'cob');

    expect($result->tipo)->toBe('pix_cob');
    expect($result->gatewayExternalId)->toBe('ch_pix_001');
    expect($result->pixEmv)->toContain('00020126');
    expect($result->pixQrCodePath)->toContain('qr/ch_pix_001');
});

it('emitirPix tipo=cobv → DriverNotSupportedException no Pagar.me', function () {
    $d = new PagarmeDriver();
    expect(fn () => $d->emitirPix(
        new EmitirCobrancaInput(
            businessId: 1, contactId: 1, valorCentavos: 100,
            vencimento: new DateTimeImmutable(), descricao: 'x', idempotencyKey: 'k',
            meta: ['payer_cpf_cnpj' => '12345678900', 'payer_name' => 'X'],
        ),
        $this->cred,
        'cobv',
    ))->toThrow(DriverNotSupportedException::class);
});

it('emitirPixAutomatico → DriverNotSupportedException no Pagar.me', function () {
    $d = new PagarmeDriver();
    expect(fn () => $d->emitirPixAutomatico(
        new EmitirCobrancaInput(
            businessId: 1, contactId: 1, valorCentavos: 100,
            vencimento: new DateTimeImmutable(), descricao: 'x', idempotencyKey: 'k',
            meta: ['payer_cpf_cnpj' => '12345678900', 'payer_name' => 'X'],
        ),
        $this->cred,
    ))->toThrow(DriverNotSupportedException::class);
});

// ─── (4) cobrarCartao ───────────────────────────────────────────────────

it('cobrarCartao Pagar.me com card_token → CobrancaEmitida', function () {
    Http::fake([
        '*/orders' => Http::response([
            'id'      => 'or_card_001',
            'status'  => 'paid',
            'charges' => [[
                'id'             => 'ch_card_001',
                'status'         => 'paid',
                'payment_method' => 'credit_card',
            ]],
        ], 200),
    ]);

    $d = new PagarmeDriver();
    $token = new CardToken(
        token: 'card_token_abc',
        brand: 'visa',
        lastFour: '4242',
        holderName: 'João Cartão',
        expMonth: '12',
        expYear: '2030',
    );
    $result = $d->cobrarCartao(new EmitirCobrancaInput(
        businessId: 1, contactId: 200, valorCentavos: 9999,
        vencimento: new DateTimeImmutable('+1 day'),
        descricao: 'card teste', idempotencyKey: 'card:001',
        meta: ['payer_cpf_cnpj' => '12345678900', 'payer_name' => 'João', 'installments' => 3],
    ), $this->cred, $token);

    expect($result->tipo)->toBe('card');
    expect($result->gatewayExternalId)->toBe('ch_card_001');

    Http::assertSent(fn ($r) => $r['payments'][0]['payment_method'] === 'credit_card'
        && $r['payments'][0]['credit_card']['card_token'] === 'card_token_abc'
        && $r['payments'][0]['credit_card']['installments'] === 3);
});

it('cobrarCartao 400 → CardDeclinedException', function () {
    Http::fake([
        '*/orders' => Http::response(['errors' => [['message' => 'invalid_card']]], 400),
    ]);

    $d = new PagarmeDriver();
    $token = new CardToken(token: 'tok_x', brand: 'visa', lastFour: '4242', holderName: 'X', expMonth: '12', expYear: '2030');

    expect(fn () => $d->cobrarCartao(
        new EmitirCobrancaInput(
            businessId: 1, contactId: 200, valorCentavos: 9999,
            vencimento: new DateTimeImmutable('+1 day'), descricao: 'card', idempotencyKey: 'card:002',
            meta: ['payer_cpf_cnpj' => '12345678900', 'payer_name' => 'X'],
        ),
        $this->cred,
        $token,
    ))->toThrow(CardDeclinedException::class);
});

it('cobrarCartao charge.status=failed → CardDeclinedException', function () {
    Http::fake([
        '*/orders' => Http::response([
            'id'      => 'or_x',
            'charges' => [[
                'id'              => 'ch_failed',
                'status'          => 'failed',
                'last_transaction' => ['acquirer_message' => 'Cartão recusado pelo emissor'],
            ]],
        ], 200),
    ]);

    $d = new PagarmeDriver();
    $token = new CardToken(token: 'tok_x', brand: 'visa', lastFour: '4242', holderName: 'X', expMonth: '12', expYear: '2030');

    expect(fn () => $d->cobrarCartao(
        new EmitirCobrancaInput(
            businessId: 1, contactId: 200, valorCentavos: 100,
            vencimento: new DateTimeImmutable('+1 day'), descricao: 'card', idempotencyKey: 'card:003',
            meta: ['payer_cpf_cnpj' => '12345678900', 'payer_name' => 'X'],
        ),
        $this->cred,
        $token,
    ))->toThrow(CardDeclinedException::class);
});

// ─── (5) consultar ───────────────────────────────────────────────────────

it('consultar Pagar.me mapeia status canon', function () {
    Http::fake([
        '*/charges/ch_paga' => Http::response([
            'id'             => 'ch_paga',
            'status'         => 'paid',
            'paid_at'        => '2026-05-19T12:34:56Z',
            'paid_amount'    => 14500,
            'payment_method' => 'boleto',
        ], 200),
    ]);

    $cobranca = (object) ['gateway_external_id' => 'ch_paga'];
    $status = (new PagarmeDriver())->consultar($cobranca, $this->cred);

    expect($status->status)->toBe('paga');
    expect($status->valorPagoCentavos)->toBe(14500);
    expect($status->formaPagamento)->toBe('boleto');
});

it('consultar Pagar.me status=failed → erro', function () {
    Http::fake([
        '*/charges/ch_fail' => Http::response([
            'id'             => 'ch_fail',
            'status'         => 'failed',
            'payment_method' => 'credit_card',
        ], 200),
    ]);

    $cobranca = (object) ['gateway_external_id' => 'ch_fail'];
    $status = (new PagarmeDriver())->consultar($cobranca, $this->cred);
    expect($status->status)->toBe('erro');
});

// ─── (6) cancelar + refund ───────────────────────────────────────────────

it('cancelar Pagar.me chama DELETE /charges/{id}', function () {
    Http::fake(['*/charges/ch_cancel' => Http::response(['id' => 'ch_cancel', 'status' => 'canceled'], 200)]);

    $cobranca = (object) ['gateway_external_id' => 'ch_cancel'];
    (new PagarmeDriver())->cancelar($cobranca, $this->cred, 'Cliente desistiu');

    Http::assertSent(fn ($r) => $r->method() === 'DELETE' && str_contains($r->url(), '/charges/ch_cancel'));
});

it('refund Pagar.me parcial envia amount em centavos', function () {
    Http::fake(['*/charges/ch_refund' => Http::response(['status' => 'partial_canceled'], 200)]);

    $cobranca = (object) ['gateway_external_id' => 'ch_refund'];
    (new PagarmeDriver())->refund($cobranca, $this->cred, 5000, 'Estorno parcial');

    Http::assertSent(function ($r) {
        if ($r->method() !== 'DELETE' || ! str_contains($r->url(), '/charges/ch_refund')) {
            return false;
        }
        $body = json_decode($r->body(), true) ?? [];
        return ($body['amount'] ?? null) === 5000;
    });
});

it('refund Pagar.me total NÃO envia amount no body', function () {
    Http::fake(['*/charges/ch_refund_total' => Http::response(['status' => 'canceled'], 200)]);

    $cobranca = (object) ['gateway_external_id' => 'ch_refund_total'];
    (new PagarmeDriver())->refund($cobranca, $this->cred, null, 'Total');

    Http::assertSent(function ($r) {
        if ($r->method() !== 'DELETE' || ! str_contains($r->url(), 'ch_refund_total')) {
            return false;
        }
        $body = json_decode($r->body(), true) ?? [];
        return ! array_key_exists('amount', $body);
    });
});

// ─── (7) healthCheck ─────────────────────────────────────────────────────

it('healthCheck OK quando GET /balance retorna 200', function () {
    Http::fake(['*/balance' => Http::response(['available_amount' => 100000, 'currency' => 'BRL'], 200)]);

    $h = (new PagarmeDriver())->healthCheck($this->cred);
    expect($h->ok)->toBeTrue();
    expect($h->status)->toBeIn(['ok', 'degraded']);
});

it('healthCheck down em 401', function () {
    Http::fake(['*/balance' => Http::response(['errors' => ['unauthorized']], 401)]);

    $h = (new PagarmeDriver())->healthCheck($this->cred);
    expect($h->ok)->toBeFalse();
    expect($h->status)->toBe('down');
});

// ─── (8) processWebhook ──────────────────────────────────────────────────

it('processWebhook lê data.id (charge id) e type', function () {
    $d = new PagarmeDriver();

    $r1 = $d->processWebhook([
        'id'   => 'hook_001',
        'type' => 'charge.paid',
        'data' => ['id' => 'ch_paid_001', 'status' => 'paid'],
    ], $this->cred);
    expect($r1->gateway_external_id)->toBe('ch_paid_001');
    expect($r1->event_type)->toBe('charge.paid');
    expect($r1->gateway_key)->toBe('pagarme');

    $r2 = $d->processWebhook([
        'id'   => 'hook_002',
        'type' => 'charge.refunded',
        'data' => ['charge' => ['id' => 'ch_002']],
    ], $this->cred);
    expect($r2->gateway_external_id)->toBe('ch_002');

    $r3 = $d->processWebhook(['type' => 'foo.bar'], $this->cred);
    expect($r3)->toBeNull();
});

// ─── (9) error paths ────────────────────────────────────────────────────

it('emitirBoleto 500 → GatewayUnavailableException', function () {
    Http::fake(['*/orders' => Http::response(['error' => 'internal'], 500)]);

    $d = new PagarmeDriver();
    expect(fn () => $d->emitirBoleto(
        new EmitirCobrancaInput(
            businessId: 1, contactId: 1, valorCentavos: 100,
            vencimento: new DateTimeImmutable('+1 day'), descricao: 'x', idempotencyKey: 'k',
            meta: ['payer_cpf_cnpj' => '12345678900', 'payer_name' => 'X'],
        ),
        $this->cred,
    ))->toThrow(GatewayUnavailableException::class);
});

it('credential gateway_key errado → CredentialMisconfigured', function () {
    $bad = new PaymentGatewayCredential();
    $bad->setRawAttributes([
        'id' => 88, 'business_id' => 1, 'gateway_key' => 'asaas',
        'ambiente' => 'sandbox', 'ativo' => true,
        'config_json' => json_encode(['api_key' => 'x']),
    ], true);
    expect(fn () => (new PagarmeDriver())->healthCheck($bad))
        ->toThrow(CredentialMisconfiguredException::class);
});

it('credential sem secret_key → CredentialMisconfigured', function () {
    $bad = new PaymentGatewayCredential();
    $bad->setRawAttributes([
        'id' => 87, 'business_id' => 1, 'gateway_key' => 'pagarme',
        'ambiente' => 'sandbox', 'ativo' => true,
        'config_json' => json_encode(['webhook_secret' => 'x']), // sem secret_key
    ], true);
    expect(fn () => (new PagarmeDriver())->healthCheck($bad))
        ->toThrow(CredentialMisconfiguredException::class);
});

it('Pagar.me exige payer_cpf_cnpj → InvalidPayerException', function () {
    $d = new PagarmeDriver();
    expect(fn () => $d->emitirBoleto(
        new EmitirCobrancaInput(
            businessId: 1, contactId: 1, valorCentavos: 100,
            vencimento: new DateTimeImmutable('+1 day'), descricao: 'x', idempotencyKey: 'k',
            meta: [],
        ),
        $this->cred,
    ))->toThrow(\Modules\PaymentGateway\Exceptions\InvalidPayerException::class);
});
