<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * ADR ARQ-0005 — disparado quando uma FiscalRule é deletada.
 * Listener remove a TaxRate vinculada (cascade do FK também removeria,
 * mas event garante side-effects extras como audit log).
 */
class FiscalRuleDeleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $ruleId,
        public readonly int $businessId,
    ) {}
}
