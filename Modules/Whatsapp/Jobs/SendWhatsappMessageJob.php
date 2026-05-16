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
use Modules\Whatsapp\Entities\WhatsappBusinessPhone;
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
 * **Multi-números (ADR 0117 — US-WA-040):**
 * `$whatsappBusinessPhoneId` no constructor — identifica qual número Whatsapp
 * do business envia a mensagem (Comercial, Financeiro, etc). Job resolve
 * `WhatsappBusinessPhone::where('business_id', $bizId)->where('id', $phoneId)
 * ->firstOrFail()` defensivo (Tier 0 — phone de outro business jamais é aceito).
 *
 * **Driver fallback runtime:**
 * `DriverFactory::make($phone)` é chamado no `handle()` (não no constructor) — se
 * driver primário ficou degraded entre dispatch e handle, fallback automático
 * pra Meta Cloud entra em ação sem intervenção (ADR 0096).
 *
 * **Append-only:**
 * Cria `WhatsappMessage` em `status=queued` no início; ao final, atualiza
 * apenas `status` + `failed_reason` + `provider_message_id` (campos NÃO
 * imutáveis — ver `WhatsappMessage::IMMUTABLE_COLUMNS`).
 *
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-003, US-WA-040
 * @see memory/requisitos/Whatsapp/ARCHITECTURE.md §3.1, §4
 * @see memory/decisions/0117-multiplos-numeros-whatsapp-por-business.md
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
        public readonly int $whatsappBusinessPhoneId,
        public readonly string $to,
        public readonly string $kind,
        public readonly array $payload,
    ) {
        $this->onQueue(config('whatsapp.queue', 'whatsapp'));
    }

    public function handle(): void
    {
        OtelHelper::span('whatsapp.message.send', [
            'business_id' => $this->businessId,
            'phone_id' => $this->whatsappBusinessPhoneId,
            'kind' => $this->kind,
        ], fn () => $this->doHandle());
    }

    private function doHandle(): void
    {
        Log::info('whatsapp.message.send.started', [
            'business_id' => $this->businessId,
            'phone_id' => $this->whatsappBusinessPhoneId,
            'kind' => $this->kind,
        ]);

        // SUPERADMIN: job sem session — business_id no constructor; filtro defensivo Tier 0 (phone de outro biz nunca aceito)
        // Resolve phone do business escapando global scope (job sem session())
        // Defensive multi-tenant: where('business_id', ...) garante phone pertence
        // ao business correto — phone de outro business jamais é aceito (Tier 0).
        $phone = WhatsappBusinessPhone::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $this->businessId)
            ->where('id', $this->whatsappBusinessPhoneId)
            ->firstOrFail();

        $driver = DriverFactory::make($phone);

        // Cria WhatsappMessage em status=queued (append-only)
        $conversation = $this->resolveConversation($this->businessId, $this->whatsappBusinessPhoneId, $this->to);

        // SUPERADMIN: job sem session — INSERT explícito com business_id do constructor
        $message = WhatsappMessage::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->create([
                'business_id' => $this->businessId,
                'whatsapp_business_phone_id' => $this->whatsappBusinessPhoneId,
                'conversation_id' => $conversation->id,
                'direction' => 'outbound',
                'provider' => $phone->effectiveDriver(),
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
                $phone,
                $this->to,
                $this->payload['name'],
                $this->payload['params'] ?? [],
                $this->payload['locale'] ?? 'pt_BR',
            ),
            'freeform' => $driver->sendFreeform($phone, $this->to, $this->payload['body']),
            'media' => $driver->sendMedia(
                $phone,
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

            Log::info('whatsapp.message.send.ok', [
                'business_id' => $this->businessId,
                'phone_id' => $this->whatsappBusinessPhoneId,
                'kind' => $this->kind,
                'message_id' => $message->id,
                'provider_message_id' => $result->providerMessageId,
            ]);

            return;
        }

        $message->update([
            'status' => 'failed',
            'failed_reason' => $result->errorMessage,
        ]);

        Log::warning('whatsapp.message.send.failed', [
            'business_id' => $this->businessId,
            'phone_id' => $this->whatsappBusinessPhoneId,
            'kind' => $this->kind,
            'message_id' => $message->id,
            'error_code' => $result->errorCode ?? 'unknown',
            'session_lost' => $result->sessionLost,
            'ban_detected' => $result->banDetected,
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
            "Whatsapp send failed (business={$this->businessId}, phone={$this->whatsappBusinessPhoneId}, code={$result->errorCode}): {$result->errorMessage}"
        );
    }

    /**
     * Tags pro Horizon (debug por business + phone).
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return [
            "business:{$this->businessId}",
            "phone:{$this->whatsappBusinessPhoneId}",
            "whatsapp:{$this->kind}",
        ];
    }

    private function resolveConversation(int $businessId, int $phoneId, string $to): WhatsappConversation
    {
        $normalized = preg_replace('/\D/', '', $to) ?? $to;
        if (strlen($normalized) <= 11) {
            $normalized = '55' . $normalized;
        }
        $normalized = '+' . $normalized;

        // SUPERADMIN: job sem session — firstOrCreate com business_id explícito do param do método
        return WhatsappConversation::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->firstOrCreate(
                ['business_id' => $businessId, 'customer_phone' => $normalized],
                ['status' => 'open', 'whatsapp_business_phone_id' => $phoneId],
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
