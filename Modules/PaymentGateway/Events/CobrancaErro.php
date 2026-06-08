<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Erro durante tentativa de emissão/cancelamento/refund.
 *
 * Payload `context` DEVE ser sanitizado (sem PII bruta) — listeners
 * usam para audit + Otel spans + retry-strategy.
 *
 * Listeners esperados (ADR 0170 CONTRACTS.md §3):
 *   - Otel\Listeners\RecordCobrancaErro
 *   - PaymentGateway\Listeners\AlertHealthCheck
 *   - RecurringBilling\Listeners\StopRetryIfCredentialError
 */
class CobrancaErro
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly int $businessId,
        public readonly string $exception,
        public readonly string $message,
        public readonly string $driverKey,
        public readonly array $context,
        public readonly \DateTimeImmutable $occurredAt,
        public readonly ?int $cobrancaId = null,
        public readonly ?string $origemType = null,
        public readonly ?int $origemId = null,
    ) {
    }
}
