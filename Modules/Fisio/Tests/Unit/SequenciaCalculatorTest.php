<?php

declare(strict_types=1);
// Cobre, na regra pura, UC-FPP-18 (Fisio/Public/Portal.casos.md).

use Modules\Fisio\Services\SequenciaCalculator;

/**
 * Sequência por dias PRESCRITOS (ADR 0437 §6) — serviço PURO, sem TestCase.
 *
 * Contrato que este teste FIXA:
 *   folga(s)  = ceil(7 / sessions_per_week) - 1  → dias seguidos sem registro tolerados.
 *               s=7 → 0 · s=3 → 2 · s=2 → 3 · s=1 → 6.
 *   Dia que SOMA  = check-in com ≥1 exercício feito.
 *   Dia de PAUSA  = check-in com dor ≥ limiar OU red_flags preenchido (mesmo sem exercício):
 *                   NÃO soma, mas também NÃO quebra — reinicia a contagem de folga.
 *   Check-in sem exercício e sem pausa: conta como dia sem registro (não soma, não liga).
 *   A sequência está viva se, de HOJE para trás, nenhum intervalo entre elos (e entre o
 *   último elo e hoje) ultrapassa a folga. Valor = nº de dias que somam na corrente viva.
 *   "Intervalo" = nº de dias SEM elo estritamente entre dois elos (ou entre o último elo e
 *   hoje, hoje excluído — o paciente ainda pode registrar hoje). Corrente morta → 0.
 *
 * Assinatura assumida:
 *   SequenciaCalculator::calcular(list<array{date:string, done_count:int, pain:?int, red_flags?:list<string>}> $checkins,
 *                                 int $sessionsPerWeek, string $hoje, int $limiarDor = 7): array{sequencia:int, em_pausa:bool}
 */
function usqCalc(array $checkins, int $spw = 3, string $hoje = '2026-10-14'): array
{
    return (new SequenciaCalculator())->calcular($checkins, $spw, $hoje, 7);
}

function usqFeito(string $d): array
{
    return ['date' => $d, 'done_count' => 1, 'pain' => 2, 'red_flags' => []];
}

function usqPausa(string $d, ?int $dor = 8, array $flags = []): array
{
    return ['date' => $d, 'done_count' => 0, 'pain' => $dor, 'red_flags' => $flags];
}

it('UC-FPP-18 · sem check-in nenhum → 0, fora de pausa', function () {
    expect(usqCalc([]))->toBe(['sequencia' => 0, 'em_pausa' => false]);
});

it('UC-FPP-18 · 3x/semana: dia sim, dia não é sequência contínua', function () {
    $dias = array_map('usqFeito', ['2026-10-06', '2026-10-08', '2026-10-10', '2026-10-12', '2026-10-14']);

    expect(usqCalc($dias)['sequencia'])->toBe(5);
});

it('UC-FPP-18 · 3x/semana: 2 dias de descanso seguidos não quebram; 3 quebram', function () {
    expect(usqCalc([usqFeito('2026-10-08'), usqFeito('2026-10-11'), usqFeito('2026-10-14')])['sequencia'])->toBe(3)
        ->and(usqCalc([usqFeito('2026-10-07'), usqFeito('2026-10-11'), usqFeito('2026-10-14')])['sequencia'])->toBe(2);
});

dataset('folga por meta', [
    '7x: nenhum dia de folga' => [7, '2026-10-12', 1],
    '7x: dia seguido mantém' => [7, '2026-10-13', 2],
    '2x: 3 dias de folga mantêm' => [2, '2026-10-10', 2],
    '2x: 4 dias quebram' => [2, '2026-10-09', 1],
    '1x: 6 dias de folga mantêm' => [1, '2026-10-07', 2],
    '1x: 7 dias quebram' => [1, '2026-10-06', 1],
]);

it('UC-FPP-18 · folga = ceil(7/meta) - 1', function (int $spw, string $anterior, int $esperado) {
    expect(usqCalc([usqFeito($anterior), usqFeito('2026-10-14')], $spw)['sequencia'])->toBe($esperado);
})->with('folga por meta');

it('UC-FPP-18 · a corrente morre se o último elo já passou da folga até hoje', function () {
    expect(usqCalc([usqFeito('2026-10-10'), usqFeito('2026-10-11')])['sequencia'])->toBe(2)   // hoje 14: 2 dias de folga
        ->and(usqCalc([usqFeito('2026-10-09'), usqFeito('2026-10-10')])['sequencia'])->toBe(0); // 3 dias: morreu
});

it('UC-FPP-18 · dia de pausa (dor alta) não soma e não quebra; hoje em pausa → em_pausa', function () {
    $r = usqCalc([usqFeito('2026-10-10'), usqFeito('2026-10-12'), usqPausa('2026-10-14')]);

    expect($r)->toBe(['sequencia' => 2, 'em_pausa' => true]);
});

it('UC-FPP-18 · pausa no meio liga as pontas que, sem ela, passariam da folga', function () {
    // 08 → 14 são 5 dias vazios (quebraria); com pausa no 11, vira 2 + 2 dias de folga.
    expect(usqCalc([usqFeito('2026-10-08'), usqPausa('2026-10-11'), usqFeito('2026-10-14')])['sequencia'])->toBe(2)
        ->and(usqCalc([usqFeito('2026-10-08'), usqFeito('2026-10-14')])['sequencia'])->toBe(1);
});

it('UC-FPP-18 · sinal de alerta com dor baixa também é pausa', function () {
    expect(usqCalc([usqFeito('2026-10-12'), usqPausa('2026-10-14', 1, ['febre'])]))->toBe(['sequencia' => 1, 'em_pausa' => true]);
});

it('UC-FPP-18 · check-in sem exercício e sem pausa não liga a corrente', function () {
    $vazio = ['date' => '2026-10-11', 'done_count' => 0, 'pain' => 2, 'red_flags' => []];

    expect(usqCalc([usqFeito('2026-10-08'), $vazio, usqFeito('2026-10-14')])['sequencia'])->toBe(1);
});

it('UC-FPP-18 · dor com exercício feito no mesmo dia: soma (fez o prescrito) e marca pausa', function () {
    $doeuMasFez = ['date' => '2026-10-14', 'done_count' => 1, 'pain' => 8, 'red_flags' => []];

    expect(usqCalc([usqFeito('2026-10-12'), $doeuMasFez]))->toBe(['sequencia' => 2, 'em_pausa' => true]);
});

it('UC-FPP-18 · meta fora de 1..7 é proibida', function () {
    expect(fn () => usqCalc([usqFeito('2026-10-14')], 0))->toThrow(InvalidArgumentException::class);
});
