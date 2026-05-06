<?php

namespace Modules\RecurringBilling\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InvoicePaid
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int    $businessId,
        public readonly string $invoiceRef,
        public readonly float  $valor,
        public readonly string $paidAt,
    ) {}
}
