<?php

declare(strict_types=1);

namespace Modules\Fisio\Services\Questionarios;

use InvalidArgumentException;

/**
 * Resposta de questionário fora do formato do instrumento (item faltando, valor fora da faixa).
 * `campo` é a chave de validação (`respostas.itens.3`), para o controller devolver 422 no campo certo.
 */
final class RespostaInvalidaException extends InvalidArgumentException
{
    public function __construct(public readonly string $campo, string $mensagem)
    {
        parent::__construct($mensagem);
    }
}
