<?php

namespace Modules\Producao\Services;

use InvalidArgumentException;

/**
 * Custo-hora como texto decimal, sem passar por float (UC-PCCH-12 · regra mestre de valor).
 *
 * A terceira casa arredonda para longe do zero, como o `round()` fazia. Quem recebe valor digitado
 * tem de recusar notação científica antes (`not_regex:/e/i`): `numeric` aceita "1e2".
 */
final class CustoHoraDecimal
{
    public static function emCentavos(string|int|float $valor): int
    {
        $texto = trim((string) $valor);
        if (! preg_match('/^([+-]?)(\d*)(?:\.(\d*))?$/', $texto, $m) || ($m[2] === '' && ($m[3] ?? '') === '')) {
            throw new InvalidArgumentException('Custo-hora inválido.');
        }

        $casas = str_pad($m[3] ?? '', 3, '0');
        $centavos = (int) $m[2] * 100 + (int) substr($casas, 0, 2) + ((int) $casas[2] >= 5 ? 1 : 0);

        return $m[1] === '-' ? -$centavos : $centavos;
    }

    public static function comDuasCasas(string|int|float $valor): string
    {
        $centavos = self::emCentavos($valor);
        $sinal = $centavos < 0 ? '-' : '';
        $absoluto = abs($centavos);

        return sprintf('%s%d.%02d', $sinal, intdiv($absoluto, 100), $absoluto % 100);
    }
}
