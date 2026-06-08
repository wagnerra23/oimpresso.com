<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\NfeBrasil\Models\NfeEmissao;

/**
 * Disparado pelo `NfeService` (e pelo Listener `EmitirNfceAoFinalizarVenda`)
 * quando uma NFC-e (modelo 65, venda B2C balcão) é autorizada pela SEFAZ
 * (cStat 100/150).
 *
 * **Por que separado de `NFeAutorizada`** (modelo 55):
 *   - NFe 55 vem de Invoice (recorrência) — destinatário em `rb_invoices.contact_id`
 *   - NFC-e 65 vem de Transaction (venda POS) — destinatário em `transactions.contact_id`
 *   - Listeners precisam resolver email por caminhos diferentes; um event por modelo
 *     deixa o despacho explícito e evita branchear "if modelo === 65" em cada handler
 *   - Webhooks/dashboards podem querer filtrar por tipo (B2B vs B2C)
 *
 * Consumidores típicos:
 *   - Notificação ao consumidor (e-mail com DANFE NFC-e)
 *   - Dashboard de vendas autorizadas (separar B2C de B2B)
 *   - Webhook outbound (integração ERP do cliente)
 *   - Broadcast Reverb `business.{id}.nfe-status` (US-NFE-002 AC #5)
 *
 * Não usar pra rejeições — ver `NFCeRejeitada` (futuro).
 */
class NFCeAutorizada
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly NfeEmissao $emissao,
    ) {}
}
