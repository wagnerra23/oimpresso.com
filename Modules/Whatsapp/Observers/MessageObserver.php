<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Observers;

use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Conversation;
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
     * Em created: atualiza preview denormalizado (US-WA-072) e dispara
     * received pra inbound; sent pra outbound.
     *
     * Update do preview vem ANTES do dispatch dos events Centrifugo —
     * garante que listeners que recarregam Conversation pegam o estado
     * atualizado. NÃO toca `updated_at` da conv (forceFill->save) — esse
     * já é mantido pelos outros writes do controller.
     */
    public function created(Message $message): void
    {
        $this->syncConversationPreview($message);

        if ($message->direction === Message::DIRECTION_INBOUND) {
            OmnichannelMessageReceived::dispatch($message);
            return;
        }

        if ($message->direction === Message::DIRECTION_OUTBOUND) {
            OmnichannelMessageSent::dispatch($message);
        }
    }

    /**
     * US-WA-072 — escreve `last_message_preview` + `last_message_direction`
     * em `conversations`. Substitui o N+1 anterior em
     * `InboxController::convToListArray()`.
     *
     * - body=null → preview=null (tipos media-only sem texto)
     * - mb_substr truncate em 80 chars (UI mostra ~60 com ellipsis CSS)
     * - 2ª msg sobrescreve a 1ª (não acumula histórico — sempre o último)
     * - withoutGlobalScope ScopeByBusiness pq Observer roda em qualquer
     *   sessão (incluindo webhook sem session()->user.business_id setado).
     *   Filtro explícito por conversation_id já garante isolamento Tier 0.
     */
    protected function syncConversationPreview(Message $message): void
    {
        $preview = $message->body !== null
            ? mb_substr((string) $message->body, 0, 80)
            : null;

        Conversation::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->where('id', $message->conversation_id)
            ->update([
                'last_message_preview' => $preview,
                'last_message_direction' => $message->direction,
            ]);
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
