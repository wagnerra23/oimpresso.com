<?php

namespace Modules\NFSe\Exceptions;

class IssInvalidoException extends NfseException
{
    public function __construct(string $detalhe = '', ?\Throwable $previous = null)
    {
        parent::__construct(
            'Valor do ISS inválido (código E501). Verifique a alíquota configurada em Configurações → NFSe.',
            'ISS_INVALIDO',
            $detalhe,
            $previous,
        );
    }
}
