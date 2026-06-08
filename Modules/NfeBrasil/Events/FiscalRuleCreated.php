<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\NfeBrasil\Models\NfeFiscalRule;

/**
 * ADR ARQ-0005 — disparado quando uma FiscalRule é criada.
 * Consumido pelo Listener `SyncFiscalRuleToTaxRate` que cria a TaxRate auto.
 */
class FiscalRuleCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly NfeFiscalRule $rule) {}
}
