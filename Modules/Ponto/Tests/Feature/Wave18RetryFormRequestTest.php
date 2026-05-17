<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use Modules\Ponto\Entities\Escala;
use Modules\Ponto\Http\Requests\StoreEscalaRequest;

uses(Tests\TestCase::class);

/**
 * Wave 18 RETRY D8.c — FormRequest StoreEscalaRequest validation rules.
 *
 * Cobre limites CLT que devem bloquear input inválido:
 *  - Art. 58: jornada ≤8h normal (allow até 12h pra 12x36)
 *  - Art. 7º XIII: ≤44h/semana
 *  - Tipos canônicos (FIXA, FLEXIVEL, 12X36, 6X1, 5X2)
 *
 * SQLite-friendly — só valida rules() (não toca DB).
 *
 * @see Modules/Ponto/Http/Requests/StoreEscalaRequest.php
 */

function ponto18ValidateEscala(array $data): \Illuminate\Validation\Validator
{
    $request = new StoreEscalaRequest;
    return Validator::make($data, $request->rules(), $request->messages());
}

it('aceita escala FIXA 8h/44h padrão CLT', function () {
    $v = ponto18ValidateEscala([
        'nome'                  => 'Escala Padrão Comercial',
        'tipo'                  => Escala::TIPO_FIXA,
        'carga_diaria_minutos'  => 480,
        'carga_semanal_minutos' => 2640,
    ]);

    expect($v->fails())->toBeFalse('Escala 8h/44h é o padrão CLT — deve passar');
});

it('aceita escala 12x36 com 12h/36h semana', function () {
    $v = ponto18ValidateEscala([
        'nome'                  => 'Escala 12x36 Vigilância',
        'tipo'                  => Escala::TIPO_ESCALA_12X36,
        'carga_diaria_minutos'  => 720,  // 12h
        'carga_semanal_minutos' => 2160, // 36h
    ]);

    expect($v->fails())->toBeFalse('12x36 escala válida');
});

it('rejeita jornada >12h (CLT Art. 59 limite legal)', function () {
    $v = ponto18ValidateEscala([
        'nome'                  => 'Escala Ilegal',
        'tipo'                  => Escala::TIPO_FIXA,
        'carga_diaria_minutos'  => 780,  // 13h — viola Art. 59
        'carga_semanal_minutos' => 2640,
    ]);

    expect($v->fails())->toBeTrue('Jornada 13h deve ser bloqueada');
    expect($v->errors()->first('carga_diaria_minutos'))->toContain('12h');
});

it('rejeita carga semanal >44h (CLT Art. 7º XIII)', function () {
    $v = ponto18ValidateEscala([
        'nome'                  => 'Escala Acima',
        'tipo'                  => Escala::TIPO_FIXA,
        'carga_diaria_minutos'  => 480,
        'carga_semanal_minutos' => 2700, // 45h
    ]);

    expect($v->fails())->toBeTrue('45h/semana deve ser bloqueada');
});

it('rejeita tipo de escala fora do enum canônico', function () {
    $v = ponto18ValidateEscala([
        'nome'                  => 'Escala Tipo Inventado',
        'tipo'                  => 'INVENTADO',
        'carga_diaria_minutos'  => 480,
        'carga_semanal_minutos' => 2640,
    ]);

    expect($v->fails())->toBeTrue();
    expect($v->errors()->first('tipo'))->toContain('Tipo de escala inválido');
});

it('rejeita nome <3 chars', function () {
    $v = ponto18ValidateEscala([
        'nome'                  => 'ab',
        'tipo'                  => Escala::TIPO_FIXA,
        'carga_diaria_minutos'  => 480,
        'carga_semanal_minutos' => 2640,
    ]);

    expect($v->fails())->toBeTrue();
});

it('aceita dias_semana array válido [1..5] segunda-sexta', function () {
    $v = ponto18ValidateEscala([
        'nome'                  => 'Comercial 5x2',
        'tipo'                  => Escala::TIPO_ESCALA_5X2,
        'carga_diaria_minutos'  => 480,
        'carga_semanal_minutos' => 2400,
        'dias_semana'           => [1, 2, 3, 4, 5],
    ]);

    expect($v->fails())->toBeFalse();
});

it('rejeita dia_semana >6 (sábado=6 é o max)', function () {
    $v = ponto18ValidateEscala([
        'nome'                  => 'Inválida',
        'tipo'                  => Escala::TIPO_FIXA,
        'carga_diaria_minutos'  => 480,
        'carga_semanal_minutos' => 2640,
        'dias_semana'           => [7], // dia 7 não existe
    ]);

    expect($v->fails())->toBeTrue();
});
