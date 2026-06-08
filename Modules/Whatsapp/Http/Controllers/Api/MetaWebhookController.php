<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Modules\Whatsapp\Entities\WhatsappBusinessConfig;
use Modules\Whatsapp\Entities\WhatsappBusinessPhone;
use Modules\Whatsapp\Jobs\ProcessIncomingWebhookJob;

/**
 * MetaWebhookController — recebe eventos de Meta Cloud API.
 *
 * Middleware `whatsapp.meta.signature` (VerifyMetaSignature) valida HMAC
 * SHA-256 + GET challenge antes de chegar aqui. Se passou, request tem
 * `whatsapp.config` injetado.
 *
 * Resposta SEMPRE 200 (Meta retenta agressivo se ≠200) — só rejeita 401
 * em assinatura inválida (no middleware). Job assíncrono evita timeout.
 *
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-010
 * @see memory/requisitos/Whatsapp/ARCHITECTURE.md §3.2
 */
class MetaWebhookController extends Controller
{
    /**
     * GET handler — Meta verification challenge (antes do POST iniciar).
     * Retorna `hub.challenge` se `verify_token` bater.
     *
     * Esta rota é tratada pelo middleware `VerifyMetaSignature` que
     * retorna direto. Esse controller é só fallback no map de rotas.
     */
    public function verify(Request $request): JsonResponse
    {
        // Middleware já tratou — se chegou aqui, é fallback genérico
        return response()->json(['error' => 'method_not_allowed'], 405);
    }

    /**
     * POST handler — recebe eventos `messages` e `statuses` do Meta.
     */
    public function handle(Request $request): JsonResponse
    {
        /** @var WhatsappBusinessConfig $config */
        $config = $request->attributes->get('whatsapp.config');
        /** @var WhatsappBusinessPhone|null $phone */
        $phone = $request->attributes->get('whatsapp.phone');

        Log::info('whatsapp.webhook.received', [
            'business_id' => $config->business_id,
            'provider' => 'meta_cloud',
            'phone_id' => $phone?->id,
            'event' => 'http_post',
        ]);

        ProcessIncomingWebhookJob::dispatch(
            $config->business_id,
            'meta_cloud',
            $request->all(),
            $phone?->id,
        );

        // Sempre 200 (Meta retenta se ≠200)
        return response()->json(['ok' => true], 200);
    }
}
