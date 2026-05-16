<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use Modules\Financeiro\Services\FinanceiroAuditLogger;
use Modules\Jana\Services\Privacy\PiiRedactor;

/**
 * Wave 14 D7.a — sanity tests pro wrapper que redaciona PII antes de Log::*.
 *
 * **Sem PII real:** todos os valores são CPF/CNPJ/email/telefone SINTÉTICOS,
 * gerados de forma a só validar a regex do PiiRedactor. NUNCA usar CPF real
 * de Larissa (cliente piloto ROTA LIVRE biz=4) ou qualquer cliente.
 *
 * **Sem DB:** unit test puro — sem migration, sem RefreshDatabase, sem
 * `FinanceiroTestCase`. Mocka Log facade + injeta PiiRedactor real.
 *
 * Rodar local: `vendor/bin/pest Modules/Financeiro/Tests/Unit/FinanceiroAuditLoggerTest.php`
 */

beforeEach(function () {
    Log::spy();
    $this->logger = new FinanceiroAuditLogger(new PiiRedactor());
});

test('redaciona CPF sintético em observacoes mantendo business_id intacto', function () {
    $this->logger->info('titulo_baixa.test', [
        'business_id' => 99,
        'titulo_id' => 1234,
        'observacoes' => 'Cliente sintético CPF 000.000.000-00 ligou pra negociar',
    ]);

    Log::shouldHaveReceived('info')
        ->withArgs(function (string $msg, array $ctx) {
            return $msg === 'titulo_baixa.test'
                && ($ctx['business_id'] ?? null) === 99
                && ($ctx['titulo_id'] ?? null) === 1234
                && str_contains($ctx['observacoes'] ?? '', '[REDACTED:CPF]')
                && ! str_contains($ctx['observacoes'] ?? '', '000.000.000-00');
        })
        ->once();

    // marca asserção explícita pra Pest não considerar risky.
    expect(true)->toBeTrue();
});

test('redaciona CNPJ sintético + email sintético + telefone sintético em payload aninhado', function () {
    $this->logger->warning('cobranca.fail', [
        'business_id' => 99,
        'note' => 'Falha CNPJ 00.000.000/0000-00',
        'metadata' => [
            'email' => 'teste@example.com',
            'telefone' => '(11) 99999-9999',
            'tipo' => 'boleto',
        ],
    ]);

    Log::shouldHaveReceived('warning')
        ->withArgs(function (string $msg, array $ctx) {
            return $msg === 'cobranca.fail'
                && ($ctx['business_id'] ?? null) === 99
                && str_contains($ctx['note'] ?? '', '[REDACTED:CNPJ]')
                && str_contains($ctx['metadata']['email'] ?? '', '[REDACTED:EMAIL]')
                && str_contains($ctx['metadata']['telefone'] ?? '', '[REDACTED:PHONE]')
                && ($ctx['metadata']['tipo'] ?? null) === 'boleto';
        })
        ->once();

    // marca asserção explícita pra Pest não considerar risky.
    expect(true)->toBeTrue();
});

test('preserva chaves operacionais críticas mesmo se valor parecer PII', function () {
    // idempotency_key pode conter substring que regex acharia. Mas a chave
    // está em KEYS_SKIP_REDACTION então o valor passa intacto.
    $this->logger->info('idempotency.hit', [
        'business_id' => 99,
        'idempotency_key' => 'tp_12345',
        'invoice_no' => 'NF-2026-0001',
        'status' => 'aberto',
    ]);

    Log::shouldHaveReceived('info')
        ->withArgs(function (string $msg, array $ctx) {
            return $msg === 'idempotency.hit'
                && ($ctx['idempotency_key'] ?? null) === 'tp_12345'
                && ($ctx['invoice_no'] ?? null) === 'NF-2026-0001'
                && ($ctx['status'] ?? null) === 'aberto';
        })
        ->once();

    // marca asserção explícita pra Pest não considerar risky.
    expect(true)->toBeTrue();
});

test('passa valores não-string (int/float/bool/null) sem redacionar', function () {
    $this->logger->debug('ledger.calc', [
        'business_id' => 99,
        'valor' => 1234.56,
        'parcelas' => 3,
        'ativo' => true,
        'cancelado_em' => null,
    ]);

    Log::shouldHaveReceived('debug')
        ->withArgs(function (string $msg, array $ctx) {
            return $msg === 'ledger.calc'
                && ($ctx['valor'] ?? null) === 1234.56
                && ($ctx['parcelas'] ?? null) === 3
                && ($ctx['ativo'] ?? null) === true
                && array_key_exists('cancelado_em', $ctx)
                && $ctx['cancelado_em'] === null;
        })
        ->once();

    // marca asserção explícita pra Pest não considerar risky.
    expect(true)->toBeTrue();
});

test('config retention financeiro carrega + valida bases legais BR', function () {
    $config = require __DIR__ . '/../../Config/retention.php';

    expect($config)->toBeArray();
    expect($config['enabled'])->toBeFalse(); // ADR 0105: sem job ainda
    expect($config['titulos']['days'])->toBe(1825); // CTN Art. 195
    expect($config['boletos']['days'])->toBe(730);  // Lei 5.474/68 buffer
    expect($config['caixa']['days'])->toBe(1825);
    expect($config['titulos']['legal_basis'])->toContain('CTN Art. 195');
    expect($config['boletos']['legal_basis'])->toContain('Lei 5.474/68');
    expect($config['contas_bancarias']['pii_fields'])->toContain('beneficiario_documento');
});
