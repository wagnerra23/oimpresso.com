<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\WhatsappBusinessConfig;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifica `Client-Token` do webhook Z-API (timing-safe compare).
 *
 * Z-API envia header `Client-Token: <token>` em todo webhook (configurado
 * no painel Z-API → Webhooks). O token bate com `zapi_client_token` que
 * cadastramos no `whatsapp_business_configs`.
 *
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-010b / R-WA-002
 * @see https://developer.z-api.io/webhook/configurar-webhook
 */
class VerifyZapiSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $businessUuid = (string) $request->route('business_uuid');

        $config = WhatsappBusinessConfig::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_uuid', $businessUuid)
            ->first();

        if ($config === null) {
            return response()->json(['error' => 'business_not_found'], 404);
        }

        $providedToken = (string) $request->header('Client-Token', '');
        $expectedToken = (string) $config->zapi_client_token;

        if ($providedToken === '' || $expectedToken === '' || ! hash_equals($expectedToken, $providedToken)) {
            \Log::warning('[whatsapp.webhook.zapi] Client-Token inválido', [
                'business_id' => $config->business_id,
                'business_uuid' => $businessUuid,
            ]);
            return response()->json(['error' => 'invalid_signature'], 401);
        }

        // Injeta config na request pra controller usar
        $request->attributes->set('whatsapp.config', $config);

        return $next($request);
    }
}
