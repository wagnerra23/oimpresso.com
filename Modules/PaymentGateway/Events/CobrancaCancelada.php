<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Cobrança foi cancelada antes de pagamento (boleto cancelado, PIX
 * mandato revogado).
 *
 * Distinto de refund (que é estorno de cobrança JÁ paga).
 *
 * Listeners esperados (ADR 0170 CONTRACTS.md §3):
 *   - RecurringBilling\Listeners\CancelInvoice
 *   - Core\Listeners\MarkSaleCancelled
 */
class CobrancaCancelada
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly int $cobrancaId,
        public readonly int $businessId,
        public readonly string $motivo,
        public readonly int $canceladoPor,
        public readonly \DateTimeImmutable $occurredAt,
        public readonly ?string $origemType = null,
        public readonly ?int $origemId = null,
    ) {
    }
}
