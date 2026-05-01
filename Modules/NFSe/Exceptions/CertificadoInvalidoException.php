<?php

namespace Modules\NFSe\Exceptions;

class CertificadoInvalidoException extends NfseException
{
    public function __construct(string $detalhe = '', ?\Throwable $previous = null)
    {
        parent::__construct(
            'Certificado A1 inválido ou expirado. Faça upload de um novo certificado em Configurações → NFSe.',
            'CERT_INVALIDO',
            $detalhe,
            $previous,
        );
    }
}
