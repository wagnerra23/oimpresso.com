<?php

declare(strict_types=1);
// Cobre, na regra pura, UC-FPP-17 (Fisio/Public/Portal.casos.md).

use Modules\Fisio\Services\NivelCalculator;

/**
 * Nível derivado do XP total — serviço PURO, sem TestCase. A tabela de limiares vem de
 * `config('fisio.niveis')` no app; aqui entra por parâmetro.
 *
 * Contrato: nível = maior N cujo limiar ≤ xp. Limiares precisam começar em 0 e crescer
 * estritamente; tabela inválida → InvalidArgumentException. XP negativo → nível 1 (o ledger
 * não tem XP negativo, mas o cálculo não pode quebrar).
 *
 * Assinatura assumida:
 *   NivelCalculator::nivel(int $xp, array<int,int> $limiares): int
 *   NivelCalculator::progresso(int $xp, array<int,int> $limiares): array{nivel:int, xp_no_nivel:int, xp_para_proximo:?int}
 */
const UNV_TABELA = [1 => 0, 2 => 20, 3 => 60, 4 => 150];

dataset('xp para nivel', [
    'zero' => [0, 1], 'logo abaixo do 2' => [19, 1], 'exato no 2' => [20, 2],
    'meio do 3' => [100, 3], 'exato no último' => [150, 4], 'muito acima' => [99999, 4], 'negativo' => [-5, 1],
]);

it('UC-FPP-17 · nível = maior limiar alcançado', function (int $xp, int $nivel) {
    expect((new NivelCalculator())->nivel($xp, UNV_TABELA))->toBe($nivel);
})->with('xp para nivel');

it('UC-FPP-17 · progresso dentro do nível e quanto falta para o próximo', function () {
    $calc = new NivelCalculator();

    expect($calc->progresso(35, UNV_TABELA))->toBe(['nivel' => 2, 'xp_no_nivel' => 15, 'xp_para_proximo' => 25])
        ->and($calc->progresso(150, UNV_TABELA))->toBe(['nivel' => 4, 'xp_no_nivel' => 0, 'xp_para_proximo' => null]);
});

it('UC-FPP-17 · tabela que não começa em 0 ou não cresce é recusada', function () {
    $calc = new NivelCalculator();

    expect(fn () => $calc->nivel(10, [1 => 5, 2 => 20]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $calc->nivel(10, [1 => 0, 2 => 20, 3 => 20]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $calc->nivel(10, []))->toThrow(InvalidArgumentException::class);
});
