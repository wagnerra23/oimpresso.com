<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\WhatsappBusinessConfig;
use Modules\Whatsapp\Entities\WhatsappBusinessPhone;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifica autenticação do webhook Z-API (timing-safe compare).
 *
 * Z-API envia em todo webhook outbound o header `z-api-token` contendo
 * o **token da instância** (`zapi_instance_token`).
 *
 * Naming Z-API (confuso na doc):
 * - `zapi_instance_token` — usado por Z-API pra autenticar webhooks que
 *   ela ENVIA pra nós (inbound). Header `z-api-token`.
 * - `zapi_client_token` (Account Security Token) — usado por NÓS quando
 *   chamamos a API outbound. Header `Client-Token`.
 *
 * **Multi-números (ADR 0117 — US-WA-040):**
 * Cada `WhatsappBusinessPhone` tem seu próprio `zapi_instance_token` (cada
 * instância Z-API é um número Whatsapp diferente). O middleware tenta
 * resolver o phone via header `z-api-token`. Se acha, valida e injeta;
 * senão, fallback config legacy (compara token com `config->zapi_instance_token`).
 *
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-010b / R-WA-002 / US-WA-040
 * @see https://developer.z-api.io/webhook/configurar-webhook
 */
class VerifyZapiSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $businessUuid = (string) $request->route('business_uuid');

        // SUPERADMIN: webhook público pré-auth — valida z-api-token HMAC-equivalente antes de identificar tenant via business_uuid no path
        $config = WhatsappBusinessConfig::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_uuid', $businessUuid)
            ->first();

        if ($config === null) {
            return response()->json(['error' => 'business_not_found'], 404);
        }

        $providedToken = (string) ($request->header('z-api-token') ?? '');
        if ($providedToken === '') {
            \Log::warning('[whatsapp.webhook.zapi] header z-api-token ausente', [
                'business_id' => $config->business_id,
                'business_uuid' => $businessUuid,
            ]);
            return response()->json(['error' => 'invalid_signature'], 401);
        }

        // SUPERADMIN: middleware webhook público — resolve phone via instance_token antes de auth completa; filtro business_id do config já resolvido
        // Tenta resolver phone específico via instance_token (multi-números)
        $phone = WhatsappBusinessPhone::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $config->business_id)
            ->whereNotNull('zapi_instance_token')
            ->get()
            ->first(fn ($p) => hash_equals((string) $p->zapi_instance_token, $providedToken));

        if ($phone !== null) {
            $request->attributes->set('whatsapp.config', $config);
            $request->attributes->set('whatsapp.phone', $phone);
            return $next($request);
        }

        // Fallback: valida com config legacy (comportamento pre-PR 2c)
        $expectedToken = (string) $config->zapi_instance_token;
        if ($expectedToken === '' || ! hash_equals($expectedToken, $providedToken)) {
            \Log::warning('[whatsapp.webhook.zapi] z-api-token inválido', [
                'business_id' => $config->business_id,
                'business_uuid' => $businessUuid,
            ]);
            return response()->json(['error' => 'invalid_signature'], 401);
        }

        $request->attributes->set('whatsapp.config', $config);
        $request->attributes->set('whatsapp.phone', null);

        return $next($request);
    }
}
