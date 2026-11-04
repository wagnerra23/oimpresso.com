<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Services\Tributacao;

use NFePHP\Gtin\Gtin;

/**
 * CEST e GTIN do item da NF-e (Sabor Brasil, [F] 2026-11-04).
 *
 * Até aqui toda nota saía com `cEAN = SEM GTIN` e sem a tag CEST, mesmo com o produto cadastrado.
 * Agora o cadastro vai para o XML — mas só o que a SEFAZ aceita:
 *   - GTIN: o mesmo validador que o sped-nfe usa no `tagprod` (dígito verificador e prefixo GS1).
 *     Código inválido vira "SEM GTIN", como antes — mandar código ruim rejeita a nota (rejeição 611);
 *   - CEST: 7 dígitos (Convênio ICMS 142/2018). Fora disso, a tag não vai.
 */
final class GtinCest
{
    public static function gtin(?string $codigo): ?string
    {
        $codigo = trim((string) $codigo);
        if (! preg_match('/^\d{8}$|^\d{12,14}$/', $codigo) || ! class_exists(Gtin::class)) {
            return null;
        }
        try {
            return Gtin::check($codigo)->isValid() ? $codigo : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function cest(?string $cest): ?string
    {
        $cest = preg_replace('/\D/', '', (string) $cest);

        return strlen((string) $cest) === 7 ? $cest : null;
    }
}
