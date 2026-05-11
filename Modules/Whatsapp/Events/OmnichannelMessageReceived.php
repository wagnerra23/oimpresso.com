<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Whatsapp\Entities\Message;

/**
 * Disparado quando nova `Message` inbound é criada no schema omnichannel
 * (ADR 0135 — table `messages`). Sucessor de `WhatsappMessageReceived`
 * que ainda existe pro schema legacy (`whatsapp_messages`).
 *
 * Coexiste com `WhatsappMessageReceived` durante migração — channel
 * Centrifugo diferente (`omnichannel:business:{id}` vs `whatsapp:business:{id}`).
 *
 * Listener canônico: `PublishOmnichannelToCentrifugo` (US-WA-059).
 *
 * @see memory/decisions/0135-omnichannel-inbox-arquitetura.md
 */
class OmnichannelMessageReceived
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Message $message)
    {
    }
}
