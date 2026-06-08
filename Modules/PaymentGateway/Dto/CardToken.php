<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Dto;

/**
 * Token de cartão PCI-DSS — referência opaca no provedor.
 *
 * NUNCA contém PAN bruto. lastFour + brand + holderName são metadados
 * para UX (mascaramento) e auditoria. Imutável.
 *
 * ADR 0170 — CONTRACTS.md §2.
 */
final class CardToken
{
    public function __construct(
        public readonly string $token,
        public readonly string $brand,
        public readonly string $lastFour,
        public readonly string $holderName,
        public readonly string $expMonth,
        public readonly string $expYear,
    ) {
    }
}
