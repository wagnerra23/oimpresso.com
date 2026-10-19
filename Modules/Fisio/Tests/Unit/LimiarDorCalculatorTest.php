<?php

declare(strict_types=1);
// @covers-us US-FISIO-008
// Cobre, na regra pura, UC-FJG-04 (memory/requisitos/Fisio/_telas/jogo-constancia.casos.md).

use Modules\Fisio\Services\LimiarDorCalculator;

/**
 * Limiar de dor que pausa o jogo — serviço PURO, sem TestCase. Protocolo 11 (fibromialgia):
 * "dor de base é esperada"; o alerta usa mudança sobre a dor basal da própria paciente.
 *
 * Assinatura:
 *   LimiarDorCalculator::base(list<array{date,pain}> $checkins, string $inicio, int $janelaDias, int $minimoRegistros): ?float
 *   LimiarDorCalculator::limiar(string $modo, int $limiarAbsoluto, ?float $base, int $margem): int
 */
function ulmDor(string $d, ?int $dor): array
{
    return ['date' => $d, 'pain' => $dor];
}

it('UC-FJG-04 · dor de base = média das dores da 1ª semana do programa', function () {
    $c = new LimiarDorCalculator();
    $semana = [ulmDor('2026-10-01', 5), ulmDor('2026-10-03', 6), ulmDor('2026-10-05', 5), ulmDor('2026-10-06', 6)];

    expect($c->base($semana, '2026-10-01', 7, 3))->toBe(5.5)
        // registro fora da janela (antes do início ou depois do 7º dia) não entra na base
        ->and($c->base([...$semana, ulmDor('2026-09-30', 10), ulmDor('2026-10-08', 0)], '2026-10-01', 7, 3))->toBe(5.5)
        // dor não informada não conta como zero
        ->and($c->base([...$semana, ulmDor('2026-10-07', null)], '2026-10-01', 7, 3))->toBe(5.5);
});

it('UC-FJG-04 · sem registros suficientes na janela, não há base', function () {
    expect((new LimiarDorCalculator())->base([ulmDor('2026-10-01', 5), ulmDor('2026-10-02', 6)], '2026-10-01', 7, 3))->toBeNull();
});

it('UC-FJG-04 · sobre_basal: limiar = base + margem, arredondado para cima e no máximo 10', function () {
    $c = new LimiarDorCalculator();

    expect($c->limiar('sobre_basal', 7, 5.5, 2))->toBe(8)   // 7,5 → a partir de 8
        ->and($c->limiar('sobre_basal', 7, 5.0, 2))->toBe(7)
        ->and($c->limiar('sobre_basal', 7, 2.0, 2))->toBe(4) // dor de base baixa: alerta mais cedo que o absoluto
        ->and($c->limiar('sobre_basal', 7, 9.5, 2))->toBe(10);
});

it('UC-FJG-04 · sem base ainda, ou modo absoluto, vale o limiar do programa', function () {
    $c = new LimiarDorCalculator();

    expect($c->limiar('sobre_basal', 6, null, 2))->toBe(6)
        ->and($c->limiar('absoluto', 7, 5.5, 2))->toBe(7);
});

it('UC-FJG-04 · modo desconhecido ou janela inválida é recusado', function () {
    $c = new LimiarDorCalculator();

    expect(fn () => $c->limiar('relativo', 7, 5.0, 2))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $c->base([], '2026-10-01', 0, 3))->toThrow(InvalidArgumentException::class);
});