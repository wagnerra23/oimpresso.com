<?php

declare(strict_types=1);
// Cobre, na regra pura, UC-FJG-08 (memory/requisitos/Fisio/_telas/jogo-constancia.casos.md).
// @covers-us US-FISIO-008

use Modules\Fisio\Services\DiaLocal;
use Modules\Fisio\Services\MedalhasCalculator;
use Modules\Fisio\Services\SequenciaCalculator;

/**
 * Medalhas (T-08c, etapa 02 §3.4) — serviço PURO, DERIVADO do histórico.
 * Meta 7x/semana nos casos de sequência (folga 0) para a conta ficar à vista.
 */
function umdCalc(): MedalhasCalculator
{
    return new MedalhasCalculator(new SequenciaCalculator());
}

/** @return list<array{date:string, done_count:int, pain:?int, red_flags:list<string>, crise:bool}> */
function umdDias(string $de, int $n, ?int $dor = 2): array
{
    $out = [];
    for ($i = 0; $i < $n; $i++) {
        $out[] = ['date' => DiaLocal::somar($de, $i), 'done_count' => 1, 'pain' => $dor, 'red_flags' => [], 'crise' => false];
    }

    return $out;
}

it('UC-FJG-08 · sem check-in, nenhuma medalha; o primeiro registro dá a primeira', function () {
    expect(umdCalc()->ganhas([], 3, 7, 1))->toBe([])
        ->and(umdCalc()->ganhas([['date' => '2026-10-12', 'done_count' => 0, 'pain' => null, 'red_flags' => [], 'crise' => false]], 3, 7, 1))->toBe(['primeiro_registro']);
});

it('UC-FJG-08 · a sequência 7 fica mesmo depois de a sequência quebrar (melhor sequência)', function () {
    // 7 dias seguidos (seg 05 a dom 11), um buraco, 2 dias: a sequência de hoje é 2, a melhor 7.
    $checkins = [...umdDias('2026-10-05', 7), ...umdDias('2026-10-13', 2)];

    expect(umdCalc()->melhorSequencia($checkins, 7, 7))->toBe(7)
        ->and((new SequenciaCalculator())->calcular($checkins, 7, '2026-10-14', 7)['sequencia'])->toBe(2)
        ->and(umdCalc()->ganhas($checkins, 7, 7, 1))->toContain('sequencia_7')->not->toContain('sequencia_21');
});

it('UC-FJG-08 · semana completa é ≥ 80% da meta na semana de segunda a domingo', function () {
    // Meta 3 → precisa de 3 dias (ceil 2,4). Seg 05 + qua 07 = 2: não completa; + sex 09 = 3: completa.
    $dois = [umdDias('2026-10-05', 1)[0], umdDias('2026-10-07', 1)[0]];
    $tres = [...$dois, umdDias('2026-10-09', 1)[0]];

    expect(umdCalc()->ganhas($dois, 3, 7, 1))->not->toContain('semana_completa_1')
        ->and(umdCalc()->ganhas($tres, 3, 7, 1))->toContain('semana_completa_1')->not->toContain('semanas_completas_4');

    $quatro = [];
    foreach (['2026-09-14', '2026-09-21', '2026-09-28', '2026-10-05'] as $seg) {
        $quatro = [...$quatro, ...umdDias($seg, 3)];
    }
    expect(umdCalc()->ganhas($quatro, 3, 7, 1))->toContain('semanas_completas_4');
});

it('UC-FJG-08 · voltou depois de pausa, registro honesto e nível', function () {
    $pausa = ['date' => '2026-10-06', 'done_count' => 0, 'pain' => 8, 'red_flags' => [], 'crise' => false];
    $antes = umdDias('2026-10-05', 1);
    $depois = umdDias('2026-10-08', 1);

    expect(umdCalc()->ganhas([...$antes, $pausa], 3, 7, 1))->not->toContain('voltou_depois_de_pausa')
        ->and(umdCalc()->ganhas([...$antes, $pausa, ...$depois], 3, 7, 1))->toContain('voltou_depois_de_pausa');

    // 10 registros com dor anotada (inclusive dor alta); sem dor anotada não conta.
    $nove = umdDias('2026-10-01', 9);
    $semDor = umdDias('2026-10-10', 3, null);
    expect(umdCalc()->ganhas([...$nove, ...$semDor], 3, 7, 1))->not->toContain('registro_honesto')
        ->and(umdCalc()->ganhas([...$nove, $pausa], 3, 7, 1))->toContain('registro_honesto');

    expect(umdCalc()->ganhas($antes, 3, 7, 4))->not->toContain('nivel_5')
        ->and(umdCalc()->ganhas($antes, 3, 7, 5))->toContain('nivel_5')->not->toContain('nivel_10')
        ->and(umdCalc()->ganhas($antes, 3, 7, 10))->toContain('nivel_10');
});

it('UC-FJG-08 · são 10 medalhas; as duas sem fonte (horário combinado, alta) não existem ainda', function () {
    expect(array_keys(MedalhasCalculator::MEDALHAS))->toHaveCount(10)
        ->not->toContain('horario_combinado_10')
        ->not->toContain('programa_concluido');
});
