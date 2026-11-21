<?php

declare(strict_types=1);

namespace Modules\RecurringBilling\Services;

use InvalidArgumentException;

/**
 * Reais → centavos inteiros, num lugar só do RecurringBilling (achado d9 da auditoria 2026-11-19).
 *
 * Antes havia duas cópias (`CobradorPixAutomatico::centavos` e uma inline em
 * `MarcarFaturaPagaDaCobranca`) que partiam o texto no ponto e somavam as partes. Com sinal, a
 * soma saía errada: "-0.50" virava +50 e "-12.34" virava -1166. E o `MandatoPixAutomatico`
 * passava o valor negociado por float antes de converter.
 *
 * Regras (sem float em nenhum passo):
 *  - texto com ponto decimal, como o MySQL devolve `decimal(…,2)` e o `StoreAssinaturaRequest`
 *    valida (`numeric`): "106.07", "10", "1.5", "-0.50";
 *  - até 2 casas é exato; com mais casas arredonda meio-para-cima no valor absoluto
 *    ("1.235" → 124, "-1.235" → -124), como o arredondamento comercial;
 *  - int vale como reais inteiros; float é convertido pelo texto mais curto que o PHP gera
 *    para ele ("0.29" → 29, nunca 28);
 *  - qualquer outra coisa (vírgula, milhar, notação científica, vazio) lança — valor de dinheiro
 *    não vira zero em silêncio.
 */
final class Centavos
{
    public static function de(string|int|float $valor): int
    {
        if (is_int($valor)) {
            return $valor * 100;
        }
        $texto = trim(is_float($valor) ? (string) $valor : $valor);

        if (preg_match('/^([+-]?)(\d+)(?:\.(\d*))?$/', $texto, $m) !== 1) {
            throw new InvalidArgumentException("Valor em reais inválido para centavos: '{$texto}'");
        }
        $negativo = $m[1] === '-';
        $fracao = $m[3] ?? '';

        $centavos = (int) $m[2] * 100 + (int) str_pad(substr($fracao, 0, 2), 2, '0');
        if (strlen($fracao) > 2 && (int) $fracao[2] >= 5) {
            $centavos++;
        }

        return $negativo ? -$centavos : $centavos;
    }
}
