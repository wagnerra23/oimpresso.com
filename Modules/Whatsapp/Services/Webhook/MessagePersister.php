<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Services\Webhook;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Entities\Conversation;
use Modules\Whatsapp\Entities\Message;
use Modules\Whatsapp\Services\Contacts\LidPhoneResolver;

/**
 * MessagePersister — extrai a lógica de Message::firstOrCreate compartilhada
 * entre webhook real-time (ChannelBaileysWebhookController::handleMessage)
 * e import histórico Baileys (US-WA-080 — ImportHistoryCommand).
 *
 * Mesma idempotência (firstOrCreate keyed em business_id + provider_message_id),
 * mesma extração de body/type/media meta, mesma sanitization MIME, mesma
 * normalização customer_external_id E.164. NÃO dispara DownloadMediaJob nem
 * Centrifugo publish — caller decide (real-time dispatcha; import histórico
 * normalmente NÃO dispara pra não afogar a fila com 90d × N msgs).
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093):
 * - business_id obrigatório no construtor (caller resolve via Channel)
 * - withoutGlobalScope explícito em queries (CLI/webhook sem session user)
 * - Comentário SUPERADMIN justifica o bypass
 *
 * @see Modules/Whatsapp/Http/Controllers/Api/ChannelBaileysWebhookController.php
 * @see Modules/Whatsapp/Console/Commands/ImportHistoryCommand.php
 * @see memory/decisions/0135-omnichannel-inbox-arquitetura.md
 */
class MessagePersister
{
    public function __construct(
        protected readonly Channel $channel,
    ) {
    }

