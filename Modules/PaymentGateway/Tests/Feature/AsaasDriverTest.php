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
use Modules\PaymentGateway\Services\Drivers\AsaasDriver;
use Modules\PaymentGateway\Services\PaymentGatewayService;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\DatabaseTransactions::class);

beforeEach(function () {
    session(['business.id' => 1]);
    $this->cred = PaymentGatewayCredential::query()->create([
        'business_id'  => 1,
        'gateway_key'  => 'asaas',
        'ambiente'     => 'sandbox',
        'ativo'        => true,
        'nome_display' => 'Asaas Test',
        'config_json'  => ['api_key' => '$aact_fake_token'],
    ]);
});

it('AsaasDriver key=asaas e supports boleto/pix_cob/card', function () {
    $d = new AsaasDriver();
    expect($d->key())->toBe('asaas');
    expect($d->supports('boleto'))->toBeTrue();
    expect($d->supports('pix_cob'))->toBeTrue();
    expect($d->supports('card'))->toBeTrue();
    expect($d->supports('pix_cobv'))->toBeFalse();
    expect($d->supports('pix_recv'))->toBeFalse();
});

it('emitirBoleto Asaas pipeline OK', function () {
    Http::fake([
        '*/customers*' => Http::sequence()
            ->push(['data' => []], 200) // GET search retorna vazio
            ->push(['id' => 'cus_001', 'name' => 'João'], 200), // POST cria
        '*/payments' => Http::response(['id' => 'pay_001', 'bankSlipUrl' => 'https://asaas/boleto.pdf'], 200),
        '*/payments/pay_001/identificationField' => Http::response([
            'identificationField' => '12345.67890 12345.678901 12345.678901 1 12345678901234',
            'barCode'             => '12345678901234123456789012345678901234567890',
        ], 200),
    ]);

    $service = new PaymentGatewayService();
    $account = (object) ['id' => 1, 'payment_gateway_credential_id' => $this->cred->id];

    $result = $service->for($account)->emitirBoleto(new EmitirCobrancaInput(
        businessId: 1,
        contactId: 200,
        valorCentavos: 15000,
        vencimento: new DateTimeImmutable('+5 days'),
        descricao: 'Asaas teste',
        idempotencyKey: 'sale:asaas-001',
        meta: ['payer_cpf_cnpj' => '12345678900', 'payer_name' => 'João'],
    ));

    expect($result->tipo)->toBe('boleto');
    expect($result->gatewayExternalId)->toBe('pay_001');
    expect($result->linhaDigitavel)->toContain('12345');
    expect($result->boletoPdfUrl)->toContain('boleto.pdf');
});

it('emitirPix Asaas tipo=cob OK', function () {
    Http::fake([
        '*/customers*' => Http::sequence()
            ->push(['data' => []], 200)
            ->push(['id' => 'cus_pix_01'], 200),
        '*/payments' => Http::response(['id' => 'pay_pix_001'], 200),
        '*/payments/pay_pix_001/pixQrCode' => Http::response([
            'payload' => '00020126...EMV-fake...6304ABCD',
        ], 200),
    ]);

    $service = new PaymentGatewayService();
    $account = (object) ['id' => 1, 'payment_gateway_credential_id' => $this->cred->id];

    $result = $service->for($account)->emitirPix(new EmitirCobrancaInput(
        businessId: 1, contactId: 200, valorCentavos: 5000,
        vencimento: new DateTimeImmutable('+1 day'),
        descricao: 'PIX', idempotencyKey: 'pix:001',
        meta: ['payer_cpf_cnpj' => '12345678900', 'payer_name' => 'João'],
    ), 'cob');

    expect($result->tipo)->toBe('pix_cob');
    expect($result->pixEmv)->toContain('00020126');
});

it('emitirPix tipo=cobv → DriverNotSupportedException no Asaas', function () {
    $d = new AsaasDriver();
    expect(fn () => $d->emitirPix(
        new EmitirCobrancaInput(businessId: 1, contactId: 1, valorCentavos: 100, vencimento: new DateTimeImmutable(), descricao: 'x', idempotencyKey: 'k'),
        $this->cred,
        'cobv',
    ))->toThrow(DriverNotSupportedException::class);
});

