<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Dto;

/**
 * Resultado da emissão de cobrança — retornado pelo driver.
 *
 * Inclui artefatos PCI/LGPD-safe (linha digitável, EMV, QR path) +
 * payload bruto pra auditoria. Imutável.
 *
 * ADR 0170 — CONTRACTS.md §2.
 */
final class CobrancaEmitidaResult
{
    public function __construct(
        public readonly int $cobrancaId,
        public readonly string $gatewayExternalId,
        public readonly string $tipo,
        public readonly \DateTimeImmutable $emitidaEm,
        public readonly ?string $linhaDigitavel = null,
        public readonly ?string $codigoBarras = null,
        public readonly ?string $pixEmv = null,
        public readonly ?string $pixQrCodePath = null,
        public readonly ?string $boletoPdfUrl = null,
        public readonly ?string $nossoNumero = null,
        public readonly array $payloadGateway = [],
    ) {
    }
}
