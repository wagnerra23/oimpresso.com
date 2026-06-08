<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Whatsapp\Entities\WhatsappMessage;

/**
 * Disparado quando WhatsappMessage é criado em status=queued (antes de chamar Driver).
 *
 * Listeners típicos: LogConversation, OTel `whatsapp.message.queued`.
 *
 * @see memory/requisitos/Whatsapp/ARCHITECTURE.md §5
 */
class WhatsappMessageQueued
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly WhatsappMessage $message)
    {
    }
}
