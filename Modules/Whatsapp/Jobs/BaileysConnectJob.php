<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\WhatsappBusinessConfig;
use Modules\Whatsapp\Entities\WhatsappBusinessPhone;

/**
 * BaileysConnectJob — provisiona instance no daemon Node.
 *
 * **Disparado:**
 * - SettingsController@update quando driver=baileys + phone novo + LGPD ok
 * - UI "Reconectar" no estado disconnected/banned (Sprint futuro)
 *
 * **Multi-números (ADR 0117 — US-WA-040):**
 * Aceita `?int $whatsappBusinessPhoneId` opcional. Quando set, conecta phone
 * específico (cada phone tem seu próprio instance_id auto-gerado). Quando
 * NULL, fallback config legacy.
 *
 * **Faz:**
 * 1. Garante baileys_instance_id auto-gerado ("biz{business_id}-{random6}")
 * 2. Salva o target (mesmo que connect falhe, instance_id fica registrado)
 * 3. POST {daemon_url}/instances/{instance_id}/connect
 * 4. Daemon cria socket Whatsapp Web em background, emite webhook
 *    qr_updated → Hostinger publica em Centrifugo channel
 *    `whatsapp:business:{id}` → UI mostra QR
 *
 * **NÃO faz:** esperar QR síncrono. Resposta é fire-and-forget — o flow
 * assíncrono via webhook + Centrifugo é a única fonte de truth UI.
 *
 * **Multi-tenant Tier 0 (ADR 0093):** $businessId no constructor;
 * resolve target com withoutGlobalScope + filtro explícito.
 *
 * **Falha tolerante:** retry 3x exponencial (10s/30s/90s). Após 3
 * falhas, marca driver_health=disconnected + last_health_message;
 * UI pede pro user retentar manual via botão "Reconectar".
 *
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-022, US-WA-040
 * @see resources/js/Pages/Whatsapp/Settings.charter.md
 */
class BaileysConnectJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 30;

    /**
     * @return array<int, int>  backoff em segundos
     */
    public function backoff(): array
    {
        return [10, 30, 90];
    }

    public function __construct(
        public readonly int $businessId,
        public readonly ?int $whatsappBusinessPhoneId = null,
    ) {
        $this->onQueue(config('whatsapp.queue', 'whatsapp'));
    }

    public function handle(): void
    {
        $target = $this->resolveTarget();

        if ($target === null) {
            Log::warning('[whatsapp.baileys.connect] target não encontrado', [
                'business_id' => $this->businessId,
                'phone_id' => $this->whatsappBusinessPhoneId,
            ]);

            return;
        }

        if ($target->driver !== 'baileys') {
            Log::info('[whatsapp.baileys.connect] driver mudou pra outro provedor — abortando', [
                'business_id' => $this->businessId,
                'phone_id' => $this->whatsappBusinessPhoneId,
                'driver' => $target->driver,
            ]);

            return;
        }

        if (empty($target->baileys_phone_e164)) {
            Log::warning('[whatsapp.baileys.connect] telefone E.164 ausente — abortando', [
                'business_id' => $this->businessId,
                'phone_id' => $this->whatsappBusinessPhoneId,
            ]);

            return;
        }

        $instanceId = $target->ensureBaileysInstanceId();

        $target->forceFill([
            'baileys_instance_id' => $instanceId,
            'driver_health' => 'never_checked',
            'last_health_check_at' => now(),
            'last_health_message' => 'Conectando ao daemon Baileys...',
        ])->save();

        $apiKey = (string) config('whatsapp.baileys.api_key', '');
        if ($apiKey === '') {
            Log::error('[whatsapp.baileys.connect] WHATSAPP_BAILEYS_API_KEY ausente no .env', [
                'business_id' => $this->businessId,
                'phone_id' => $this->whatsappBusinessPhoneId,
            ]);
            $target->forceFill([
                'driver_health' => 'disconnected',
                'last_health_message' => 'WHATSAPP_BAILEYS_API_KEY ausente — admin oimpresso precisa configurar',
            ])->save();

            return;
        }

        $daemonUrl = (string) config('whatsapp.baileys.daemon_url', 'https://whatsapp-baileys.oimpresso.local');
        $timeout = (int) config('whatsapp.baileys.request_timeout', 15);

        // business_uuid é único por business (nas duas tabelas vem do config legacy
        // que continua existindo durante coexistência)
        $businessUuid = $target instanceof WhatsappBusinessPhone
            ? $this->resolveBusinessUuid($target->business_id)
            : $target->business_uuid;

        $response = Http::baseUrl(rtrim($daemonUrl, '/'))
            ->timeout($timeout)
            ->withToken($apiKey)
            ->acceptJson()
            ->asJson()
            ->post("/instances/{$instanceId}/connect", [
                'business_uuid' => $businessUuid,
                'business_id' => $this->businessId,
                'phone_id' => $this->whatsappBusinessPhoneId,
            ]);

        if ($response->successful()) {
            Log::info('[whatsapp.baileys.connect] daemon aceitou connect', [
                'business_id' => $this->businessId,
                'phone_id' => $this->whatsappBusinessPhoneId,
                'instance_id' => $instanceId,
                'state' => $response->json('state'),
            ]);

            return;
        }

        $errorMsg = "daemon connect falhou: HTTP {$response->status()} — "
            . \Illuminate\Support\Str::limit($response->body(), 200);

        Log::error('[whatsapp.baileys.connect] ' . $errorMsg, [
            'business_id' => $this->businessId,
            'phone_id' => $this->whatsappBusinessPhoneId,
            'instance_id' => $instanceId,
        ]);

        $target->forceFill([
            'driver_health' => 'disconnected',
            'driver_health_consecutive_failures' => ($target->driver_health_consecutive_failures ?? 0) + 1,
            'last_health_message' => $errorMsg,
        ])->save();

        if ($this->attempts() < $this->tries) {
            throw new \RuntimeException($errorMsg);
        }
    }

    /**
     * @return WhatsappBusinessConfig|WhatsappBusinessPhone|null
     */
    private function resolveTarget()
    {
        if ($this->whatsappBusinessPhoneId !== null) {
            return WhatsappBusinessPhone::query()
                ->withoutGlobalScope(ScopeByBusiness::class)
                ->where('business_id', $this->businessId)
                ->where('id', $this->whatsappBusinessPhoneId)
                ->first();
        }

        return WhatsappBusinessConfig::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $this->businessId)
            ->first();
    }

    /**
     * Phone não tem business_uuid próprio — herda do config legacy do business.
     * Usado pra autenticar daemon webhook → controller (continua usando
     * business_uuid no path durante coexistência).
     */
    private function resolveBusinessUuid(int $businessId): ?string
    {
        $config = WhatsappBusinessConfig::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $businessId)
            ->first();

        return $config?->business_uuid;
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('[whatsapp.baileys.connect] todas as tentativas falharam', [
            'business_id' => $this->businessId,
            'phone_id' => $this->whatsappBusinessPhoneId,
            'error' => $exception->getMessage(),
        ]);
    }
}
