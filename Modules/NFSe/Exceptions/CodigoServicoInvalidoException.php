<?php

namespace Modules\NFSe\Exceptions;

class CodigoServicoInvalidoException extends NfseException
{
    public function __construct(string $codigo = '', ?\Throwable $previous = null)
    {
        parent::__construct(
            "Código de serviço LC 116 '{$codigo}' não habilitado para este município. Verifique em Configurações → NFSe.",
            'SERVICO_INVALIDO',
            "lc116={$codigo}",
            $previous,
        );
    }
}
