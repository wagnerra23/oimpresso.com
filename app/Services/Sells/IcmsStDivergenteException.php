<?php

declare(strict_types=1);

namespace App\Services\Sells;

/** O ICMS-ST recalculado ao gravar não bate com o que a tela mostrou (US-SELL-078). */
final class IcmsStDivergenteException extends \RuntimeException
{
    public function __construct(public readonly float $calculado, public readonly float $enviado)
    {
        parent::__construct(sprintf(
            'O ICMS-ST desta venda é R$ %s, mas o total enviado considerou R$ %s. Atualize a venda e salve de novo.',
            number_format($calculado, 2, ',', '.'),
            number_format($enviado, 2, ',', '.'),
        ));
    }
}
