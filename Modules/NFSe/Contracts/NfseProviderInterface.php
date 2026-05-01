<?php

namespace Modules\NFSe\Contracts;

use Modules\NFSe\DTO\NfseEmissaoPayload;
use Modules\NFSe\DTO\NfseResultado;

interface NfseProviderInterface
{
    /**
     * Emite uma NFSe. Lança NfseException em caso de erro da prefeitura.
     */
    public function emitir(NfseEmissaoPayload $payload): NfseResultado;

    /**
     * Consulta o status de uma NFSe pelo número do protocolo.
     */
    public function consultar(string $protocolo): NfseResultado;

    /**
     * Cancela uma NFSe emitida.
     */
    public function cancelar(string $numero, string $motivo): bool;
}
