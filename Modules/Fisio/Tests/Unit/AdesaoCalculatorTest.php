<?php

declare(strict_types=1);
// Cobre, na regra pura, UC-FPN-02 (Fisio/Painel.casos.md).

use Modules\Fisio\Services\AdesaoCalculator;

/**
 * Adesão 7d — serviço PURO: sem DB, sem sessão, sem relógio global (o "hoje" entra por
 * parâmetro). Por isso NÃO usa Tests\TestCase: roda em qualquer lane, inclusive sqlite.
 *
 * Contrato (handoff F1):
 *   adesão = nº de DIAS distintos, na janela [hoje-6, hoje], com check-in que tem ≥1 exercício
 *            feito ÷ sessions_per_week; ×100; arredondado (meio para cima); limitado a 100.
 *   Sem programa ativo → null (não é 0%: "sem meta" ≠ "não cumpriu").
 *   sessions_per_week fora de 1..7 → InvalidArgumentException (nunca divide por zero).
 *
 * Assinatura assumida:
 *   AdesaoCalculator::calcular(list<array{date:string, done_count:int}> $checkins,
 *                              int $sessionsPerWeek, string $hoje, bool $programaAtivo = true): ?int
 */
function uadAdesao(array $checkins, int $spw, bool $ativo = true): ?int
{
    return (new AdesaoCalculator())->calcular($checkins, $spw, '2026-10-14', $ativo);
}

function uadCk(string $date, int $done = 1): array
{
    return ['date' => $date, 'done_count' => $done];
}

it('UC-FPN-02 · conta dias com exercício feito dentro da janela ÷ meta', function () {
    expect(uadAdesao([uadCk('2026-10-09'), uadCk('2026-10-12')], 3))->toBe(67)
        ->and(uadAdesao([uadCk('2026-10-14')], 2))->toBe(50)
        ->and(uadAdesao([], 3))->toBe(0);
});

it('UC-FPN-02 · janela é [hoje-6, hoje]: 08/10 entra, 07/10 não, amanhã não', function () {
    expect(uadAdesao([uadCk('2026-10-08')], 1))->toBe(100)
        ->and(uadAdesao([uadCk('2026-10-07')], 1))->toBe(0)
        ->and(uadAdesao([uadCk('2026-10-15')], 1))->toBe(0);
});

it('UC-FPN-02 · check-in sem nenhum exercício feito não conta', function () {
    expect(uadAdesao([uadCk('2026-10-12', 0), uadCk('2026-10-13', 0)], 2))->toBe(0);
});

it('UC-FPN-02 · dia repetido na entrada conta uma vez', function () {
    expect(uadAdesao([uadCk('2026-10-12'), uadCk('2026-10-12', 3)], 2))->toBe(50);
});

it('UC-FPN-02 · mais dias que a meta → 100, nunca acima', function () {
    $sete = array_map(fn ($d) => uadCk("2026-10-{$d}"), ['08', '09', '10', '11', '12', '13', '14']);

    expect(uadAdesao($sete, 3))->toBe(100)
        ->and(uadAdesao($sete, 7))->toBe(100)
        ->and(uadAdesao(array_slice($sete, 0, 6), 7))->toBe(86);
});

it('UC-FPN-02 · arredonda meio para cima: 1/6 = 16,67 → 17; 1/8 inexiste (meta máx 7)', function () {
    expect(uadAdesao([uadCk('2026-10-14')], 6))->toBe(17)
        ->and(uadAdesao([uadCk('2026-10-14')], 7))->toBe(14);
});

it('UC-FPN-02 · sem programa ativo → null, mesmo com check-ins', function () {
    expect(uadAdesao([uadCk('2026-10-14')], 3, false))->toBeNull();
});

dataset('meta invalida', ['zero' => [0], 'negativa' => [-2], 'oito' => [8]]);

it('UC-FPN-02 · sessions_per_week fora de 1..7 é proibido (não divide por zero)', function (int $spw) {
    expect(fn () => uadAdesao([uadCk('2026-10-14')], $spw))->toThrow(InvalidArgumentException::class);
})->with('meta invalida');
