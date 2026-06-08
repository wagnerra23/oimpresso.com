<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Modules\RecurringBilling\Dto\BoletoResult;
use Modules\RecurringBilling\Services\Boleto\Drivers\AsaasDriver;

uses(Tests\TestCase::class);

/**
 * US-RB-040 · Cobertura Pest do AsaasDriver.
 *
 * AsaasDriver usa Http facade (laravel/http) → fácil mockar via Http::fake().
 * Tests cobrem: emitir, cancelar, pdf, e o subroutine resolveCustomer.
 */

beforeEach(function () {
    $this->config = [
        'api_key'  => '$aact_test_token',
        'ambiente' => 'sandbox',
    ];
    $this->driver = new AsaasDriver($this->config);
});

it('usa baseUrl sandbox e emite boleto com customer novo', function () {
    Http::fake(function (\Illuminate\Http\Client\Request $request) {
        $url = $request->url();
        $method = $request->method();
        return match (true) {
            str_contains($url, '/customers') && $method === 'GET'
                => Http::response(['data' => []], 200), // não acha
            str_contains($url, '/customers') && $method === 'POST'
                => Http::response(['id' => 'cus_123', 'name' => 'João Silva'], 200),
            str_ends_with($url, '/payments') && $method === 'POST'
                => Http::response([
                    'id'          => 'pay_abc',
                    'bankSlipUrl' => 'https://sandbox.asaas.com/b/pdf/abc',
                ], 200),
            str_ends_with($url, '/payments/pay_abc/identificationField')
                => Http::response([
                    'identificationField' => '23793.39001 60000.000005 90000.000004 1 99800001234567',
                    'barCode'             => '23791998000012345670003900060000909000000400',
                ], 200),
            str_ends_with($url, '/payments/pay_abc/pixQrCode')
                => Http::response(['payload' => '00020126580014BR.GOV.BCB.PIX...'], 200),
            default => Http::response([], 404),
        };
    });

    $result = $this->driver->emitir([
        'valor'             => 150.00,
        'data_vencimento'   => '2026-06-15',
        'descricao'         => 'Mensalidade Maio',
        'numero_documento'  => 'INV-001',
        'pagador_nome'      => 'João Silva',
        'pagador_cpf_cnpj'  => '111.222.333-44',
        'pagador_email'     => 'joao@example.com',
    ]);

    expect($result)->toBeInstanceOf(BoletoResult::class)
        ->and($result->nossoNumero)->toBe('pay_abc')
        ->and($result->linhaDigitavel)->toBe('23793.39001 60000.000005 90000.000004 1 99800001234567')
        ->and($result->codigoBarras)->toBe('23791998000012345670003900060000909000000400')
        ->and($result->valor)->toBe(150.00)
        ->and($result->pdfUrl)->toBe('https://sandbox.asaas.com/b/pdf/abc')
        ->and($result->pixQrCode)->toContain('BR.GOV.BCB.PIX');
});

it('usa baseUrl production quando ambiente=production', function () {
    $driver = new AsaasDriver(['api_key' => 'test', 'ambiente' => 'production']);

    Http::fake(['api.asaas.com/*' => Http::response(['id' => 'pay_xyz'], 200)]);

    $reflect = (new ReflectionClass($driver))->getProperty('baseUrl');
    $reflect->setAccessible(true);

    expect($reflect->getValue($driver))->toBe('https://api.asaas.com/v3');
});

it('reutiliza cliente existente em vez de criar novo', function () {
    Http::fake([
        'sandbox.asaas.com/api/v3/customers*' => Http::response([
            'data' => [['id' => 'cus_existing']],
        ], 200),
        'sandbox.asaas.com/api/v3/payments' => Http::response([
            'id' => 'pay_reuse', 'bankSlipUrl' => 'https://x',
        ], 200),
        'sandbox.asaas.com/api/v3/payments/pay_reuse/identificationField' => Http::response([
            'identificationField' => 'X', 'barCode' => 'Y',
        ], 200),
        'sandbox.asaas.com/api/v3/payments/pay_reuse/pixQrCode' => Http::response([
            'payload' => null,
        ], 200),
    ]);

    $this->driver->emitir([
        'valor' => 50, 'data_vencimento' => '2026-07-01',
        'numero_documento' => 'INV-002',
        'pagador_nome' => 'Maria', 'pagador_cpf_cnpj' => '999.888.777-66',
    ]);

    Http::assertSent(function ($request) {
        return $request->method() === 'POST' && str_contains($request->url(), '/payments')
            && ($request['customer'] ?? null) === 'cus_existing';
    });

    Http::assertNotSent(function ($request) {
        return $request->method() === 'POST' && str_ends_with($request->url(), '/customers');
    });
});

it('cancela boleto via DELETE /payments/{id}', function () {
    Http::fake([
        'sandbox.asaas.com/api/v3/payments/pay_to_cancel' => Http::response([
            'id' => 'pay_to_cancel', 'status' => 'DELETED',
        ], 200),
    ]);

    $result = $this->driver->cancelar('pay_to_cancel', 'ACERTOS');

    expect($result)->toBeTrue();

    Http::assertSent(function ($request) {
        return $request->method() === 'DELETE'
            && str_ends_with($request->url(), '/payments/pay_to_cancel');
    });
});

it('inclui access_token header em todas as chamadas', function () {
    Http::fake(['sandbox.asaas.com/*' => Http::response(['data' => []], 200)]);

    $this->driver->cancelar('any_id');

    Http::assertSent(function ($request) {
        return $request->hasHeader('access_token', '$aact_test_token');
    });
});

it('retorna URL do bankSlip via método pdf()', function () {
    Http::fake([
        'sandbox.asaas.com/api/v3/payments/pay_pdf' => Http::response([
            'id' => 'pay_pdf',
            'bankSlipUrl' => 'https://sandbox.asaas.com/b/pdf/long-token',
        ], 200),
    ]);

    $url = $this->driver->pdf('pay_pdf');

    expect($url)->toBe('https://sandbox.asaas.com/b/pdf/long-token');
});
