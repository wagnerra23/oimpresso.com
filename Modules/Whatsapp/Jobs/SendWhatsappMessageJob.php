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
use Modules\Whatsapp\Events\WhatsappMessageFailed;
use Modules\Whatsapp\Events\WhatsappMessageQueued;
use Modules\Whatsapp\Events\WhatsappMessageSent;
use Modules\Whatsapp\Services\Drivers\DriverFactory;

/**
 * Envia mensagem Whatsapp (template ou freeform) de forma assíncrona.
 *
 * **Multi-tenant Tier 0 (ADR 0093):**
 * `$businessId` no constructor — NUNCA usar `session()` em job (fila não tem session).
 *
 * **Driver fallback runtime:**
 * `DriverFactory::make($config)` é chamado no `handle()` (não no constructor) — se
 * driver primário ficou degraded entre dispatch e handle, fallback automático
 * pra Meta Cloud entra em ação sem intervenção (ADR 0096).
 *
 * **Append-only:**
 * Cria `WhatsappMessage` em `status=queued` no início; ao final, atualiza
 * apenas `status` + `failed_reason` + `provider_message_id` (campos NÃO
 * imutáveis — ver `WhatsappMessage::IMMUTABLE_COLUMNS`).
 *
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-003
 * @see memory/requisitos/Whatsapp/ARCHITECTURE.md §3.1, §4
 */
class SendWhatsappMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Retry exponencial: tries=5, backoff [60s, 5min, 15min, 1h, 1d].
     */
    public int $tries = 5;

    public function backoff(): array
    {
        return [60, 300, 900, 3600, 86400];
    }

    /**
     * @param  string  $kind  'template' | 'freeform' | 'media'
     * @param  array<string, mixed>  $payload  Variáveis específicas por kind:
     *   - template: ['name' => 'repair_status_ready', 'params' => ['Maria', '#OS-123'], 'locale' => 'pt_BR']
     *   - freeform: ['body' => 'Texto direto']
     *   - media: ['url' => 'https://...', 'type' => 'image|document|audio', 'caption' => 'opcional']
     */
    public function __construct(
        public readonly int $businessId,
        public readonly string $to,
        public readonly string $kind,
        public readonly array $payload,
    ) {
        $this->onQueue(config('whatsapp.queue', 'whatsapp'));
    }

    public function handle(): void
    {
        // Resolve config do business escapando global scope (job sem session())
        $config = WhatsappBusinessConfig::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $this->businessId)
            ->firstOrFail();

        $driver = DriverFactory::make($config);

        // Cria WhatsappMessage em status=queued (append-only)
        $conversation = $this->resolveConversation($this->businessId, $this->to);

        $message = WhatsappMessage::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->create([
                'business_id' => $this->businessId,
                'conversation_id' => $conversation->id,
                'direction' => 'outbound',
                'provider' => $config->effectiveDriver(),
                'type' => $this->kind === 'media' ? ($this->payload['type'] ?? 'document') : ($this->kind === 'template' ? 'template' : 'text'),
                'template_name' => $this->kind === 'template' ? ($this->payload['name'] ?? null) : null,
                'body' => $this->extractBody(),
                'status' => 'queued',
                'sender_kind' => 'system',
            ]);

        WhatsappMessageQueued::dispatch($message);

        // Chama driver
        $result = match ($this->kind) {
            'template' => $driver->sendTemplate(
                $config,
                $this->to,
                $this->payload['name'],
                $this->payload['params'] ?? [],
                $this->payload['locale'] ?? 'pt_BR',
            ),
            'freeform' => $driver->sendFreeform($config, $this->to, $this->payload['body']),
            'media' => $driver->sendMedia(
                $config,
                $this->to,
                $this->payload['url'],
                $this->payload['type'] ?? 'document',
                $this->payload['caption'] ?? null,
            ),
            default => throw new \InvalidArgumentException("Kind '{$this->kind}' inválido. Use: template|freeform|media."),
        };

        // Atualiza status (UPDATE permitido apenas em status/failed_reason/provider_message_id)
        if ($result->success) {
            $message->update([
                'status' => 'sent',
                'provider_message_id' => $result->providerMessageId,
            ]);

            $conversation->update([
                'last_outbound_at' => now(),
                'last_message_at' => now(),
            ]);

            WhatsappMessageSent::dispatch($message->fresh());
            return;
        }

        $message->update([
            'status' => 'failed',
            'failed_reason' => $result->errorMessage,
        ]);

        WhatsappMessageFailed::dispatch(
            $message->fresh(),
            $result->errorCode ?? 'unknown',
            $result->errorMessage ?? '',
            $result->sessionLost,
            $result->banDetected,
        );

        // Re-throw pra Laravel queue dispatchar retry exponencial
        throw new \RuntimeException(
            "Whatsapp send failed (business={$this->businessId}, code={$result->errorCode}): {$result->errorMessage}"
        );
    }

    /**
     * Tags pro Horizon (debug por business).
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ["business:{$this->businessId}", "whatsapp:{$this->kind}"];
    }

    private function resolveConversation(int $businessId, string $to): WhatsappConversation
    {
        $normalized = preg_replace('/\D/', '', $to) ?? $to;
        if (strlen($normalized) <= 11) {
            $normalized = '55' . $normalized;
        }
        $normalized = '+' . $normalized;

        return WhatsappConversation::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->firstOrCreate(
                ['business_id' => $businessId, 'customer_phone' => $normalized],
                ['status' => 'open'],
            );
    }

    private function extractBody(): ?string
    {
        return match ($this->kind) {
            'freeform' => $this->payload['body'] ?? null,
            'template' => '[template:' . ($this->payload['name'] ?? '?') . ']',
            'media' => $this->payload['caption'] ?? '[mídia]',
            default => null,
        };
    }
}
