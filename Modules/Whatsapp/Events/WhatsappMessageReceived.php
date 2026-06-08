<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Whatsapp\Entities\WhatsappMessage;

/**
 * Disparado quando ProcessIncomingWebhookJob cria nova WhatsappMessage inbound.
 *
 * Listeners típicos (Sprint 2-3):
 * - LogConversation
 * - DispatchToJanaBot (se bot_enabled=true) — Sprint 3
 * - Centrifugo publish whatsapp:business:{id} pra UI live
 * - OTel `whatsapp.message.received`
 *
 * @see memory/requisitos/Whatsapp/ARCHITECTURE.md §5
 */
class WhatsappMessageReceived
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly WhatsappMessage $message)
    {
    }
}
