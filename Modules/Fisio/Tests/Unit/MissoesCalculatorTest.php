<?php

declare(strict_types=1);
// Cobre, na regra pura, UC-FJG-06 (memory/requisitos/Fisio/_telas/jogo-constancia.casos.md).
// @covers-us US-FISIO-008

use Modules\Fisio\Services\MissoesCalculator;

/**
 * Missões do dia (T-08c, etapa 02 §3.4) — serviço PURO, sem TestCase.
 *
 * Contrato: o pool nasce SÓ do programa prescrito. Cada missão é o registro do dia ou aponta
 * para um item prescrito; nenhuma pede volume. O teste percorre o pool INTEIRO de vários
 * programas e o sorteio de 60 dias de cada um.
 */
function umcProgramas(): array
{
    return [
        'vazio' => [],
        'um item' => [['id' => 11, 'nome' => 'Ponte', 'times_per_day' => 1]],
        'misto' => [
            ['id' => 21, 'nome' => 'Ponte', 'times_per_day' => 1],
            ['id' => 22, 'nome' => 'Respiração diafragmática', 'times_per_day' => 2],
            ['id' => 23, 'nome' => 'Agachamento na cadeira', 'times_per_day' => 3],
            ['id' => 24, 'nome' => 'Alongamento de panturrilha', 'times_per_day' => 1],
        ],
    ];
}

it('UC-FJG-06 · o pool inteiro só tem o registro do dia e itens prescritos, nunca volume', function () {
    $calc = new MissoesCalculator();
    foreach (umcProgramas() as $nome => $prescritos) {
        $ids = array_column($prescritos, 'id');
        $vezes = array_column($prescritos, 'times_per_day', 'id');
        foreach ($calc->pool($prescritos) as $m) {
            expect($m['tipo'])->toBeIn([MissoesCalculator::TIPO_REGISTRO, MissoesCalculator::TIPO_EXERCICIO, MissoesCalculator::TIPO_SERIES]);
            if ($m['tipo'] === MissoesCalculator::TIPO_REGISTRO) {
                expect($m['item_id'])->toBeNull();
            } else {
                expect($m['item_id'])->toBeIn($ids);
            }
            if ($m['tipo'] === MissoesCalculator::TIPO_SERIES) {
                expect($vezes[$m['item_id']])->toBeGreaterThanOrEqual(2);
            }
            // As proibidas da §3.4: "faça mais", "bata seu recorde", "dois dias seguidos sem descanso".
            expect(mb_strtolower($m['texto']))->not->toMatch('/\bmais\b|recorde|sem descanso|seguidos/u');
        }
        // Programa vazio: só o registro do dia.
        if ($prescritos === []) {
            expect(array_column($calc->pool($prescritos), 'chave'))->toBe(['registro']);
        }
    }
});

it('UC-FJG-06 · o sorteio de cada dia sai do pool, sem repetir, até 3, e é o mesmo o dia todo', function () {
    $calc = new MissoesCalculator();
    foreach (umcProgramas() as $prescritos) {
        $chavesDoPool = array_column($calc->pool($prescritos), 'chave');
        for ($d = 1; $d <= 60; $d++) {
            $semente = '98:7:2026-10-'.str_pad((string) (($d % 28) + 1), 2, '0', STR_PAD_LEFT).':'.$d;
            $dia = array_column($calc->doDia($prescritos, $semente), 'chave');
            expect(count($dia))->toBe(min(MissoesCalculator::POR_DIA, count($chavesDoPool)))
                ->and(array_unique($dia))->toHaveCount(count($dia))
                ->and(array_diff($dia, $chavesDoPool))->toBe([])
                ->and(array_column($calc->doDia($prescritos, $semente), 'chave'))->toBe($dia);
        }
    }
});

it('UC-FJG-06 · cumprir pede o prescrito: fazer a mais não cumpre "a mais", item fora nunca cumpre', function () {
    $calc = new MissoesCalculator();
    $prescritas = [21 => 1, 22 => 2];
    $series = ['chave' => 'series:22', 'tipo' => MissoesCalculator::TIPO_SERIES, 'item_id' => 22];
    $exercicio = ['chave' => 'exercicio:21', 'tipo' => MissoesCalculator::TIPO_EXERCICIO, 'item_id' => 21];
    $fora = ['chave' => 'series:99', 'tipo' => MissoesCalculator::TIPO_SERIES, 'item_id' => 99];
    $registro = ['chave' => 'registro', 'tipo' => MissoesCalculator::TIPO_REGISTRO, 'item_id' => null];

    expect($calc->cumprida($series, true, [22 => 1], $prescritas))->toBeFalse()
        ->and($calc->cumprida($series, true, [22 => 2], $prescritas))->toBeTrue()
        ->and($calc->cumprida($series, true, [22 => 9], $prescritas))->toBeTrue()
        ->and($calc->cumprida($exercicio, true, [21 => 1], $prescritas))->toBeTrue()
        ->and($calc->cumprida($exercicio, true, [], $prescritas))->toBeFalse()
        ->and($calc->cumprida($fora, true, [99 => 5], $prescritas))->toBeFalse()
        ->and($calc->cumprida($registro, true, [], $prescritas))->toBeTrue()
        ->and($calc->cumprida($registro, false, [], $prescritas))->toBeFalse();
});
