<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\WhatsappBusinessConfig;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifica autenticação do webhook Z-API (timing-safe compare).
 *
 * Z-API envia em todo webhook outbound o header `z-api-token` contendo
 * o **token da instância** (`zapi_instance_token`), confirmado
 * empiricamente em prod 2026-05-08 (logs do middleware capturaram o
 * token recebido = `zapi_instance_token` cadastrado).
 *
 * Naming Z-API (confuso na doc):
 * - `zapi_instance_token` (24 chars) — usado por Z-API pra autenticar
 *   webhooks que ela ENVIA pra nós (inbound) via header `z-api-token`.
 * - `zapi_client_token` (Account Security Token / Client-Token, 34 chars)
 *   — usado por NÓS quando chamamos a API Z-API outbound (sendText etc)
 *   no header `Client-Token`.
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

        $providedToken = (string) ($request->header('z-api-token') ?? '');
        $expectedToken = (string) $config->zapi_instance_token;

        if ($providedToken === '' || $expectedToken === '' || ! hash_equals($expectedToken, $providedToken)) {
            \Log::warning('[whatsapp.webhook.zapi] z-api-token inválido', [
                'business_id' => $config->business_id,
                'business_uuid' => $businessUuid,
            ]);
            return response()->json(['error' => 'invalid_signature'], 401);
        }

        $request->attributes->set('whatsapp.config', $config);

        return $next($request);
    }
}
