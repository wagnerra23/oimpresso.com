<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Util\OtelHelper;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\WhatsappBusinessConfig;
use Modules\Whatsapp\Entities\WhatsappBusinessPhone;
use Modules\Whatsapp\Entities\WhatsappConversation;
use Modules\Whatsapp\Entities\WhatsappMessage;
use Modules\Whatsapp\Events\WhatsappMessageReceived;

/**
 * Processa payload de webhook (Meta/Z-API/Baileys), normaliza pra
 * WhatsappMessage append-only.
 *
 * **Driver-agnóstico:** o controller já validou assinatura e identificou
 * `business_id` + `provider`. Aqui só normalizamos o payload de cada
 * provider pra estrutura comum.
 *
 * **Multi-números (ADR 0117 — US-WA-040):**
 * Aceita `?int $whatsappBusinessPhoneId` opcional. Quando set, escreve
 * `whatsapp_business_phone_id` em `WhatsappConversation` e `WhatsappMessage`
 * inbound — UI Inbox filtra conversas por phone do user. Quando NULL
 * (legacy/coexistência), comportamento original sem phone_id (data migration
 * PR 1 já preencheu phone_id em conversations existentes).
 *
 * **Idempotência:** UNIQUE em `provider_message_id` impede duplicata.
 * Se webhook chegar 2× com mesmo wamid/messageId, segunda vez é no-op.
 *
 * **Tier 0 (ADR 0093):** `$businessId` no constructor; queries com
 * `withoutGlobalScope` + filtro explícito.
 *
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-011, US-WA-040
 * @see memory/requisitos/Whatsapp/ARCHITECTURE.md §3.2
 */
class ProcessIncomingWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;

    /**
     * @param  array<string, mixed>  $payload  Raw payload do provider (já validado HMAC/Client-Token)
     */
    public function __construct(
        public readonly int $businessId,
        public readonly string $provider, // meta_cloud|zapi|baileys
        public readonly array $payload,
        public readonly ?int $whatsappBusinessPhoneId = null,
    ) {
        $this->onQueue(config('whatsapp.queue', 'whatsapp'));
    }

    public function backoff(): array
    {
        return [30, 90, 270];
    }

    public function handle(): void
    {
        OtelHelper::span('whatsapp.webhook.process_incoming', [
            'business_id' => $this->businessId,
            'provider' => $this->provider,
            'phone_id' => $this->whatsappBusinessPhoneId,
        ], fn () => $this->doHandle());
    }

    private function doHandle(): void
    {
        Log::info('whatsapp.webhook.process_incoming.started', [
            'business_id' => $this->businessId,
            'provider' => $this->provider,
            'phone_id' => $this->whatsappBusinessPhoneId,
        ]);

        // Resolve phone se fornecido (defensive Tier 0); senão fallback config legacy
        $phone = null;
        if ($this->whatsappBusinessPhoneId !== null) {
            // SUPERADMIN: job webhook sem session — business_id do constructor (validado pelo middleware HMAC)
            $phone = WhatsappBusinessPhone::query()
                ->withoutGlobalScope(ScopeByBusiness::class)
                ->where('business_id', $this->businessId)
                ->where('id', $this->whatsappBusinessPhoneId)
                ->first();
        }

        if ($phone === null) {
            // ADR 0135 + 0204: providers Channel-based (whatsmeow) NÃO usam
            // whatsapp_business_configs legacy. Skip lookup — job processa
            // direto via Channel (resolveChannel no upsertMessage).
            // Legacy providers (zapi/meta_cloud com phones table) ainda passam
            // phone_id no constructor; whatsmeow nunca passa.
            if ($this->provider !== 'whatsmeow') {
                $config = WhatsappBusinessConfig::query()
                    ->withoutGlobalScope(ScopeByBusiness::class)
                    ->where('business_id', $this->businessId)
                    ->first();
                if ($config === null) {
                    Log::warning('whatsapp.webhook.process_incoming.no_legacy_config', [
                        'business_id' => $this->businessId,
                        'provider' => $this->provider,
                    ]);
                    return; // sem config legacy → não processa (defensive)
                }
            }
            $resolvedPhoneId = null;
        } else {
            $resolvedPhoneId = $phone->id;
        }

        $extracted = $this->extractMessages();
        if (empty($extracted)) {
            return;
        }

        foreach ($extracted as $msg) {
            // ADR 0135 + 0204: provider=whatsmeow usa schema novo (channels/conversations/messages)
            // Caixa Unificada v4. Legacy providers continuam via whatsapp_business_configs.
            if ($this->provider === 'whatsmeow') {
                $this->upsertMessageWhatsmeow($msg);
            } else {
                $this->upsertMessage($this->businessId, $resolvedPhoneId, $msg);
            }
        }
    }

    /**
     * Upsert mensagem no schema NOVO (channels/conversations/messages) — ADR 0135 + 0204.
     *
     * Resolve channel via instanceName no payload (whatsmeowUserName match).
     * Cria conversation + message via DB::table direto (não usa WhatsappMessage
     * model legacy que aponta pra whatsapp_messages table vazia).
     */
    private function upsertMessageWhatsmeow(array $msg): void
    {
        $providerMessageId = (string) ($msg['provider_message_id'] ?? '');
        if ($providerMessageId === '') {
            return;
        }

        // Idempotência cross-tenant — provider_message_id UNIQUE global
        $existing = \DB::table('messages')
            ->where('provider_message_id', $providerMessageId)
            ->first();
        if ($existing !== null) {
            return;
        }

        // Resolve channel via instanceName do payload outer (passado pelo controller)
        // OU via primeiro channel whatsmeow ativo do business (fallback).
        $instanceName = (string) ($this->payload['instanceName'] ?? '');
        $channel = null;
        if ($instanceName !== '') {
            $channel = \Modules\Whatsapp\Entities\Channel::query()
                ->withoutGlobalScope(ScopeByBusiness::class)
                ->where('business_id', $this->businessId)
                ->where('type', \Modules\Whatsapp\Entities\Channel::TYPE_WHATSAPP_WHATSMEOW)
                ->get()
                ->first(fn ($ch) => $ch->whatsmeowUserName() === $instanceName);
        }
        if ($channel === null) {
            $channel = \Modules\Whatsapp\Entities\Channel::query()
                ->withoutGlobalScope(ScopeByBusiness::class)
                ->where('business_id', $this->businessId)
                ->where('type', \Modules\Whatsapp\Entities\Channel::TYPE_WHATSAPP_WHATSMEOW)
                ->where('status', 'active')
                ->first();
        }

        if ($channel === null) {
            Log::warning('whatsapp.webhook.whatsmeow.no_channel_resolved', [
                'business_id' => $this->businessId,
                'instance_name' => $instanceName,
                'provider_message_id' => $providerMessageId,
            ]);
            return;
        }

        $phoneE164 = (string) ($msg['from'] ?? '');
        $contactName = (string) ($msg['push_name'] ?? '') ?: $phoneE164;

        // firstOrCreate conversation
        $conversation = \DB::table('conversations')
            ->where('business_id', $this->businessId)
            ->where('channel_id', $channel->id)
            ->where('phone_e164', $phoneE164)
            ->first();

        $convId = $conversation?->id;
        $now = now();
        if ($convId === null) {
            $convId = \DB::table('conversations')->insertGetId([
                'business_id' => $this->businessId,
                'channel_id' => $channel->id,
                'phone_e164' => $phoneE164,
                'contact_name' => $contactName,
                'status' => 'open',
                'unread_count' => 1,
                'last_inbound_at' => $now,
                'last_message_at' => $now,
                'last_message_preview' => mb_substr((string) ($msg['body'] ?? ''), 0, 200),
                'last_message_direction' => 'inbound',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Schema 2026-05-27: messages table não tem channel_id direto;
        // canal vem via conversation_id → conversation.channel_id.
        \DB::table('messages')->insert([
            'business_id' => $this->businessId,
            'conversation_id' => $convId,
            'direction' => 'inbound',
            'provider' => 'whatsmeow',
            'provider_message_id' => $providerMessageId,
            'type' => $msg['type'] ?? 'text',
            'body' => $msg['body'] ?? null,
            'payload' => json_encode($msg['raw'] ?? null),
            'status' => 'received',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Realtime Centrifugo — publica evento "message.received" no canal
        // do business pra UI Inbox/ConversationThread atualizar sem refresh.
        try {
            app(\Modules\Whatsapp\Services\Centrifugo\CentrifugoPublisher::class)->publish(
                "whatsapp:business:{$this->businessId}",
                [
                    'event' => 'whatsmeow.message.received',
                    'channel_id' => $channel->id,
                    'conversation_id' => $convId,
                    'phone_e164' => $phoneE164,
                    'contact_name' => $contactName,
                    'body_preview' => mb_substr((string) ($msg['body'] ?? ''), 0, 200),
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('whatsmeow.centrifugo.publish_failed', ['error' => $e->getMessage()]);
        }

        if ($conversation !== null) {
            \DB::table('conversations')->where('id', $convId)->update([
                'unread_count' => ($conversation->unread_count ?? 0) + 1,
                'last_inbound_at' => $now,
                'last_message_at' => $now,
                'last_message_preview' => mb_substr((string) ($msg['body'] ?? ''), 0, 200),
                'last_message_direction' => 'inbound',
                'contact_name' => $contactName,
                'updated_at' => $now,
            ]);
        }

        Log::info('whatsapp.webhook.whatsmeow.message_persisted', [
            'business_id' => $this->businessId,
            'channel_id' => $channel->id,
            'conversation_id' => $convId,
            'provider_message_id' => $providerMessageId,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function extractMessages(): array
    {
        return match ($this->provider) {
            'meta_cloud' => $this->extractFromMeta($this->payload),
            'zapi' => $this->extractFromZapi($this->payload),
            'baileys' => $this->extractFromBaileys($this->payload),
            'whatsmeow' => $this->extractFromWhatsmeow($this->payload),
            default => [],
        };
    }

    /**
     * Whatsmeow (WuzAPI daemon Go) — ADR 0204.
     * Payload já unwrapped pelo WhatsmeowWebhookController: $payload.event.Info + $payload.event.Message
     */
    private function extractFromWhatsmeow(array $payload): array
    {
        $event = $payload['event'] ?? $payload;
        $info = $event['Info'] ?? [];
        $message = $event['Message'] ?? [];

        // SenderAlt tem o número E.164 real (Chat/Sender vem @lid em multi-device).
        // Fallback Chat se SenderAlt vazio (grupos / casos exóticos).
        $senderJid = (string) ($info['SenderAlt'] ?? $info['Chat'] ?? '');
        $phone = '+' . preg_replace('/\D/', '', explode('@', $senderJid)[0]);

        // Body — WuzAPI/whatsmeow embute em Message.conversation (text) ou Message.imageMessage.caption (image), etc.
        $type = strtolower((string) ($info['Type'] ?? 'text'));
        $body = match (true) {
            isset($message['conversation']) => (string) $message['conversation'],
            isset($message['extendedTextMessage']['text']) => (string) $message['extendedTextMessage']['text'],
            isset($message['imageMessage']['caption']) => (string) $message['imageMessage']['caption'] ?: '[imagem]',
            isset($message['documentMessage']['caption']) => (string) $message['documentMessage']['caption'] ?: '[documento]',
            isset($message['audioMessage']) => '[áudio]',
            isset($message['videoMessage']['caption']) => (string) $message['videoMessage']['caption'] ?: '[vídeo]',
            default => '[' . $type . ']',
        };

        if (($info['IsFromMe'] ?? false) === true) {
            return []; // outbound enviado por nós mesmo — ignora
        }

        return [[
            'provider_message_id' => $info['ID'] ?? null,
            'from' => $phone,
            'body' => $body,
            'type' => $type,
            'push_name' => $info['PushName'] ?? null,
            'raw' => $payload,
        ]];
    }

    private function extractFromMeta(array $payload): array
    {
        $out = [];
        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];
                foreach ($value['messages'] ?? [] as $m) {
                    $type = $m['type'] ?? 'text';
                    $body = match ($type) {
                        'text' => $m['text']['body'] ?? '',
                        'image' => $m['image']['caption'] ?? '[imagem]',
                        'document' => $m['document']['caption'] ?? '[documento]',
                        'audio' => '[áudio]',
                        default => '[' . $type . ']',
                    };
                    $out[] = [
                        'provider_message_id' => $m['id'] ?? null,
                        'from' => '+' . preg_replace('/\D/', '', $m['from'] ?? ''),
                        'body' => $body,
                        'type' => $type,
                        'raw' => $m,
                    ];
                }
            }
        }
        return $out;
    }

    private function extractFromZapi(array $payload): array
    {
        if ($payload['fromMe'] ?? false) {
            return [];
        }

        $type = strtolower($payload['type'] ?? 'text');
        $body = $payload['text']['message'] ?? $payload['caption'] ?? '';

        return [[
            'provider_message_id' => $payload['messageId'] ?? null,
            'from' => '+' . preg_replace('/\D/', '', $payload['phone'] ?? ''),
            'body' => $body,
            'type' => str_contains($type, 'image') ? 'image' : (str_contains($type, 'document') ? 'document' : 'text'),
            'raw' => $payload,
        ]];
    }

    private function extractFromBaileys(array $payload): array
    {
        if (($payload['event'] ?? '') !== 'message') {
            return [];
        }
        $data = $payload['data'] ?? [];
        return [[
            'provider_message_id' => $data['id'] ?? null,
            'from' => '+' . preg_replace('/\D/', '', $data['from'] ?? ''),
            'body' => $data['body'] ?? '',
            'type' => $data['type'] ?? 'text',
            'raw' => $data,
        ]];
    }

    private function upsertMessage(int $businessId, ?int $phoneId, array $msg): void
    {
        $providerMessageId = (string) ($msg['provider_message_id'] ?? '');
        if ($providerMessageId === '') {
            return;
        }

        // SUPERADMIN: job webhook sem session — provider_message_id é UNIQUE global (idempotência cross-tenant via wamid/messageId único)
        $existing = WhatsappMessage::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->where('provider_message_id', $providerMessageId)
            ->first();

        if ($existing !== null) {
            return;
        }

        // SUPERADMIN: job webhook sem session — firstOrCreate com business_id explícito (param)
        $conversation = WhatsappConversation::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->firstOrCreate(
                ['business_id' => $businessId, 'customer_phone' => $msg['from']],
                ['status' => 'open', 'whatsapp_business_phone_id' => $phoneId],
            );

        // Se conversa existente está sem phone_id (legacy) e agora resolvemos,
        // atualiza pra cravar o phone correto.
        if ($phoneId !== null && $conversation->whatsapp_business_phone_id === null) {
            $conversation->update(['whatsapp_business_phone_id' => $phoneId]);
        }

        // SUPERADMIN: job webhook sem session — INSERT inbound com business_id do param
        $message = WhatsappMessage::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->create([
                'business_id' => $businessId,
                'whatsapp_business_phone_id' => $phoneId ?? $conversation->whatsapp_business_phone_id,
                'conversation_id' => $conversation->id,
                'direction' => 'inbound',
                'provider' => $this->provider,
                'provider_message_id' => $providerMessageId,
                'type' => $msg['type'] ?? 'text',
                'body' => $msg['body'] ?? null,
                'payload' => $msg['raw'] ?? null,
                'status' => 'received',
            ]);

        $conversation->update([
            'last_inbound_at' => now(),
            'last_message_at' => now(),
            'unread_count' => $conversation->unread_count + 1,
        ]);

        WhatsappMessageReceived::dispatch($message);
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        $tags = ["business:{$this->businessId}", "whatsapp:webhook:{$this->provider}"];
        if ($this->whatsappBusinessPhoneId !== null) {
            $tags[] = "phone:{$this->whatsappBusinessPhoneId}";
        }
        return $tags;
    }
}
