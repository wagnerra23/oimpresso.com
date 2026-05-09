<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\WhatsappBusinessConfig;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifica autenticação do webhook do daemon Baileys (CT 100 → Hostinger).
 *
 * O daemon Node envia `Authorization: Bearer {api_key}` em todo POST.
 * O `api_key` é o mesmo `baileys_api_key` cadastrado em
 * `whatsapp_business_configs.baileys_api_key` (cifrado pelo `encrypted` cast).
 *
 * Comparação timing-safe via hash_equals.
 *
 * Camadas de defesa:
 *  1. IP whitelist Traefik (CT 100 só responde pra Hostinger 148.135.133.115)
 *  2. Bearer token (este middleware)
 *  3. business_uuid no path (daemon não pode confundir tenant)
 *
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-002d
 * @see memory/requisitos/Whatsapp/ARCHITECTURE.md §16.5
 */
class VerifyBaileysSignature
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

        $header = (string) ($request->header('authorization') ?? '');
        $providedToken = '';
        if (str_starts_with($header, 'Bearer ')) {
            $providedToken = trim(substr($header, 7));
        }

        $expectedToken = (string) $config->baileys_api_key;

        if ($providedToken === '' || $expectedToken === '' || ! hash_equals($expectedToken, $providedToken)) {
            \Log::warning('[whatsapp.webhook.baileys] Bearer inválido', [
                'business_id' => $config->business_id,
                'business_uuid' => $businessUuid,
            ]);

            return response()->json(['error' => 'invalid_signature'], 401);
        }

        $request->attributes->set('whatsapp.config', $config);

        return $next($request);
    }
}
