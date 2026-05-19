<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Dto;

/**
 * Status consultado direto no gateway (bypass cache local).
 *
 * Útil pra reconciliação manual quando webhook atrasou ou foi perdido.
 * Imutável.
 *
 * ADR 0170 — CONTRACTS.md §2.
 */
final class CobrancaStatus
{
    public function __construct(
        public readonly string $status,
        public readonly ?\DateTimeImmutable $pagaEm = null,
        public readonly ?int $valorPagoCentavos = null,
        public readonly ?string $formaPagamento = null,
        public readonly ?string $payerCpfCnpj = null,
        public readonly array $payloadGateway = [],
    ) {
    }
}
