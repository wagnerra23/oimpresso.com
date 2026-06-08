<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\NfeBrasil\Models\NfeEmissao;

/**
 * Disparado pelo `NfeService` (e pelo Listener `EmitirNFeAoReceberPagamento`)
 * quando uma NF-e é autorizada pela SEFAZ (cStat 100/150).
 *
 * Consumidores típicos:
 *   - Notificação ao destinatário (e-mail com DANFE PDF)
 *   - Bridge `tax_rates` (ADR ARQ-0005)
 *   - Telemetria/dashboard de uso
 *   - Webhook outbound (ERP cliente integra)
 *
 * Não usar pra rejeições — ver `NFeRejeitada` (futuro).
 */
class NFeAutorizada
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly NfeEmissao $emissao,
    ) {}
}
