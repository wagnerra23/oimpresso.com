<?php

declare(strict_types=1);

use Modules\Jana\Services\Privacy\PiiRedactor;

uses(Tests\TestCase::class);

/**
 * COPI-43 — PII redactor BR (LGPD-blocker).
 *
 * Cobre 5 tipos canônicos: CPF, CNPJ, EMAIL, CEP, PHONE BR.
 * Cobre 3 modos: placeholder (default), hash (cross-reference), remove.
 *
 * Test puro — não toca DB nem rede.
 */

beforeEach(function () {
    $this->redactor = new PiiRedactor();
});

// -------------------------------------------------------------------------
// CPF
// -------------------------------------------------------------------------

test('redaciona CPF formatado', function () {
    $input = 'Cliente Larissa CPF 123.456.789-09';
    $output = $this->redactor->redact($input);
    expect($output)->toBe('Cliente Larissa CPF [REDACTED:CPF]');
});

test('redaciona CPF sem máscara', function () {
    $input = 'CPF 12345678909 do cliente';
    $output = $this->redactor->redact($input);
    expect($output)->toBe('CPF [REDACTED:CPF] do cliente');
});

test('redaciona múltiplos CPFs', function () {
    $input = '111.222.333-44 e 555.666.777-88';
    $output = $this->redactor->redact($input);
    expect($output)->toBe('[REDACTED:CPF] e [REDACTED:CPF]');
});

// -------------------------------------------------------------------------
// CNPJ
// -------------------------------------------------------------------------

test('redaciona CNPJ formatado', function () {
    $input = 'CNPJ 12.345.678/0001-90 da empresa';
    $output = $this->redactor->redact($input);
    expect($output)->toBe('CNPJ [REDACTED:CNPJ] da empresa');
});

test('redaciona CNPJ sem máscara', function () {
    $input = '12345678000190 inscrição';
    $output = $this->redactor->redact($input);
    expect($output)->toBe('[REDACTED:CNPJ] inscrição');
});

// -------------------------------------------------------------------------
// EMAIL
// -------------------------------------------------------------------------

test('redaciona email simples', function () {
    $input = 'contato larissa@rotalivre.com.br';
    $output = $this->redactor->redact($input);
    expect($output)->toBe('contato [REDACTED:EMAIL]');
});

test('redaciona email com símbolos', function () {
    $input = 'enviar pra wagner.ra+tag@gmail.com hoje';
    $output = $this->redactor->redact($input);
    expect($output)->toBe('enviar pra [REDACTED:EMAIL] hoje');
});

// -------------------------------------------------------------------------
// CEP
// -------------------------------------------------------------------------

test('redaciona CEP formatado', function () {
    $input = 'Endereço CEP 01310-100 SP';
    $output = $this->redactor->redact($input);
    expect($output)->toBe('Endereço CEP [REDACTED:CEP] SP');
});

test('redaciona CEP sem hífen', function () {
    $input = 'CEP 01310100';
    $output = $this->redactor->redact($input);
    expect($output)->toBe('CEP [REDACTED:CEP]');
});

// -------------------------------------------------------------------------
// PHONE BR
// -------------------------------------------------------------------------

test('redaciona telefone com DDD e 9 dígitos', function () {
    $input = 'Tel (11) 98765-4321 do cliente';
    $output = $this->redactor->redact($input);
    expect($output)->toBe('Tel [REDACTED:PHONE] do cliente');
});

test('redaciona telefone com +55', function () {
    $input = 'WhatsApp +55 11 98765-4321 hoje';
    $output = $this->redactor->redact($input);
    expect($output)->toBe('WhatsApp [REDACTED:PHONE] hoje');
});

// -------------------------------------------------------------------------
// Combinado real-world
// -------------------------------------------------------------------------

test('redaciona mensagem real do Copiloto chat', function () {
    $input = 'Cliente Larissa (CPF 123.456.789-09, email larissa@rotalivre.com.br, '
           . 'tel (11) 98765-4321) pediu boleto pro CEP 01310-100 da empresa CNPJ 12.345.678/0001-90.';
    $output = $this->redactor->redact($input);

    expect($output)
        ->toContain('[REDACTED:CPF]')
        ->toContain('[REDACTED:EMAIL]')
        ->toContain('[REDACTED:PHONE]')
        ->toContain('[REDACTED:CEP]')
        ->toContain('[REDACTED:CNPJ]')
        ->not->toContain('123.456.789-09')
        ->not->toContain('larissa@rotalivre.com.br')
        ->not->toContain('12.345.678/0001-90');
});

// -------------------------------------------------------------------------
// Modos
// -------------------------------------------------------------------------

