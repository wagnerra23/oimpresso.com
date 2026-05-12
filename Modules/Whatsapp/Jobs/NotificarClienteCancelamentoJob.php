<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * US-SELL-034 — Notifica cliente do cancelamento de uma venda via WhatsApp.
 *
 * Disparado pelo side-effect CancelarVendaCascade após cancelar NFes +
 * liberar reservas. Best-effort: falha de notificação não desfaz
 * cancelamento (já estaria em stage=cancelled com history audit).
 *
 * Multi-tenant Tier 0 (ADR 0093): $businessId no constructor.
 *
 * **Implementação WhatsApp**: stub atual loga. Wagner amarra
 * SendWhatsappMessageJob com template canônico em US separada
 * (CASCADE-NOTIFY-001 a criar).
 */
class NotificarClienteCancelamentoJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public int $backoff = 30;

    public function __construct(
        public readonly int $businessId,
        public readonly int $transactionId,
        public readonly string $motivo,
    ) {}

    public function handle(): void
    {
        Log::info('NotificarClienteCancelamentoJob: handler invoked (stub)', [
            'business_id' => $this->businessId,
            'transaction_id' => $this->transactionId,
            'motivo' => $this->motivo,
            'todo' => 'compor mensagem + dispatch SendWhatsappMessageJob com template "Sua venda #X foi cancelada — motivo: Y"',
        ]);

        // TODO US CASCADE-NOTIFY-001:
        //   1. Resolver contact via transaction.contact_id
        //   2. Verificar consentimento Whatsapp (LGPD)
        //   3. Renderizar template canônico (Modules/Whatsapp/Templates/)
        //   4. dispatch SendWhatsappMessageJob com phone + body
        //   5. Fallback email se contact sem phone
    }
}
