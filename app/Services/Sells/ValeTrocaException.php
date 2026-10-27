<?php

declare(strict_types=1);

namespace App\Services\Sells;

/** Recusa do vale-troca, com código estável para a tela (US-VEST-021). */
final class ValeTrocaException extends \RuntimeException
{
    public function __construct(public readonly string $codigo, string $mensagem)
    {
        parent::__construct($mensagem);
    }
}
