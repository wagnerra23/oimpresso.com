<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Cobrança foi paga — evento canônico do módulo.
 *
 * Listeners esperados em ordem (ADR 0170 CONTRACTS.md §3):
 *   1. RecurringBilling\Listeners\MarkInvoicePaid
 *   2. NFSe\Listeners\EmitirNfseFromCobrancaPaga   ⭐ US-RB-044 irrevogável
 *   3. Core\Listeners\CreateAccountTransactionFromCobranca
 *   4. Core\Listeners\MarkSalePaid
 *   5. Superadmin\Listeners\RenovarLicencaTenant   (Onda 5)
 */
class CobrancaPaga
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly int $cobrancaId,
        public readonly int $businessId,
        public readonly int $valorPagoCentavos,
        public readonly \DateTimeImmutable $pagaEm,
        public readonly string $formaPagamento,
        public readonly \DateTimeImmutable $occurredAt,
        public readonly ?string $payerCpfCnpj = null,
        public readonly ?string $origemType = null,
        public readonly ?int $origemId = null,
    ) {
    }
}
