<?php

namespace Modules\NFSe\DTO;

final class NfseResultado
{
    public function __construct(
        public readonly bool    $sucesso,
        public readonly ?string $numero = null,
        public readonly ?string $protocolo = null,
        public readonly ?string $codigoVerificacao = null,
        public readonly ?string $pdfUrl = null,
        public readonly ?string $xmlRetorno = null,
        public readonly ?string $erroMensagem = null,
        public readonly ?string $erroCodigo = null,
    ) {}

    public static function sucesso(
        string $numero,
        string $protocolo,
        ?string $codigoVerificacao = null,
        ?string $pdfUrl = null,
        ?string $xmlRetorno = null,
    ): self {
        return new self(true, $numero, $protocolo, $codigoVerificacao, $pdfUrl, $xmlRetorno);
    }

    public static function erro(string $mensagem, string $codigo = ''): self
    {
        return new self(false, erroMensagem: $mensagem, erroCodigo: $codigo);
    }
}
