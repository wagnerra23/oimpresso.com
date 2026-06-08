<?php

declare(strict_types=1);

namespace Modules\RecurringBilling\Dto;

/**
 * Resultado de criar uma cobrança PIX imediata (`PUT /cobranca/v3/cob/{txid}`).
 *
 * `txid` é o ID do recebedor (26-35 alfanuméricos). `pixCopiaECola` é a
 * string que vai pro QR Code (BR Code BACEN). `qrcodeBase64` é PNG decodificável
 * via `data:image/png;base64,...` na UI.
 *
 * @see US-RB-047
 */
class PixCobResult
{
    public function __construct(
        public readonly string $txid,
        public readonly string $status,
        public readonly float  $valor,
        public readonly string $pixCopiaECola,
        public readonly ?string $qrcodeBase64,
        public readonly int    $expiracaoSegundos,
        public readonly array  $raw,
    ) {}

    public function toArray(): array
    {
        return [
            'txid'                => $this->txid,
            'status'              => $this->status,
            'valor'               => $this->valor,
            'pix_copia_e_cola'    => $this->pixCopiaECola,
            'qrcode_base64'       => $this->qrcodeBase64,
            'expiracao_segundos'  => $this->expiracaoSegundos,
        ];
    }
}
