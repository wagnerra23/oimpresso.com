<?php

namespace Modules\RecurringBilling\Dto;

class BoletoResult
{
    public function __construct(
        public readonly string $nossoNumero,
        public readonly string $linhaDigitavel,
        public readonly string $codigoBarras,
        public readonly string $dataVencimento,
        public readonly float  $valor,
        public readonly ?string $pixQrCode = null,
        public readonly ?string $pdfUrl = null,
        public readonly ?string $pdfBase64 = null,
    ) {}

    public function toArray(): array
    {
        return [
            'nosso_numero'    => $this->nossoNumero,
            'linha_digitavel' => $this->linhaDigitavel,
            'codigo_barras'   => $this->codigoBarras,
            'data_vencimento' => $this->dataVencimento,
            'valor'           => $this->valor,
            'pix_qr_code'     => $this->pixQrCode,
            'pdf_url'         => $this->pdfUrl,
            'pdf_base64'      => $this->pdfBase64,
        ];
    }
}
