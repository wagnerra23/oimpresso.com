<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Dto;

/**
 * Resultado de health check de driver (usado por
 * paymentgateway:health cron + alertas).
 *
 * Imutável.
 *
 * ADR 0170 — CONTRACTS.md §2.
 */
final class DriverHealth
{
    public function __construct(
        public readonly bool $ok,
        public readonly string $status,
        public readonly int $latencyMs,
        public readonly \DateTimeImmutable $checkedAt,
        public readonly ?string $errorMessage = null,
    ) {
    }
}
