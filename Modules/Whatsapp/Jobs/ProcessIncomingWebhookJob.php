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
            // SUPERADMIN: fallback config legacy — job sem session, biz do constructor
            // Fallback config legacy (durante coexistência PR 1 → PR 5)
            $config = WhatsappBusinessConfig::query()
                ->withoutGlobalScope(ScopeByBusiness::class)
                ->where('business_id', $this->businessId)
                ->firstOrFail();
            $resolvedPhoneId = null;
        } else {
            $resolvedPhoneId = $phone->id;
        }

        $extracted = $this->extractMessages();
        if (empty($extracted)) {
            return;
        }

        foreach ($extracted as $msg) {
            $this->upsertMessage($this->businessId, $resolvedPhoneId, $msg);
        }
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
            default => [],
        };
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
