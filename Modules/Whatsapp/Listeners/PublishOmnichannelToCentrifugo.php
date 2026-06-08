<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Listeners;

use Modules\Whatsapp\Events\OmnichannelMessageReceived;
use Modules\Whatsapp\Events\OmnichannelMessageSent;
use Modules\Whatsapp\Services\Centrifugo\CentrifugoPublisher;

/**
 * Publica no channel `omnichannel:business:{businessId}` quando uma
 * `Message` inbound/outbound do schema novo é criada/atualizada.
 *
 * Handle único pros 2 eventos (received + sent) — diferencia via
 * `type` no payload (`message.received` | `message.sent`). UI no
 * `/atendimento/inbox` faz partial reload conforme `type` (US-WA-059).
 *
 * Síncrono (não-queued) — falha silenciosa em CentrifugoPublisher.
 * Channel é segregado por business_id (Tier 0 ADR 0093): biz=99 nunca
 * vaza pra biz=1, porque cada cliente subscrita SÓ no seu channel via
 * token Centrifugo emitido pelo `InboxController` (CentrifugoTokenIssuer).
 *
 * Coexiste com `PublishMessageReceivedToCentrifugo`/`PublishMessageSentToCentrifugo`
 * que publicam em `whatsapp:business:{id}` pro schema legacy. Canais
 * separados → não duplicam UI.
 *
 * @see memory/decisions/0058-reverb-substituido-por-centrifugo-frankenphp.md
 * @see memory/decisions/0135-omnichannel-inbox-arquitetura.md
 */
class PublishOmnichannelToCentrifugo
{
    public function __construct(private readonly CentrifugoPublisher $publisher)
    {
    }

    public function handle(OmnichannelMessageReceived|OmnichannelMessageSent $event): void
    {
        $message = $event->message;
        $type = $event instanceof OmnichannelMessageReceived
            ? 'message.received'
            : 'message.sent';

        $this->publisher->publish(
            "omnichannel:business:{$message->business_id}",
            [
                'type' => $type,
                'conversation_id' => $message->conversation_id,
                'channel_id' => $message->conversation?->channel_id,
                'message' => [
                    'id' => $message->id,
                    'direction' => $message->direction,
                    'provider' => $message->provider,
                    'type' => $message->type,
                    'body' => $message->body !== null
                        ? mb_substr((string) $message->body, 0, 240)
                        : null,
                    'status' => $message->status,
                    'failed_reason' => $message->failed_reason,
                    'sender_kind' => $message->sender_kind,
                    'created_at' => $message->created_at?->toIso8601String(),
                ],
            ],
        );
    }
}
