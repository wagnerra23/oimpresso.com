<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Observers;

use Modules\Whatsapp\Entities\Message;
use Modules\Whatsapp\Events\OmnichannelMessageReceived;
use Modules\Whatsapp\Events\OmnichannelMessageSent;

/**
 * Observer da entidade `Message` (schema novo `messages` — ADR 0135).
 *
 * Dispara eventos `OmnichannelMessageReceived`/`OmnichannelMessageSent`
 * que o listener `PublishOmnichannelToCentrifugo` (US-WA-059) consome
 * pra publicar em `omnichannel:business:{id}` real-time.
 *
 * NÃO duplica com `WhatsappMessageObserver` — esse opera na entidade
 * legacy `WhatsappMessage` (tabela `whatsapp_messages`). Channel
 * Centrifugo distinto (`omnichannel:` vs `whatsapp:`) garante que UI
 * não recebe payload de schema cruzado.
 *
 * Append-only enforcement (PR futuro espelhando `WhatsappMessageObserver`)
 * fica fora desse escopo — esta observação é só pra eventing real-time.
 *
 * @see memory/decisions/0135-omnichannel-inbox-arquitetura.md
 */
class MessageObserver
{
    /**
     * Em created: dispara received pra inbound; sent pra outbound.
     */
    public function created(Message $message): void
    {
        if ($message->direction === Message::DIRECTION_INBOUND) {
            OmnichannelMessageReceived::dispatch($message);
            return;
        }

        if ($message->direction === Message::DIRECTION_OUTBOUND) {
            OmnichannelMessageSent::dispatch($message);
        }
    }

    /**
     * Em updated: re-publica outbound quando status muda pra
     * sent/failed (delivery flow do driver).
     *
     * Ignora inbound (inbound não tem fluxo de delivery do nosso lado)
     * e mudanças de outros campos que não `status`.
     */
    public function updated(Message $message): void
    {
        if ($message->direction !== Message::DIRECTION_OUTBOUND) {
            return;
        }

        if (! $message->wasChanged('status')) {
            return;
        }

        // Só republica em transições terminais relevantes pra UI
        // (sent = driver confirmou; failed = mostra erro).
        // Delivered/read são updates de baixa frequência — fica em
        // PR seguinte se precisar de feedback visual.
        if (! in_array($message->status, [Message::STATUS_SENT, Message::STATUS_FAILED], true)) {
            return;
        }

        OmnichannelMessageSent::dispatch($message);
    }
}
