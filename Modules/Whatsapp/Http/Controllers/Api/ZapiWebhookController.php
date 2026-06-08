<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Whatsapp\Entities\WhatsappBusinessConfig;
use Modules\Whatsapp\Entities\WhatsappBusinessPhone;
use Modules\Whatsapp\Jobs\ProcessIncomingWebhookJob;

/**
 * ZapiWebhookController — recebe eventos de Z-API (`api.z-api.io`).
 *
 * Middleware `whatsapp.zapi.signature` (VerifyZapiSignature) valida
 * `Client-Token` timing-safe antes de chegar aqui.
 *
 * Z-API webhooks principais:
 *   - `on-message` (mensagem recebida)
 *   - `on-message-status` (sent/delivered/read/failed update)
 *   - `on-presence-status` (cliente digitando)
 *   - `on-disconnected` (sessão Whatsapp Web caiu — alerta!)
 *
 * Tipo do evento vem em `payload.type` ou no roteamento Z-API. Aqui
 * processamos todos via ProcessIncomingWebhookJob (driver-agnostic).
 *
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-010b
 * @see https://developer.z-api.io/webhook
 */
class ZapiWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        /** @var WhatsappBusinessConfig $config */
        $config = $request->attributes->get('whatsapp.config');
        /** @var WhatsappBusinessPhone|null $phone */
        $phone = $request->attributes->get('whatsapp.phone');

        $payload = $request->all();
        $eventType = strtolower((string) ($payload['type'] ?? ''));

        // on-disconnected é caso especial — driver pode estar caindo agora
        // (NOT enfileira mensagem; só log + sinaliza pra HealthCheck)
        if (str_contains($eventType, 'disconnected')) {
            \Log::warning('[whatsapp.webhook.zapi.disconnected] sessão Whatsapp Web caiu', [
                'business_id' => $config->business_id,
                'phone_id' => $phone?->id,
                'phone_label' => $phone?->label,
            ]);
            // HealthCheckJob (Sprint 2) detecta isso no próximo ciclo de 6h.
            return response()->json(['ok' => true, 'note' => 'session_lost_logged'], 200);
        }

        ProcessIncomingWebhookJob::dispatch(
            $config->business_id,
            'zapi',
            $payload,
            $phone?->id,
        );

        return response()->json(['ok' => true], 200);
    }
}
