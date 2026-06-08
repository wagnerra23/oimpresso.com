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
 * Verifica assinatura HMAC SHA-256 do webhook Meta Cloud.
 *
 * Meta envia header `X-Hub-Signature-256: sha256=<hex>` calculado com
 * o `app_secret` do business. Calculamos localmente com o mesmo segredo
 * e comparamos timing-safe.
 *
 * **Multi-números (ADR 0117 — US-WA-040):**
 * Após validar HMAC com `config->meta_app_secret` (legacy), tenta resolver
 * o `WhatsappBusinessPhone` específico via `phone_number_id` extraído do
 * payload (`entry[].changes[].value.metadata.phone_number_id`). Inject
 * `whatsapp.phone` se resolvido. Controller usa `phone` preferencialmente
 * com fallback `config`.
 *
 * **GET handler (challenge):** Meta valida webhook URL com GET passando
 * `hub.mode=subscribe`, `hub.verify_token`, `hub.challenge`. Se
 * `verify_token` bate com `meta_webhook_verify_token`, retorna challenge.
 *
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-010 / R-WA-002 / US-WA-040
 * @see https://developers.facebook.com/docs/messenger-platform/webhooks#validate-payloads
 */
class VerifyMetaSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $businessUuid = (string) $request->route('business_uuid');

        // SUPERADMIN: webhook público Meta pré-auth — valida HMAC SHA-256 antes de identificar tenant via business_uuid no path
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

        // Resolve phone específico via phone_number_id payload (multi-números)
        $phone = $this->resolvePhone($config->business_id, $request->all());

        $request->attributes->set('whatsapp.config', $config);
        $request->attributes->set('whatsapp.phone', $phone);

        return $next($request);
    }

    /**
     * Tenta extrair `phone_number_id` do payload Meta e resolver o
     * `WhatsappBusinessPhone` correspondente. Retorna null se nenhum
     * payload válido OU phone não cadastrado (legacy fallback).
     *
     * Estrutura Meta:
     *   entry[].changes[].value.metadata.phone_number_id
     */
    private function resolvePhone(int $businessId, array $payload): ?WhatsappBusinessPhone
    {
        $phoneNumberId = null;
        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $candidate = $change['value']['metadata']['phone_number_id'] ?? null;
                if (is_string($candidate) && $candidate !== '') {
                    $phoneNumberId = $candidate;
                    break 2;
                }
            }
        }

        if ($phoneNumberId === null) {
            return null;
        }

        // SUPERADMIN: middleware webhook público — resolve phone via phone_number_id após HMAC já validado; filtro business_id do config
        return WhatsappBusinessPhone::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $businessId)
            ->where('meta_phone_number_id', $phoneNumberId)
            ->first();
    }
}
