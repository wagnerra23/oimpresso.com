<?php

declare(strict_types=1);

namespace Modules\Fisio\Services;

use InvalidArgumentException;

/**
 * Limiar de dor que pausa o jogo e sinaliza a clínica (US-FISIO-008, ADR 0437 §5 e §6) —
 * serviço PURO.
 *
 * Dois modos, escolhidos pelo PROFISSIONAL no programa (`fisio_programs.pain_alert_mode`):
 *  - `absoluto`:    dor ≥ limiar do programa (padrão 7).
 *  - `sobre_basal`: dor ≥ dor de base da própria paciente + margem (protocolo 11 —
 *                   fibromialgia: "dor de base é esperada"). A base é a média das dores
 *                   informadas na janela inicial do programa (padrão: 7 dias, com pelo menos
 *                   3 registros). Sem base ainda, vale o limiar absoluto.
 *
 * Não decide conduta: só devolve o número que o portal usa para sair do jogo e mostrar a
 * instrução escrita pelo profissional, e que o painel usa para a fila de atenção.
 *
 * Contrato: Modules/Fisio/Tests/Unit/LimiarDorCalculatorTest.php.
 */
final class LimiarDorCalculator
{
    public const MODO_ABSOLUTO = 'absoluto';

    public const MODO_SOBRE_BASAL = 'sobre_basal';

    public const DOR_MAXIMA = 10;

    /**
     * Dor de base: média das dores informadas de $inicio até $inicio + $janelaDias - 1.
     *
     * @param  list<array{date:string, pain:?int}>  $checkins
     * @return ?float null se houver menos de $minimoRegistros dores na janela
     */
    public function base(array $checkins, string $inicio, int $janelaDias, int $minimoRegistros): ?float
    {
        if ($janelaDias < 1 || $minimoRegistros < 1) {
            throw new InvalidArgumentException('Janela e mínimo de registros da dor de base precisam ser >= 1.');
        }
        DiaLocal::parse($inicio);
        $fim = DiaLocal::somar($inicio, $janelaDias - 1);

        $porDia = [];
        foreach ($checkins as $c) {
            $dia = DiaLocal::parse((string) $c['date'])->format('Y-m-d');
            if ($dia >= $inicio && $dia <= $fim && $c['pain'] !== null) {
                $porDia[$dia] = (int) $c['pain']; // um registro por dia (o último vale)
            }
        }

        return count($porDia) < $minimoRegistros ? null : array_sum($porDia) / count($porDia);
    }

    /**
     * @throws InvalidArgumentException modo desconhecido
     */
    public function limiar(string $modo, int $limiarAbsoluto, ?float $base, int $margem): int
    {
        if ($modo === self::MODO_ABSOLUTO || ($modo === self::MODO_SOBRE_BASAL && $base === null)) {
            return $limiarAbsoluto;
        }
        if ($modo !== self::MODO_SOBRE_BASAL) {
            throw new InvalidArgumentException("Modo de alerta de dor desconhecido: '{$modo}'.");
        }

        // "dor ≥ base + margem" com dor inteira: 5,4 + 2 = 7,4 → a partir de 8.
        return min(self::DOR_MAXIMA, (int) ceil(round((float) $base + $margem, 6)));
    }
}