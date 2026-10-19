<?php

declare(strict_types=1);
// Cobre, na regra pura, UC-FJG-01 e UC-FJG-02 (memory/requisitos/Fisio/_telas/jogo-constancia.casos.md).

use Modules\Fisio\Services\XpDoDiaCalculator;

/**
 * XP do dia — serviço PURO, sem TestCase. Números de etapas/02-f1-constancia.md §3.4 (70/30,
 * normalização 4 ÷ n, teto semanal 500), que no app vêm de `config('fisio.jogo')`.
 *
 * Assinatura:
 *   XpDoDiaCalculator::calcular(list<array{id,times_per_day}> $prescritos, array<int,int> $vezesFeitas,
 *       int $sessionsPerWeek, int $xpExercicio, int $xpCheckin, int $base): array{exercicio_concluido:int, checkin:int}
 *   XpDoDiaCalculator::aLancar(array $alvo, array $jaLancadoNoDia, int $xpNaSemana, int $teto): array{checkin:int, exercicio_concluido:int}
 */
const UXP_ITENS = [['id' => 1, 'times_per_day' => 1], ['id' => 2, 'times_per_day' => 2]];

function uxpCalc(array $vezes, int $spw = 4, array $itens = UXP_ITENS): array
{
    return (new XpDoDiaCalculator())->calcular($itens, $vezes, $spw, 70, 30, 4);
}

it('UC-FJG-01 · dia prescrito cumprido inteiro vale 70 + 30 quando a meta é a base (4x)', function () {
    expect(uxpCalc([1 => 1, 2 => 2]))->toBe(['exercicio_concluido' => 70, 'checkin' => 30]);
});

it('UC-FJG-01 · exercício é proporcional às vezes feitas ÷ vezes prescritas', function () {
    expect(uxpCalc([1 => 1])['exercicio_concluido'])->toBe(23)          // 1 de 3 → 23,3
        ->and(uxpCalc([1 => 1, 2 => 1])['exercicio_concluido'])->toBe(47) // 2 de 3 → 46,7
        ->and(uxpCalc([2 => 2])['exercicio_concluido'])->toBe(47);
});

it('UC-FJG-01 · fazer além do prescrito não dá XP (vezes acima de times_per_day e item fora valem 0)', function () {
    expect(uxpCalc([1 => 5, 2 => 9]))->toBe(uxpCalc([1 => 1, 2 => 2]))
        ->and(uxpCalc([999 => 3]))->toBe(['exercicio_concluido' => 0, 'checkin' => 30])
        ->and(uxpCalc([1 => -4]))->toBe(['exercicio_concluido' => 0, 'checkin' => 30]);
});

it('UC-FJG-01 · registrar o dia sem fazer nada (ou sem programa) dá só o XP do registro', function () {
    expect(uxpCalc([]))->toBe(['exercicio_concluido' => 0, 'checkin' => 30])
        ->and(uxpCalc([1 => 1], 4, []))->toBe(['exercicio_concluido' => 0, 'checkin' => 30]);
});

dataset('normalizacao por meta', [
    '1x/semana' => [1, 280, 120],
    '3x/semana' => [3, 93, 40],
    '4x/semana' => [4, 70, 30],
    '7x/semana' => [7, 40, 17],
]);

it('UC-FJG-01 · XP do dia × 4 ÷ meta: a semana inteira vale ~400 em qualquer prescrição', function (int $spw, int $ex, int $chk) {
    $dia = uxpCalc([1 => 1, 2 => 2], $spw);

    expect($dia)->toBe(['exercicio_concluido' => $ex, 'checkin' => $chk])
        ->and(abs(($ex + $chk) * $spw - 400))->toBeLessThanOrEqual(7);
})->with('normalizacao por meta');

it('UC-FJG-01 · meta fora de 1..7, base < 1 ou XP negativo é proibido', function () {
    $c = new XpDoDiaCalculator();

    expect(fn () => $c->calcular(UXP_ITENS, [], 0, 70, 30, 4))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $c->calcular(UXP_ITENS, [], 8, 70, 30, 4))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $c->calcular(UXP_ITENS, [], 3, 70, 30, 0))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $c->calcular(UXP_ITENS, [], 3, -1, 30, 4))->toThrow(InvalidArgumentException::class);
});

it('UC-FJG-02 · lança só a diferença positiva do dia; nunca negativo (ledger não estorna)', function () {
    $c = new XpDoDiaCalculator();
    $alvo = ['exercicio_concluido' => 47, 'checkin' => 30];

    expect($c->aLancar($alvo, [], 0, 500))->toBe(['checkin' => 30, 'exercicio_concluido' => 47])
        ->and($c->aLancar($alvo, ['checkin' => 30, 'exercicio_concluido' => 23], 53, 500))->toBe(['checkin' => 0, 'exercicio_concluido' => 24])
        ->and($c->aLancar(['exercicio_concluido' => 0, 'checkin' => 30], ['checkin' => 30, 'exercicio_concluido' => 70], 100, 500))
        ->toBe(['checkin' => 0, 'exercicio_concluido' => 0]);
});

it('UC-FJG-02 · teto semanal: o que passa do teto não entra, e o registro do dia entra primeiro', function () {
    $c = new XpDoDiaCalculator();
    $alvo = ['exercicio_concluido' => 70, 'checkin' => 30];

    expect($c->aLancar($alvo, [], 450, 500))->toBe(['checkin' => 30, 'exercicio_concluido' => 20])
        ->and($c->aLancar($alvo, [], 480, 500))->toBe(['checkin' => 20, 'exercicio_concluido' => 0])
        ->and($c->aLancar($alvo, [], 500, 500))->toBe(['checkin' => 0, 'exercicio_concluido' => 0])
        ->and($c->aLancar($alvo, [], 900, 500))->toBe(['checkin' => 0, 'exercicio_concluido' => 0]);
});