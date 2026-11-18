<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Assinatura desenhada na tela, recebida como data URL `data:image/png;base64,…`.
 *
 * Mesma regra da entrega do pedido no app (`PedidoEntregaController`) e da entrega da OS na Oficina
 * (`AssinaturaEntrega`): só PNG válido, até 200 KB decodificado. Essas duas ainda têm a cópia delas;
 * passam a usar esta num PR próprio.
 */
final class AssinaturaPng
{
    public const MAX_BYTES = 200 * 1024;

    /** PNG decodificado do data URL, ou null se não for PNG válido de até MAX_BYTES. */
    public static function decodificar(string $texto): ?string
    {
        $base64 = preg_replace('#^data:image/png;base64,#', '', $texto, 1, $trocas);
        if ($trocas !== 1 || $base64 === null) {
            return null;
        }
        if (strlen($base64) > (int) ceil(self::MAX_BYTES * 4 / 3) + 4) {
            return null;
        }
        $bin = base64_decode($base64, true);
        if ($bin === false || strlen($bin) > self::MAX_BYTES || ! str_starts_with($bin, "\x89PNG\r\n\x1a\n")) {
            return null;
        }

        return $bin;
    }
}
