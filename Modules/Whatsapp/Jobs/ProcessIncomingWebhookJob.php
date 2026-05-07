<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\WhatsappBusinessConfig;
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
 * **Idempotência:** UNIQUE em `provider_message_id` impede duplicata.
 * Se webhook chegar 2× com mesmo wamid/messageId, segunda vez é no-op.
 *
 * **Tier 0 (ADR 0093):** `$businessId` no constructor; queries com
 * `withoutGlobalScope` + filtro explícito.
 *
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-011
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
    ) {
        $this->onQueue(config('whatsapp.queue', 'whatsapp'));
    }

    public function backoff(): array
    {
        return [30, 90, 270];
    }

    public function handle(): void
    {
        $config = WhatsappBusinessConfig::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $this->businessId)
            ->firstOrFail();

        $extracted = $this->extractMessages();
        if (empty($extracted)) {
            return; // payload sem mensagens — pode ser status update ou evento outro
        }

        foreach ($extracted as $msg) {
            $this->upsertMessage($config, $msg);
        }
    }

    /**
     * Extrai mensagens do payload do provider em formato comum:
     *   [
     *     ['provider_message_id' => 'wamid.X', 'from' => '+5511...', 'body' => 'texto', 'type' => 'text'],
     *     ...
     *   ]
     *
     * @return array<int, array<string, mixed>>
     */
    private function extractMessages(): array
    {
        return match ($this->provider) {
            'meta_cloud' => $this->extractFromMeta($this->payload),
            'zapi' => $this->extractFromZapi($this->payload),
            'baileys' => $this->extractFromBaileys($this->payload),
            default => [],
        };
    }

    /**
     * Meta Cloud payload (estrutura oficial):
     * { "entry": [{ "changes": [{ "value": { "messages": [{ "id": "wamid.X", "from": "5511...", "type": "text", "text": {"body": "..."} }] } }] }] }
     */
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

    /**
     * Z-API payload (on-message event):
     * { "messageId": "...", "phone": "5511...", "fromMe": false, "text": {"message": "..."}, "type": "ReceivedCallback" }
     */
    private function extractFromZapi(array $payload): array
    {
        // Ignora mensagens enviadas pelo próprio business (evita echo)
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

    /**
     * BaileysDriver custom (Sprint 3) — normalizado pelo daemon Node próprio
     * pra estrutura comum mais simples.
     */
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

    private function upsertMessage(WhatsappBusinessConfig $config, array $msg): void
    {
        $providerMessageId = (string) ($msg['provider_message_id'] ?? '');
        if ($providerMessageId === '') {
            return;
        }

        // Idempotência: se já existe, no-op (Tier 0 — UNIQUE provider_message_id)
        $existing = WhatsappMessage::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->where('provider_message_id', $providerMessageId)
            ->first();

        if ($existing !== null) {
            return;
        }

        $conversation = WhatsappConversation::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->firstOrCreate(
                ['business_id' => $config->business_id, 'customer_phone' => $msg['from']],
                ['status' => 'open'],
            );

        $message = WhatsappMessage::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->create([
                'business_id' => $config->business_id,
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
        return ["business:{$this->businessId}", "whatsapp:webhook:{$this->provider}"];
    }
}
