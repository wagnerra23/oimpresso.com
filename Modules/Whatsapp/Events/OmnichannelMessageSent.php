<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Whatsapp\Entities\Message;

/**
 * Disparado quando `Message` outbound é criada ou tem status mudado
 * para `sent`/`failed` no schema omnichannel (ADR 0135).
 *
 * Sucessor de `WhatsappMessageSent` (schema legacy). Coexistem em
 * channels Centrifugo separados — não duplicam UI.
 *
 * @see memory/decisions/0135-omnichannel-inbox-arquitetura.md
 */
class OmnichannelMessageSent
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Message $message)
    {
    }
}
