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
 * Verifica autenticação do webhook do daemon Baileys (CT 100 → Hostinger).
 *
 * **US-WA-022:** o `api_key` é GLOBAL (config('whatsapp.baileys.api_key')) —
 * mesma chave do Docker secret CT 100 (env var `WHATSAPP_BAILEYS_API_KEY`).
 * Não é mais per-tenant. Multi-tenancy é via `business_uuid` no path.
 *
 * **Multi-números (ADR 0115 — US-WA-040):**
 * Após validar Bearer global, tenta resolver `WhatsappBusinessPhone` específico
 * via `instance_id` no body. Se acha, injeta como `whatsapp.phone`. Daemon
 * Node manda `instance_id` em todo webhook (auto-gerado per-phone).
 *
 * Camadas de defesa:
 *  1. IP whitelist Traefik (CT 100 só responde pra Hostinger 148.135.133.115)
 *  2. Bearer token global (este middleware)
 *  3. business_uuid no path → resolve config tenant (multi-tenant Tier 0)
 *  4. instance_id no body → resolve phone específico (multi-números)
 *
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-002d, US-WA-022, US-WA-040
 * @see memory/requisitos/Whatsapp/ARCHITECTURE.md §16.5
 * @see resources/js/Pages/Whatsapp/Settings.charter.md
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

        $expectedToken = (string) config('whatsapp.baileys.api_key', '');

        if ($providedToken === '' || $expectedToken === '' || ! hash_equals($expectedToken, $providedToken)) {
            \Log::warning('[whatsapp.webhook.baileys] Bearer inválido', [
                'business_id' => $config->business_id,
                'business_uuid' => $businessUuid,
            ]);

            return response()->json(['error' => 'invalid_signature'], 401);
        }

        // Resolve phone específico via instance_id no body (multi-números)
        $instanceId = (string) ($request->input('instance_id') ?? '');
        $phone = null;
        if ($instanceId !== '') {
            $phone = WhatsappBusinessPhone::query()
                ->withoutGlobalScope(ScopeByBusiness::class)
                ->where('business_id', $config->business_id)
                ->where('baileys_instance_id', $instanceId)
                ->first();
        }

        $request->attributes->set('whatsapp.config', $config);
        $request->attributes->set('whatsapp.phone', $phone);

        return $next($request);
    }
}
