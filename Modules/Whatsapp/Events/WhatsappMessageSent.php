<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Whatsapp\Entities\WhatsappMessage;

/**
 * Disparado quando Driver retornou sucesso e WhatsappMessage está em status=sent.
 *
 * Listeners: LogConversation, Centrifugo publish whatsapp:business:{id},
 * OTel `whatsapp.message.sent`.
 *
 * @see memory/requisitos/Whatsapp/ARCHITECTURE.md §5
 */
class WhatsappMessageSent
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly WhatsappMessage $message)
    {
    }
}
