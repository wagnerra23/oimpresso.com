<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Cobrança passou da data de vencimento sem pagamento confirmado.
 *
 * Disparado por job diário (Onda 3+) que varre cobrancas WHERE
 * status='emitida' AND vencimento < today.
 *
 * Listeners esperados (ADR 0170 CONTRACTS.md §3):
 *   - RecurringBilling\Listeners\SmartRetryRecurringInvoice
 *   - RecurringBilling\Listeners\SubscriptionOverdue
 *   - Core\Listeners\NotifyContactPaymentOverdue
 */
class CobrancaVencida
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly int $cobrancaId,
        public readonly int $businessId,
        public readonly int $diasVencido,
        public readonly \DateTimeImmutable $vencimentoOriginal,
        public readonly \DateTimeImmutable $occurredAt,
        public readonly ?string $origemType = null,
        public readonly ?int $origemId = null,
    ) {
    }
}
