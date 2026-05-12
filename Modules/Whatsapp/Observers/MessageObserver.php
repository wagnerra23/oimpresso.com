<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Observers;

use Illuminate\Support\Facades\Log;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Conversation;
use Modules\Whatsapp\Entities\Message;
use Modules\Whatsapp\Events\OmnichannelMessageReceived;
use Modules\Whatsapp\Events\OmnichannelMessageSent;
use Modules\Whatsapp\Jobs\DownloadMediaJob;

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

        // Guardião 6 camadas — Camada 1: hard gate Observer auto-dispatch.
        // Defense-in-depth: webhook controller já dispara DownloadMediaJob no
        // ChannelBaileysWebhookController, mas observer NÃO esquece de
        // disparar mesmo se entry point novo (Z-API, Meta Cloud, MWS, replay
        // manual) esquecer. ADR 0093 Tier 0 — `business_id` global scope OK
        // pq Observer roda no contexto da Eloquent write.
        $this->maybeDispatchMediaDownload($message);

        if ($message->direction === Message::DIRECTION_INBOUND) {
            OmnichannelMessageReceived::dispatch($message);
            return;
        }

        if ($message->direction === Message::DIRECTION_OUTBOUND) {
            OmnichannelMessageSent::dispatch($message);
        }
    }

    /**
     * Guardião 6 camadas — Camada 1.
     *
     * Auto-dispatch DownloadMediaJob quando:
     *   - type IN (image|audio|video|document|sticker) — Message::MEDIA_TYPES
     *   - media_mime != null (sabemos que tem mídia anexa)
     *   - media_url === null (ainda não baixou)
     *   - media_download_status != failed_permanent (não retentar permanently failed)
     *
     * Hostinger queue=sync → Job roda imediato (síncrono no mesmo request).
     * Em qualquer outro driver de fila, Job entra na queue normal.
     *
     * Idempotência: DownloadMediaJob no início já incrementa attempts e
     * marca status='downloading' — re-entry double dispatch (Observer +
     * webhook controller) só desperdiça 1 call HTTP, não corrompe estado.
     */
    protected function maybeDispatchMediaDownload(Message $message): void
    {
        if (! in_array($message->type, Message::MEDIA_TYPES, true)) {
            return; // text/template/sistema — sem mídia
        }

        if ($message->media_mime === null) {
            return; // sem mídia anexa (msg media-type sem payload = anomalia, log debug)
        }

        if ($message->media_url !== null) {
            return; // já baixou (outbound upload local ou re-entry pós success)
        }

        if ($message->media_download_status === Message::DOWNLOAD_STATUS_FAILED_PERMANENT) {
            return; // cap atingido — não disparar mais (só via backfill --force-failed)
        }

        // Tier 0 (ADR 0093) — Job constructor recebe business_id explícito.
        // session() não funciona em fila — passar via SerializesModels.
        try {
            DownloadMediaJob::dispatch(
                $message->business_id,
                $message->id,
                // sourceUrl + expectedMime: lidos do payload no próprio Job
                // (Camada 3 chama daemon decrypt-url com mediaKey).
                '',
                (string) $message->media_mime,
            );
        } catch (\Throwable $e) {
            // Falha pra dispatchar (ex: queue connection down) NÃO derruba o
            // webhook — registro fica pending pra Camada 4 (retry hourly).
            Log::warning('[guardiao_midia] observer dispatch falhou', [
                'message_id' => $message->id,
                'business_id' => $message->business_id,
                'error' => mb_substr($e->getMessage(), 0, 200),
            ]);
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