test('modo hash gera identificador determinístico', function () {
    $input1 = 'CPF 123.456.789-09 hoje';
    $input2 = 'CPF 123.456.789-09 amanhã';

    $r1 = $this->redactor->redact($input1, 'hash');
    $r2 = $this->redactor->redact($input2, 'hash');

    // Mesmo CPF deve gerar mesmo hash → cross-reference sem expor PII
    expect($r1)->toContain('[REDACTED:CPF:');
    expect($r2)->toContain('[REDACTED:CPF:');

    preg_match('/\[REDACTED:CPF:([a-f0-9]+)\]/', $r1, $m1);
    preg_match('/\[REDACTED:CPF:([a-f0-9]+)\]/', $r2, $m2);

    expect($m1[1])->toBe($m2[1]);
});

test('modo remove apaga sem placeholder', function () {
    $input = 'Email contato@x.com.br pra teste';
    $output = $this->redactor->redact($input, 'remove');
    expect($output)
        ->not->toContain('contato@x.com.br')
        ->not->toContain('[REDACTED');
});

// -------------------------------------------------------------------------
// Detect
// -------------------------------------------------------------------------

test('detecta PII sem redactar', function () {
    $input = 'CPF 123.456.789-09 + email a@b.com.br';
    $detected = $this->redactor->detect($input);

    expect($detected)
        ->toHaveKey('CPF')
        ->toHaveKey('EMAIL');
    expect($detected['CPF'])->toBe(1);
    expect($detected['EMAIL'])->toBe(1);
});

// -------------------------------------------------------------------------
// redactArray
// -------------------------------------------------------------------------

test('redactArray funciona recursivamente', function () {
    $input = [
        'message' => 'CPF 123.456.789-09',
        'meta' => [
            'phone' => '(11) 98765-4321',
            'count' => 42,
        ],
    ];

    $output = $this->redactor->redactArray($input);

    expect($output['message'])->toBe('CPF [REDACTED:CPF]');
    expect($output['meta']['phone'])->toBe('[REDACTED:PHONE]');
    expect($output['meta']['count'])->toBe(42); // não-string preservado
});

// -------------------------------------------------------------------------
// Edge cases
// -------------------------------------------------------------------------

test('string vazia retorna vazia', function () {
    expect($this->redactor->redact(''))->toBe('');
});

test('string sem PII retorna idêntica', function () {
    $input = 'Faturamento de abril foi 30k bruto';
    expect($this->redactor->redact($input))->toBe($input);
});

test('detect em string vazia retorna array vazio', function () {
    expect($this->redactor->detect(''))->toBe([]);
});

// ── CPF cru × número de 11 dígitos (2026-08-02) ──────────────────────────────
//
// Medido em PRODUÇÃO ao indexar o trio de tela: 32 "PII" redigidos em 8 casos.md
// eram RUN ID do GitHub Actions. `\d{11}` casa qualquer número de 11 dígitos, e o
// efeito era apagar do índice o recibo de CI que o projeto exige como prova.
// O CPF tem dígito verificador; run id não.

it('não redige run id do GitHub Actions (11 dígitos, DV inválido)', function () {
    $r = new Modules\Jana\Services\Privacy\PiiRedactor();
    $texto = 'lane Estoque · MySQL, run 30366164436 (PR #4953), lido 2026-07-29';

    expect($r->redact($texto))->toBe($texto)
        ->and($r->detect($texto))->not->toHaveKey('CPF');
});

it('CONTINUA redigindo CPF cru com DV válido', function () {
    $r = new Modules\Jana\Services\Privacy\PiiRedactor();

    // 11144477735 é CPF válido (DV confere) — o afrouxamento não pode deixá-lo passar.
    expect($r->redact('titular 11144477735 no cadastro'))->not->toContain('11144477735');
});

it('CONTINUA redigindo CPF pontuado mesmo com DV inválido', function () {
    $r = new Modules\Jana\Services\Privacy\PiiRedactor();

    // Formato explícito é declaração de CPF — digitado errado ainda é tentativa de PII.
    expect($r->redact('CPF 123.456.789-01'))->not->toContain('123.456.789-01');
});

it('detect e redact concordam — has_pii não marca doc que ficou intacto', function () {
    $r = new Modules\Jana\Services\Privacy\PiiRedactor();
    $texto = 'run 30122611472 e run 30366164436';

    expect($r->detect($texto))->not->toHaveKey('CPF')
        ->and($r->redact($texto))->toBe($texto);
});

it('não redige número longo com DDD inexistente, mas redige celular real', function () {
    $r = new Modules\Jana\Services\Privacy\PiiRedactor();

    // O regex de telefone casava os 10 primeiros dígitos do run id (`3036616443`)
    // e redigia mesmo depois de o CPF liberar. DDD "30" não existe no Brasil.
    expect($r->redact('run 30366164436'))->toBe('run 30366164436');

    // Controle: DDD 11 existe — celular cru continua sendo PII.
    expect($r->redact('tel 11987654321'))->not->toContain('11987654321');
});

it('formato explícito dispensa validação — telefone e CPF pontuados sempre redigidos', function () {
    $r = new Modules\Jana\Services\Privacy\PiiRedactor();

    expect($r->redact('fone (11) 98765-4321'))->not->toContain('98765-4321')
        ->and($r->redact('CPF 123.456.789-01'))->not->toContain('123.456.789-01');
});
