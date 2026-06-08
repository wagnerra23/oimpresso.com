<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Listeners;

use Modules\Whatsapp\Events\WhatsappMessageReceived;
use Modules\Whatsapp\Services\Centrifugo\CentrifugoPublisher;

/**
 * Quando WhatsappMessageReceived dispara, publica no channel Centrifugo
 * `whatsapp:business:{id}` pra UI atualizar live (sem polling).
 *
 * Síncrono (não-queued) porque é fast-path — falha silenciosa em
 * CentrifugoPublisher se houver problema (UI tem reload manual fallback).
 */
class PublishMessageReceivedToCentrifugo
{
    public function __construct(private readonly CentrifugoPublisher $publisher)
    {
    }

    public function handle(WhatsappMessageReceived $event): void
    {
        $message = $event->message;
        $this->publisher->publish(
            "whatsapp:business:{$message->business_id}",
            [
                'event' => 'message_received',
                'conversation_id' => $message->conversation_id,
                'message_id' => $message->id,
                'preview' => mb_substr((string) $message->body, 0, 120),
                'created_at' => $message->created_at?->toIso8601String(),
            ],
        );
    }
}
