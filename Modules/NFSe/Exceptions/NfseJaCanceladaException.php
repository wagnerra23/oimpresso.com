<?php

namespace Modules\NFSe\Exceptions;

class NfseJaCanceladaException extends NfseException
{
    public function __construct(string $numero = '', ?\Throwable $previous = null)
    {
        parent::__construct(
            "NFSe número {$numero} já foi cancelada anteriormente.",
            'JA_CANCELADA',
            "numero={$numero}",
            $previous,
        );
    }
}
