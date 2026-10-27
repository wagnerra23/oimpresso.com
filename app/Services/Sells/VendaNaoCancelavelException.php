<?php

declare(strict_types=1);

namespace App\Services\Sells;

/** A venda não pode ser cancelada agora; a mensagem diz o que falta para liberar ([W] 2026-10-26). */
final class VendaNaoCancelavelException extends \RuntimeException
{
    /** @param list<string> $pendencias */
    public function __construct(public readonly array $pendencias)
    {
        parent::__construct(implode(' ', $pendencias));
    }
}
