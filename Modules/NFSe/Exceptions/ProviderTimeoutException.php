<?php

namespace Modules\NFSe\Exceptions;

class ProviderTimeoutException extends NfseException
{
    public function __construct(int $tentativa = 1, ?\Throwable $previous = null)
    {
        parent::__construct(
            'Prefeitura não respondeu a tempo. A nota está em processamento — aguarde e consulte o status.',
            'TIMEOUT',
            "tentativa={$tentativa}",
            $previous,
        );
    }
}
