<?php

declare(strict_types=1);

use Modules\RecurringBilling\Dto\BoletoResult;
use Modules\RecurringBilling\Services\Boleto\Drivers\C6Driver;

uses(Tests\TestCase::class);

/**
 * US-RB-040 · Cobertura Pest do C6Driver.
 *
 * C6 não tem API REST — geração 100% local via lib eduardokum.
 * Testes não dependem de HTTP/SEFAZ/banco — só validam que a lib gera
 * boleto com código de barras / linha digitável / nossoNumero corretos
 * e que os contratos do BoletoDriverContract são respeitados.
 */

beforeEach(function () {
    $this->config = [
        'agencia'           => '0001',
        'conta_corrente'    => '12345678',
        'codigo_cliente'    => '999',
        'carteira'          => '10',
        'cnpj_beneficiario' => '12.345.678/0001-99',
        'nome_beneficiario' => 'Empresa Teste LTDA',
        'cep'               => '01310-100',
        'logradouro'        => 'Av. Paulista',
        'numero'            => '1000',
        'bairro'            => 'Bela Vista',
        'cidade'            => 'São Paulo',
        'uf'                => 'SP',
    ];
    $this->driver = new C6Driver($this->config);

    $this->params = [
        'valor'             => 199.90,
        'data_vencimento'   => '2026-06-30',
        'numero_documento'  => 'INV-100',
        'pagador_nome'      => 'Cliente Final',
        'pagador_cpf_cnpj'  => '111.222.333-44',
        'pagador_cep'       => '04567-000',
        'pagador_endereco'  => 'Rua Cliente',
        'pagador_numero'    => '50',
        'pagador_bairro'    => 'Vila Mariana',
        'pagador_cidade'    => 'São Paulo',
        'pagador_uf'        => 'SP',
    ];
});

it('emite boleto local com código de barras + linha digitável válidos', function () {
    $result = $this->driver->emitir($this->params);

    expect($result)->toBeInstanceOf(BoletoResult::class)
        ->and($result->valor)->toBe(199.90)
        ->and($result->dataVencimento)->toBe('2026-06-30');

    // Linha digitável formato padrão FEBRABAN: 47 dígitos (com 4 espaços)
    $linhaSemEspacos = preg_replace('/\D/', '', $result->linhaDigitavel);
    expect(strlen($linhaSemEspacos))->toBe(47);

    // Código de barras: 44 dígitos
    expect(strlen($result->codigoBarras))->toBe(44);

    // PDF base64 não vazio
    expect($result->pdfBase64)->not()->toBeEmpty();
    expect(base64_decode($result->pdfBase64, true))->not()->toBeFalse();

    // C6 (cód 336) começa com '336' no código de barras
    expect(substr($result->codigoBarras, 0, 3))->toBe('336');
});

it('preserva nossoNumero como referência', function () {
    $result = $this->driver->emitir($this->params);

    expect($result->nossoNumero)->not()->toBeEmpty();
    expect($result->nossoNumero)->toBeString();
});

it('cancelar() lança BadMethodCallException com instrução clara (US-RB-042)', function () {
    expect(fn () => $this->driver->cancelar('any-nosso-numero'))
        ->toThrow(\BadMethodCallException::class, 'C6Driver::cancelar() não suportado via API');

    expect(fn () => $this->driver->cancelar('123456', 'ERROR'))
        ->toThrow(\BadMethodCallException::class, 'manualmente no portal C6');
});

it('pdf() lança exceção pedindo re-emissão com dados originais', function () {
    expect(fn () => $this->driver->pdf('123'))
        ->toThrow(RuntimeException::class, 're-geração de PDF requer re-emissão');
});

it('aceita pagador com campos opcionais ausentes (defaults aplicados)', function () {
    $minimal = [
        'valor'             => 50.00,
        'data_vencimento'   => '2026-07-01',
        'numero_documento'  => 'INV-200',
        'pagador_nome'      => 'Cliente Mínimo',
        'pagador_cpf_cnpj'  => '999.888.777-66',
    ];

    $result = $this->driver->emitir($minimal);

    expect($result)->toBeInstanceOf(BoletoResult::class)
        ->and($result->valor)->toBe(50.00);
});
