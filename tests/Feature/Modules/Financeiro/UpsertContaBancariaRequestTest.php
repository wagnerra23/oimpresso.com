<?php

use Illuminate\Support\Facades\Validator;
use Modules\Financeiro\Http\Requests\UpsertContaBancariaRequest;

/**
 * Test offline-safe das regras de validacao do form de configurar boleto.
 * Cobre: campos obrigatorios + banco_codigo na lista suportada.
 */

it('aceita payload completo com banco suportado', function () {
    $rules = (new UpsertContaBancariaRequest)->rules();

    $validator = Validator::make([
        'banco_codigo' => '756',
        'agencia' => '1234',
        'carteira' => '1',
        'beneficiario_documento' => '12.345.678/0001-99',
        'beneficiario_razao_social' => 'TESTE LTDA',
    ], $rules);

    expect($validator->fails())->toBeFalse();
});

it('rejeita banco_codigo nao suportado', function () {
    $rules = (new UpsertContaBancariaRequest)->rules();

    $validator = Validator::make([
        'banco_codigo' => '999',
        'agencia' => '1234',
        'carteira' => '1',
        'beneficiario_documento' => '12.345.678/0001-99',
        'beneficiario_razao_social' => 'X',
    ], $rules);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('banco_codigo'))->toBeTrue();
});

it('exige banco/agencia/carteira/beneficiario obrigatorios', function () {
    $rules = (new UpsertContaBancariaRequest)->rules();
    $validator = Validator::make([], $rules);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->keys())->toContain(
        'banco_codigo', 'agencia', 'carteira',
        'beneficiario_documento', 'beneficiario_razao_social'
    );
});
