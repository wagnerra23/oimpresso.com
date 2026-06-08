<?php

namespace Modules\NFSe\Exceptions;

class TomadorInvalidoException extends NfseException
{
    public function __construct(string $detalhe = '', ?\Throwable $previous = null)
    {
        parent::__construct(
            'CNPJ ou CPF do tomador inválido. Verifique o cadastro do cliente.',
            'TOMADOR_INVALIDO',
            $detalhe,
            $previous,
        );
    }
}
