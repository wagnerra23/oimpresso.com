<?php

declare(strict_types=1);

namespace App\Services\Estoque;

/** Recusa da contagem de inventário, com um código estável para a tela e a API (US-ESTOQUE-002). */
final class ContagemInventarioException extends \RuntimeException
{
    public function __construct(public readonly string $codigo, string $mensagem)
    {
        parent::__construct($mensagem);
    }
}
