<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\WhatsappBusinessConfig;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifica assinatura HMAC SHA-256 do webhook Meta Cloud.
 *
 * Meta envia header `X-Hub-Signature-256: sha256=<hex>` calculado com
 * o `app_secret` do business. Calculamos localmente com o mesmo segredo
 * e comparamos timing-safe.
 *
 * **GET handler (challenge):** Meta valida webhook URL com GET passando
 * `hub.mode=subscribe`, `hub.verify_token`, `hub.challenge`. Se
 * `verify_token` bate com `meta_webhook_verify_token` cadastrado,
 * retorna `hub.challenge` em texto.
 *
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-010 / R-WA-002
 * @see https://developers.facebook.com/docs/messenger-platform/webhooks#validate-payloads
 */
class VerifyMetaSignature
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

        // GET = Meta verification challenge
        if ($request->isMethod('GET')) {
            $mode = (string) $request->query('hub_mode', $request->query('hub.mode', ''));
            $token = (string) $request->query('hub_verify_token', $request->query('hub.verify_token', ''));
            $challenge = (string) $request->query('hub_challenge', $request->query('hub.challenge', ''));

            if ($mode === 'subscribe' && hash_equals((string) $config->meta_webhook_verify_token, $token)) {
                return response($challenge, 200)->header('Content-Type', 'text/plain');
            }
            return response()->json(['error' => 'verify_token_mismatch'], 403);
        }

        // POST = evento real
        $signature = (string) $request->header('X-Hub-Signature-256', '');
        if (! str_starts_with($signature, 'sha256=')) {
            return response()->json(['error' => 'missing_signature'], 401);
        }

        $providedHmac = substr($signature, 7);
        $rawBody = $request->getContent();
        $expectedHmac = hash_hmac('sha256', $rawBody, (string) $config->meta_app_secret);

        if (! hash_equals($expectedHmac, $providedHmac)) {
            \Log::warning('[whatsapp.webhook.meta] HMAC inválido', [
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
