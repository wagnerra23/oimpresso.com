<?php

declare(strict_types=1);

namespace Modules\Fisio\Services;

use InvalidArgumentException;

/**
 * Nível derivado do XP total (US-FISIO-008, ADR 0437 §6) — serviço PURO.
 *
 * nível = maior N cujo limiar ≤ xp. Os limiares vêm de `config('fisio.niveis')` no app e
 * entram aqui por parâmetro. Como o XP vem de um ledger append-only (só soma), o nível
 * nunca desce. XP negativo → nível 1 (o ledger não tem, mas a conta não quebra).
 *
 * Contrato: Modules/Fisio/Tests/Unit/NivelCalculatorTest.php.
 */
final class NivelCalculator
{
    /**
     * @param  array<int,int>  $limiares  nível => XP mínimo; começa em 0 e cresce estritamente
     *
     * @throws InvalidArgumentException tabela vazia, que não começa em 0 ou não cresce
     */
    public function nivel(int $xp, array $limiares): int
    {
        return $this->progresso($xp, $limiares)['nivel'];
    }

    /**
     * @param  array<int,int>  $limiares
     * @return array{nivel:int, xp_no_nivel:int, xp_para_proximo:?int}
     */
    public function progresso(int $xp, array $limiares): array
    {
        $tabela = $this->validar($limiares);
        $xp = max(0, $xp);

        $niveis = array_keys($tabela);
        $atual = $niveis[0];
        $proximo = null;
        foreach ($niveis as $i => $n) {
            if ($tabela[$n] <= $xp) {
                $atual = $n;
                $proximo = $niveis[$i + 1] ?? null;
            }
        }

        return [
            'nivel' => $atual,
            'xp_no_nivel' => $xp - $tabela[$atual],
            'xp_para_proximo' => $proximo === null ? null : $tabela[$proximo] - $xp,
        ];
    }

    /**
     * @param  array<int,int>  $limiares
     * @return array<int,int> ordenada por nível
     */
    private function validar(array $limiares): array
    {
        if ($limiares === []) {
            throw new InvalidArgumentException('Tabela de níveis vazia.');
        }
        ksort($limiares);
        $anterior = null;
        foreach ($limiares as $nivel => $min) {
            if ($anterior === null && (int) $min !== 0) {
                throw new InvalidArgumentException("O primeiro nível precisa começar em 0 XP (nível {$nivel} = {$min}).");
            }
            if ($anterior !== null && (int) $min <= $anterior) {
                throw new InvalidArgumentException("Limiares de nível precisam crescer estritamente (nível {$nivel} = {$min}).");
            }
            $anterior = (int) $min;
        }

        return array_map('intval', $limiares);
    }
}