    /**
     * Persiste 1 message vinda do daemon Baileys (webhook real-time OU
     * import histórico). Idempotente por (business_id, provider_message_id).
     *
     * @param  array  $data  Payload `data` do daemon, shape:
     *   - key.remoteJid, key.id, key.fromMe, key.senderPn (opcional)
     *   - message (proto raw)
     *   - push_name (opcional)
     *   - timestamp (unix ts, opcional — usado quando histórico, p/ created_at)
     *   - media_url / mime / size_bytes / duration_s / filename (opcional)
     * @param  bool  $bumpUnread  Real-time bump unread_count + last_inbound_at.
     *                            Import histórico = false pra não poluir Inbox.
     * @return PersistResult
     */
    public function persist(array $data, bool $bumpUnread = true): PersistResult
    {
        $msgKey = $data['key'] ?? [];
        $remoteJid = $msgKey['remoteJid'] ?? null;
        $senderPn = $msgKey['senderPn'] ?? null;
        $providerMessageId = $msgKey['id'] ?? null;
        $fromMe = (bool) ($msgKey['fromMe'] ?? false);
        $pushName = $data['push_name'] ?? null;
        $timestamp = isset($data['timestamp']) ? (int) $data['timestamp'] : null;

        if (! $remoteJid && ! $senderPn) {
            return PersistResult::skipped('no_remote_jid');
        }

        if (! $providerMessageId) {
            return PersistResult::skipped('no_provider_message_id');
        }

        // E.164 resolution (espelha ChannelBaileysWebhookController:138-146)
        $resolvedJid = ($senderPn && str_contains($senderPn, '@s.whatsapp.net'))
            ? $senderPn
            : $remoteJid;
        $rawNumber = preg_replace('/@.+$/', '', $resolvedJid);
        $customerExternalId = '+' . $rawNumber;

        // INCIDENT 2026-05-14 P0-3: history-sync path ANTES ignorava o
        // LidPhoneResolver — só o ChannelBaileysWebhookController real-time
        // consultava. Resultado: 81 msgs do Wagner (`remoteJid=<LID>@lid`
        // sem `senderPn`) caíram no contato errado pois MessagePersister
        // não trocava o LID pelo phone cacheado. Espelha o controller
        // [ChannelBaileysWebhookController:289-312] mantendo log auditoria
        // distinguindo history-sync vs real-time (signals diferentes).
        if (is_string($remoteJid) && str_contains($remoteJid, '@lid')) {
            $resolver = app(LidPhoneResolver::class);
            $senderPnHasPhone = is_string($senderPn) && str_contains($senderPn, '@s.whatsapp.net');

            if ($senderPnHasPhone) {
                $resolver->record($this->channel->business_id, $remoteJid, $senderPn);
            } else {
                $cachedPhone = $resolver->resolve($this->channel->business_id, $remoteJid);
                if ($cachedPhone !== null && $cachedPhone !== $customerExternalId) {
                    Log::info('[whatsapp.persister.lid_resolved_to_different_phone]', [
                        'business_id' => $this->channel->business_id,
                        'lid_prefix' => substr(preg_replace('/@.+$/', '', $remoteJid), 0, 6) . '...',
                        'is_history_sync' => $data['is_history_sync'] ?? false,
                    ]);
                    $customerExternalId = $cachedPhone;
                } elseif ($cachedPhone === null) {
                    $resolver->record($this->channel->business_id, $remoteJid, null);
                }
            }
        }

        // Body + type derivação (espelha controller:148-164 + caption fallback)
        $messageProto = $data['message'] ?? [];
        $body = $messageProto['conversation']
            ?? $messageProto['extendedTextMessage']['text']
            ?? null;

        $type = match (true) {
            isset($messageProto['imageMessage']) => 'image',
            isset($messageProto['documentMessage']) => 'document',
            isset($messageProto['audioMessage']) => 'audio',
            isset($messageProto['videoMessage']) => 'video',
            isset($messageProto['stickerMessage']) => 'image',
            isset($messageProto['locationMessage']) => 'location',
            isset($messageProto['contactMessage']) => 'contacts',
            default => 'text',
        };

        // Media meta — preferindo flat daemon, fallback proto aninhado
        $mediaProto = $messageProto['imageMessage']
            ?? $messageProto['audioMessage']
            ?? $messageProto['videoMessage']
            ?? $messageProto['documentMessage']
            ?? $messageProto['stickerMessage']
            ?? null;

        $mediaUrl = $data['media_url'] ?? null;
        $mediaMimeRaw = $data['mime']
            ?? ($mediaProto['mimetype'] ?? null);
        $mediaMime = $mediaMimeRaw
            ? trim(explode(';', (string) $mediaMimeRaw, 2)[0])
            : null;
        $mediaSize = isset($data['size_bytes'])
            ? (int) $data['size_bytes']
            : (isset($mediaProto['fileLength']) ? (int) $mediaProto['fileLength'] : null);
        $mediaDuration = isset($data['duration_s'])
            ? (int) $data['duration_s']
            : (isset($mediaProto['seconds']) ? (int) $mediaProto['seconds'] : null);
        $mediaFilename = $data['filename']
            ?? ($mediaProto['fileName'] ?? null);

        // Caption fallback (espelha controller:209-214)
        if ($body === null) {
            $body = $messageProto['imageMessage']['caption']
                ?? $messageProto['videoMessage']['caption']
                ?? $messageProto['documentMessage']['caption']
                ?? null;
        }

        // Filtro msgs protocol vazias (espelha controller:227-233)
        if ($body === null && $type === 'text') {
            return PersistResult::skipped('protocol_msg_ignored');
        }

        // SUPERADMIN: CLI sem session user — bypass global scope justificado (ADR 0093)
        $existingConv = Conversation::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $this->channel->business_id)
            ->where('channel_id', $this->channel->id)
            ->where('customer_external_id', $customerExternalId)
            ->first();

        // Drop inbound de blocked (espelha controller:246-256)
        if ($existingConv && $existingConv->is_blocked && ! $fromMe) {
            return PersistResult::skipped('inbound_dropped_blocked', $existingConv);
        }

