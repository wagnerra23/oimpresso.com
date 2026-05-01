<?php

namespace Modules\NFSe\Exceptions;

class RpsDuplicadoException extends NfseException
{
    public function __construct(string $rpsNumero = '', ?\Throwable $previous = null)
    {
        parent::__construct(
            "RPS número {$rpsNumero} já foi enviado e aceito pela prefeitura. Use a nota original ou emita um novo RPS.",
            'RPS_DUPLICADO',
            "rps_numero={$rpsNumero}",
            $previous,
        );
    }
}
