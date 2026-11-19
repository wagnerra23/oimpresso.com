<?php

declare(strict_types=1);

/*
 * Contrato do helper único de reais → centavos do RecurringBilling (achado d9, auditoria
 * 2026-11-19, L2 PR 4). Puro: não toca banco, roda em qualquer lane.
 *
 * Dois caminhos independentes:
 *  1. tabela de bordas com o número esperado escrito à mão;
 *  2. varredura de 0,00 a 999,99 (todos os centavos) comparando com round((float) v * 100),
 *     que só é seguro para valores de até 2 casas — exatamente o domínio da varredura.
 */

use Modules\RecurringBilling\Services\Centavos;

uses(Tests\TestCase::class);

it('d9 · bordas: negativo, sem fração, uma casa, mais de 2 casas, float', function (string|int|float $entrada, int $esperado) {
    expect(Centavos::de($entrada))->toBe($esperado);
})->with([
    'decimal do MySQL'              => ['106.07', 10607],
    'um centavo a menos'            => ['106.06', 10606],
    'um centavo'                    => ['0.01', 1],
    'zero'                          => ['0.00', 0],
    'sem fração'                    => ['10', 1000],
    'ponto sem casas'               => ['10.', 1000],
    'uma casa'                      => ['1.5', 150],
    'negativo menor que 1 real'     => ['-0.50', -50],   // antes: +50
    'negativo com inteiro'          => ['-12.34', -1234], // antes: -1166
    'sinal positivo explícito'      => ['+3.20', 320],
    '3 casas abaixo da metade'      => ['1.234', 123],
    '3 casas na metade'             => ['1.235', 124],
    '3 casas negativo na metade'    => ['-1.235', -124],
    'muitas casas'                  => ['2.9999', 300],
    'espaço em volta'               => [' 7.10 ', 710],
    'int é real inteiro'            => [25, 2500],
    'float 0.29 (float*100 = 28,99…)' => [0.29, 29],
    'float 106.07'                  => [106.07, 10607],
]);

it('d9 · um centavo a menos continua menor (comparação do pagamento com a fatura)', function () {
    expect(Centavos::de('106.06'))->toBeLessThan(Centavos::de('106.07'))
        ->and(Centavos::de('106.07') - Centavos::de('106.06'))->toBe(1);
});

it('d9 · texto que não é valor em reais lança em vez de virar zero', function (string $entrada) {
    expect(fn () => Centavos::de($entrada))->toThrow(InvalidArgumentException::class);
})->with(['vírgula' => ['106,07'], 'milhar' => ['1.234.56'], 'vazio' => [''], 'letras' => ['abc'], 'científica' => ['1e3']]);

it('d9 · 2º caminho: de 0,00 a 999,99 bate com round(float * 100) em todos os centavos', function () {
    $divergentes = [];
    for ($c = 0; $c <= 99999; $c++) {
        $texto = sprintf('%d.%02d', intdiv($c, 100), $c % 100);
        foreach ([$texto, '-'.$texto] as $v) {
            if (Centavos::de($v) !== (int) round((float) $v * 100)) {
                $divergentes[] = $v;
            }
        }
    }
    expect($divergentes)->toBe([]);
});
