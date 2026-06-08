<?php

declare(strict_types=1);

use Modules\Jana\Services\Privacy\PiiRedactor;

/**
 * D7.a — PiiRedactor integrado em RevertService.
 *
 * Garante que justificativa de revert (texto livre digitado por admin)
 * passa por PiiRedactor antes de persistir em activity_log.
 *
 * NAO chama RevertService::revert() de verdade (requer DB + Activity completa
 * + subject Model + permissoes Spatie — escopo MultiTenantIsolationTest).
 * Testa apenas o CONTRATO: redactor recebe e devolve texto sanitizado.
 *
 * @see Modules\Auditoria\Services\RevertService::revert()
 */

uses(Tests\TestCase::class);

it('redacta CPF em string de razao livre antes de persistir', function () {
    $redactor = new PiiRedactor();
    $reasonComPii = 'Cliente Larissa CPF 123.456.789-00 pediu reverter por email larissa@rotalivre.com.br';

    $redacted = $redactor->redact($reasonComPii, 'placeholder');

    expect($redacted)->not->toContain('123.456.789-00');
    expect($redacted)->not->toContain('larissa@rotalivre.com.br');
    expect($redacted)->toContain('[REDACTED:CPF]');
    expect($redacted)->toContain('[REDACTED:EMAIL]');
});

it('redacta CNPJ + telefone BR mantendo contexto legivel', function () {
    $redactor = new PiiRedactor();
    $reason = 'CNPJ 12.345.678/0001-90 ligou no telefone (48) 99999-1234 pedindo cancelamento';

    $redacted = $redactor->redact($reason, 'placeholder');

    expect($redacted)->not->toContain('12.345.678/0001-90');
    expect($redacted)->toContain('[REDACTED:CNPJ]');
    expect($redacted)->toContain('[REDACTED:PHONE]');
    expect($redacted)->toContain('ligou no telefone'); // contexto preservado
    expect($redacted)->toContain('pedindo cancelamento');
});

it('texto sem PII passa intocado', function () {
    $redactor = new PiiRedactor();
    $reason = 'Erro de digitacao no campo quantidade — admin reverteu';

    $redacted = $redactor->redact($reason, 'placeholder');

    expect($redacted)->toBe($reason);
});

it('container resolve PiiRedactor (verifica binding default)', function () {
    $redactor = app(PiiRedactor::class);

    expect($redactor)->toBeInstanceOf(PiiRedactor::class);
    expect($redactor->redact('teste@email.com', 'placeholder'))
        ->toBe('[REDACTED:EMAIL]');
});
