<?php

namespace Modules\NFSe\Exceptions;

use RuntimeException;

class NfseException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $codigo = '',
        public readonly ?string $detalhe = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
