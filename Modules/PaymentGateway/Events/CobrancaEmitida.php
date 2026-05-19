<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Cobrança foi emitida no gateway (boleto gerado, PIX cob criado, etc).
 *
 * Listeners esperados (ADR 0170 CONTRACTS.md §3):
 *   - RecurringBilling\Listeners\MarkInvoiceCobrancaEmitida
 *   - Core\Listeners\MarkSalePaymentPending
 *
 * Onda 1 dispara em modo shadow (sem listeners reais ainda).
 */
class CobrancaEmitida
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly int $cobrancaId,
        public readonly int $businessId,
        public readonly string $tipo,
        public readonly int $valorCentavos,
        public readonly \DateTimeImmutable $vencimento,
        public readonly \DateTimeImmutable $occurredAt,
        public readonly ?string $origemType = null,
        public readonly ?int $origemId = null,
    ) {
    }
}
