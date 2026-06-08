<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Listeners;

use Modules\Whatsapp\Events\WhatsappMessageSent;
use Modules\Whatsapp\Services\Centrifugo\CentrifugoPublisher;

/**
 * Quando WhatsappMessageSent dispara (driver retornou ok + status=sent),
 * publica no channel Centrifugo pra UI atualizar status delivery.
 */
class PublishMessageSentToCentrifugo
{
    public function __construct(private readonly CentrifugoPublisher $publisher)
    {
    }

    public function handle(WhatsappMessageSent $event): void
    {
        $message = $event->message;
        $this->publisher->publish(
            "whatsapp:business:{$message->business_id}",
            [
                'event' => 'message_sent',
                'conversation_id' => $message->conversation_id,
                'message_id' => $message->id,
                'provider_message_id' => $message->provider_message_id,
                'status' => $message->status,
            ],
        );
    }
}
