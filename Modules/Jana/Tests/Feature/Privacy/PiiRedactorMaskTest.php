<?php

declare(strict_types=1);

use Modules\Jana\Services\Privacy\PiiRedactor;

/**
 * Contrato do mascaramento parcial do PiiRedactor (LGPD Art. 7º · minimização).
 *
 * Pure-logic (sem DB) — roda no lane sqlite do CI (.github/ci-sqlite-pest.list),
 * dando evidência executada do núcleo de segurança (maskTail/maskEmail) que os
 * consumidores (ex. ComprasService::buscarDetalhe · Drawer G-06/C17) dependem.
 *
 * `redact()` (redação total → placeholder) já é coberto noutros testes; aqui o
 * foco é o mascaramento PARCIAL adicionado pra exibição a papéis limitados.
 */

it('maskTail expõe apenas os últimos 4 dígitos (CNPJ/CPF/telefone)', function () {
    $r = new PiiRedactor();

    expect($r->maskTail('12.345.678/0001-90'))->toBe('**********0190')   // CNPJ 14 díg · pii-allowlist (fixture)
        ->and($r->maskTail('529.982.247-25'))->toBe('*******4725')       // CPF 11 díg · pii-allowlist (fixture)
        ->and($r->maskTail('(48) 99999-1234'))->toBe('*******1234')      // telefone
        ->and($r->maskTail('99999-1234'))->toBe('*****1234');            // sem DDD
});

it('maskTail aceita tail customizado', function () {
    $r = new PiiRedactor();

    expect($r->maskTail('12345678', 2))->toBe('******78')
        ->and($r->maskTail('12345678', 6))->toBe('**345678');
});

it('maskTail é fail-safe (vazio / sem dígito / curto demais)', function () {
    $r = new PiiRedactor();

    expect($r->maskTail(null))->toBeNull()
        ->and($r->maskTail(''))->toBeNull()
        ->and($r->maskTail('   '))->toBeNull()      // sem dígito → null
        ->and($r->maskTail('abc'))->toBeNull()      // sem dígito → null
        ->and($r->maskTail('12'))->toBe('**')        // <= tail → tudo mascarado
        ->and($r->maskTail('1234'))->toBe('****');   // == tail → tudo mascarado
});

it('maskEmail preserva 1ª letra do local-part + domínio inteiro', function () {
    $r = new PiiRedactor();

    expect($r->maskEmail('fornecedor@acme.com.br'))->toBe('f*********@acme.com.br')
        ->and($r->maskEmail('ab@x.io'))->toBe('a*@x.io')
        ->and($r->maskEmail('a@b.com'))->toBe('a*@b.com');  // local 1 char → 1 estrela
});

it('maskEmail é fail-safe (vazio / sem @ / @ no início)', function () {
    $r = new PiiRedactor();

    expect($r->maskEmail(null))->toBeNull()
        ->and($r->maskEmail(''))->toBeNull()
        ->and($r->maskEmail('sem-arroba'))->toBe('**********')   // 10 chars, tudo mascarado
        ->and($r->maskEmail('@dominio.com'))->toBe('************'); // @ no início → tudo mascarado
});

it('mascaramento parcial não vaza o valor bruto', function () {
    $r = new PiiRedactor();

    $rawTax = '12.345.678/0001-90'; // pii-allowlist (fixture Pest)
    $rawEmail = 'joao@empresa.com.br';

    expect($r->maskTail($rawTax))->not->toContain('345')  // miolo some
        ->and($r->maskEmail($rawEmail))->not->toContain('oao'); // resto do local some
});
