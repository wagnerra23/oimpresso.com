<?php

namespace Modules\NFSe\Exceptions;

class PrestadorNaoAutorizadoException extends NfseException
{
    public function __construct(string $cnpj = '', ?\Throwable $previous = null)
    {
        parent::__construct(
            'CNPJ não autorizado para emissão de NFSe neste município (código L1). Regularize o cadastro na prefeitura.',
            'PRESTADOR_NAO_AUTORIZADO',
            "cnpj={$cnpj}",
            $previous,
        );
    }
}