        $conversation = Conversation::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->firstOrCreate(
                [
                    'business_id' => $this->channel->business_id,
                    'channel_id' => $this->channel->id,
                    'customer_external_id' => $customerExternalId,
                ],
                [
                    'contact_name' => $pushName ?: $customerExternalId,
                    'status' => 'open',
                    'bot_handling' => false,
                    'last_inbound_at' => $bumpUnread ? now() : null,
                    'last_message_at' => $bumpUnread ? now() : null,
                    'unread_count' => 0,
                ]
            );

        // Atualiza contact_name se push_name veio E o atual era só E.164
        if ($pushName && $conversation->contact_name === $customerExternalId) {
            $conversation->contact_name = $pushName;
            $conversation->save();
        }

        // SUPERADMIN: ADR 0093 — webhook/CLI sem session user
        $message = Message::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->firstOrCreate(
                [
                    'business_id' => $this->channel->business_id,
                    'provider_message_id' => $providerMessageId,
                ],
                [
                    'conversation_id' => $conversation->id,
                    'direction' => $fromMe ? 'outbound' : 'inbound',
                    'provider' => $this->channel->type,
                    'type' => $type,
                    'body' => $body,
                    'payload' => $data,
                    'status' => $fromMe ? 'sent' : 'received',
                    'sender_kind' => $fromMe ? 'human' : null,
                    'media_url' => $mediaUrl,
                    'media_mime' => $mediaMime,
                    'media_size_bytes' => $mediaSize,
                    'media_duration_s' => $mediaDuration,
                    'media_filename' => $mediaFilename,
                ]
            );

        // Para import histórico: tenta backdate `created_at` pro timestamp
        // real da mensagem. Eloquent ignora created_at no $fillable do
        // Message — usa UPDATE direto sem timestamps. Só backdates em ROW
        // recém criada pra não reescrever histórico já preservado.
        if ($message->wasRecentlyCreated && $timestamp !== null && ! $bumpUnread) {
            $createdAt = \Carbon\Carbon::createFromTimestamp($timestamp);
            Message::query()
                ->withoutGlobalScope(ScopeByBusiness::class)
                ->where('id', $message->id)
                ->update([
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);
            $message->created_at = $createdAt;
            $message->updated_at = $createdAt;
        }

        // Real-time: bump unread + last_*_at. Import histórico: skip.
        if ($message->wasRecentlyCreated && $bumpUnread) {
            $conversation->forceFill([
                'last_message_at' => now(),
                'last_inbound_at' => $fromMe ? $conversation->last_inbound_at : now(),
                'last_outbound_at' => $fromMe ? now() : $conversation->last_outbound_at,
                'unread_count' => $fromMe ? $conversation->unread_count : $conversation->unread_count + 1,
            ])->save();
        }

        return $message->wasRecentlyCreated
            ? PersistResult::created($conversation, $message)
            : PersistResult::duplicate($conversation, $message);
    }
}

/**
 * Resultado tipado da operação — caller decide o que fazer (dispatch
 * DownloadMediaJob, publish Centrifugo, log stats etc).
 */
class PersistResult
{
    public function __construct(
        public readonly string $outcome, // created | duplicate | skipped
        public readonly string $note,
        public readonly ?Conversation $conversation = null,
        public readonly ?Message $message = null,
    ) {
    }

    public static function created(Conversation $c, Message $m): self
    {
        return new self('created', 'message_persisted', $c, $m);
    }

    public static function duplicate(Conversation $c, Message $m): self
    {
        return new self('duplicate', 'message_duplicate_ignored', $c, $m);
    }

    public static function skipped(string $note, ?Model $conv = null): self
    {
        return new self('skipped', $note, $conv instanceof Conversation ? $conv : null, null);
    }

    public function wasCreated(): bool
    {
        return $this->outcome === 'created';
    }

    public function wasDuplicate(): bool
    {
        return $this->outcome === 'duplicate';
    }

    public function wasSkipped(): bool
    {
        return $this->outcome === 'skipped';
    }
}