it('cobrarCartao 400 → CardDeclinedException', function () {
    Http::fake([
        '*/customers*' => Http::sequence()
            ->push(['data' => []], 200)
            ->push(['id' => 'cus_card_01'], 200),
        '*/payments' => Http::response(['errors' => [['description' => 'Cartão recusado']]], 400),
    ]);

    $d = new AsaasDriver();
    $token = new CardToken(token: 'tok_x', brand: 'visa', lastFour: '4242', holderName: 'João', expMonth: '12', expYear: '2030');

    expect(fn () => $d->cobrarCartao(
        new EmitirCobrancaInput(businessId: 1, contactId: 200, valorCentavos: 9999, vencimento: new DateTimeImmutable('+1 day'), descricao: 'card', idempotencyKey: 'card:001', meta: ['payer_cpf_cnpj' => '12345678900', 'payer_name' => 'X']),
        $this->cred,
        $token,
    ))->toThrow(CardDeclinedException::class);
});

it('refund Asaas chama POST /payments/{id}/refund com valor', function () {
    Http::fake([
        '*/payments/pay_to_refund/refund' => Http::response(['status' => 'REFUNDED'], 200),
    ]);

    $cobranca = (object) ['gateway_external_id' => 'pay_to_refund'];
    $d = new AsaasDriver();
    $d->refund($cobranca, $this->cred, 5000, 'Cliente desistiu');

    Http::assertSent(fn ($r) => str_contains($r->url(), 'pay_to_refund/refund')
        && $r['value'] === 50.0
        && $r['description'] === 'Cliente desistiu');
});

it('refund sem valor (total) NÃO envia campo value', function () {
    Http::fake(['*/payments/*/refund' => Http::response([], 200)]);

    $cobranca = (object) ['gateway_external_id' => 'pay_full_refund'];
    $d = new AsaasDriver();
    $d->refund($cobranca, $this->cred, null, 'Total');

    Http::assertSent(fn ($r) => str_contains($r->url(), 'pay_full_refund/refund')
        && ! isset($r['value']));
});

it('cancelar Asaas chama DELETE /payments/{id}', function () {
    Http::fake(['*/payments/pay_cancel' => Http::response(['deleted' => true], 200)]);

    $cobranca = (object) ['gateway_external_id' => 'pay_cancel'];
    (new AsaasDriver())->cancelar($cobranca, $this->cred, 'Acertos');

    Http::assertSent(fn ($r) => $r->method() === 'DELETE' && str_contains($r->url(), 'pay_cancel'));
});

it('consultar mapeia status canon', function () {
    Http::fake([
        '*/payments/pay_paga' => Http::response([
            'status'      => 'RECEIVED',
            'paymentDate' => '2026-05-19',
            'netValue'    => 95.50,
            'billingType' => 'BOLETO',
        ], 200),
    ]);

    $cobranca = (object) ['gateway_external_id' => 'pay_paga'];
    $status = (new AsaasDriver())->consultar($cobranca, $this->cred);

    expect($status->status)->toBe('paga');
    expect($status->valorPagoCentavos)->toBe(9550);
    expect($status->formaPagamento)->toBe('boleto');
});

it('healthCheck OK quando GET /finance/balance retorna 200', function () {
    Http::fake(['*/finance/balance' => Http::response(['balance' => 1000.00], 200)]);

    $h = (new AsaasDriver())->healthCheck($this->cred);
    expect($h->ok)->toBeTrue();
    expect($h->status)->toBeIn(['ok', 'degraded']);
});

it('healthCheck down em 401', function () {
    Http::fake(['*/finance/balance' => Http::response(['error' => 'unauthorized'], 401)]);

    $h = (new AsaasDriver())->healthCheck($this->cred);
    expect($h->ok)->toBeFalse();
    expect($h->status)->toBe('down');
});

it('processWebhook lê payment.id ou id top-level', function () {
    $d = new AsaasDriver();

    $r1 = $d->processWebhook(['event' => 'PAYMENT_RECEIVED', 'payment' => ['id' => 'pay_wh_001']], $this->cred);
    expect($r1->gateway_external_id)->toBe('pay_wh_001');

    $r2 = $d->processWebhook(['event' => 'X', 'id' => 'evt_002'], $this->cred);
    expect($r2->gateway_external_id)->toBe('evt_002');

    $r3 = $d->processWebhook(['event' => 'no-id'], $this->cred);
    expect($r3)->toBeNull();
});

it('credential gateway_key errado → CredentialMisconfigured', function () {
    $bad = PaymentGatewayCredential::query()->create([
        'business_id' => 1, 'gateway_key' => 'inter',
        'ambiente' => 'sandbox', 'ativo' => true, 'config_json' => ['client_id' => 'x'],
    ]);
    expect(fn () => (new AsaasDriver())->healthCheck($bad))
        ->toThrow(CredentialMisconfiguredException::class);
});
