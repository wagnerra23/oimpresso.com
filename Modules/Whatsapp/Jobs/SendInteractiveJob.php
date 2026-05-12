<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\WhatsappBusinessPhone;
use Modules\Whatsapp\Entities\WhatsappConversation;
use Modules\Whatsapp\Entities\WhatsappMessage;
use Modules\Whatsapp\Events\WhatsappMessageFailed;
use Modules\Whatsapp\Events\WhatsappMessageQueued;
use Modules\Whatsapp\Events\WhatsappMessageSent;
use Modules\Whatsapp\Services\Drivers\DriverDoesNotSupport;
use Modules\Whatsapp\Services\Drivers\DriverFactory;

/**
 * SendInteractiveJob — envio outbound de mensagem interativa
 * (botões reply, list menu, CTA URL) — US-WA-045/046.
 *
 * Espelha o contrato de `SendWhatsappMessageJob`:
 *  - Multi-tenant Tier 0: `$businessId` + `$whatsappBusinessPhoneId` no constructor
 *  - Defensive multi-tenant: phone de outro biz é rejeitado (firstOrFail)
 *  - Cria `WhatsappMessage` em `status=queued` com `type=interactive` antes do driver
 *  - Em sucesso: marca `sent` + `provider_message_id` + dispatch `WhatsappMessageSent`
 *  - Em falha permanente (DriverDoesNotSupport): marca `failed`, NÃO re-throw (não retry)
 *  - Em falha transitória: marca `failed`, re-throw pra retry exponencial
 *
 * O payload completo do interativo é serializado em `WhatsappMessage.payload`
 * (JSON) — não exige nova coluna. UI no Inbox pode renderizar o resumo a partir
 * desse JSON depois.
 *
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-045, US-WA-046
 * @see memory/requisitos/Whatsapp/COMPARATIVO-MERCADO-2026-05-12.md gap P1 #8
 */
class SendInteractiveJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    /**
     * @param  array<string, mixed>  $interactive  Payload tipado discriminated union:
     *   ['type' => 'buttons', 'buttons' => [['id' => 'sim', 'label' => 'Sim'], ...]]
     *   ['type' => 'list', 'button_label' => 'Escolha', 'sections' => [...]]
     *   ['type' => 'cta_url', 'button_label' => 'Pagar', 'url' => 'https://...']
     */
    public function __construct(
        public readonly int $businessId,
        public readonly int $whatsappBusinessPhoneId,
        public readonly string $to,
        public readonly string $body,
        public readonly array $interactive,
    ) {
        $this->onQueue(config('whatsapp.queue', 'whatsapp'));
    }

    public function handle(): void
    {
        // SUPERADMIN: job sem session — Tier 0 defensivo (phone só do biz do constructor)
        $phone = WhatsappBusinessPhone::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $this->businessId)
            ->where('id', $this->whatsappBusinessPhoneId)
            ->firstOrFail();

        $driver = DriverFactory::make($phone);

        $conversation = $this->resolveConversation($this->businessId, $this->whatsappBusinessPhoneId, $this->to);

        // SUPERADMIN: job sem session — INSERT explícito com business_id
        $message = WhatsappMessage::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->create([
                'business_id' => $this->businessId,
                'whatsapp_business_phone_id' => $this->whatsappBusinessPhoneId,
                'conversation_id' => $conversation->id,
                'direction' => 'outbound',
                'provider' => $phone->effectiveDriver(),
                'type' => 'interactive',
                'body' => $this->body,
                'payload' => $this->interactive,
                'status' => 'queued',
                'sender_kind' => 'system',
            ]);

        WhatsappMessageQueued::dispatch($message);

        try {
            $result = $driver->sendInteractive($phone, $this->to, $this->body, $this->interactive);
        } catch (DriverDoesNotSupport $e) {
            // Falha PERMANENTE — não retry. Caller (UI/orquestrador) deve cair
            // pro driver alternativo (geralmente Meta Cloud).
            $message->update([
                'status' => 'failed',
                'failed_reason' => mb_substr($e->getMessage(), 0, 240),
            ]);

            WhatsappMessageFailed::dispatch(
                $message->fresh(),
                'driver_does_not_support',
                $e->getMessage(),
                false,
                false,
            );

            return; // sem re-throw — Tier 0 fail-fast sem retry
        }

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

        throw new \RuntimeException(
            "Whatsapp interactive send failed (business={$this->businessId}, phone={$this->whatsappBusinessPhoneId}, code={$result->errorCode}): {$result->errorMessage}"
        );
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return [
            "business:{$this->businessId}",
            "phone:{$this->whatsappBusinessPhoneId}",
            'whatsapp:interactive',
            'interactive:' . (string) ($this->interactive['type'] ?? 'unknown'),
        ];
    }

    private function resolveConversation(int $businessId, int $phoneId, string $to): WhatsappConversation
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
                ['status' => 'open', 'whatsapp_business_phone_id' => $phoneId],
            );
    }
}
