<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Entities\WhatsappBusinessConfig;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifica autenticação do webhook Whatsmeow (ADR 0204) — daemon Go WuzAPI.
 *
 * WuzAPI envia em cada webhook 2 mecanismos de autenticação:
 *
 *  1. Header `x-hmac-signature: sha256={hex}` — HMAC-SHA256 do body com
 *     `WUZAPI_GLOBAL_HMAC_KEY` configurado no daemon (env). Comparação
 *     timing-safe via `hash_equals()`.
 *
 *  2. Header `Token: {user_token}` (fallback) — token gerado quando Laravel
 *     criou a sessão via POST /admin/users no daemon. Stored cifrado em
 *     `channels.config_json.whatsmeow_user_token`.
 *
 * **Multi-tenant Tier 0 (ADR 0093):** business_uuid vem no path da rota
 * (`/api/whatsapp/webhook/whatsmeow/{business_uuid}`). Middleware bypassa
 * global scope pra resolver business_id pré-auth (mesmo padrão Z-API/Baileys).
 *
 * SUPERADMIN bypass justificado: webhook é receiver público (Internet aberta),
 * autenticação acontece NESTE middleware via HMAC + token. Após pass, request
 * attributes carregam `whatsapp.config` e `whatsapp.channel` pro controller.
 *
 * @see memory/decisions/0204-whatsmeow-driver-substituto-baileys.md
 * @see Modules/Whatsapp/Http/Controllers/Api/WhatsmeowWebhookController.php
 * @see https://github.com/asternic/wuzapi (HMAC global)
 */
class VerifyWhatsmeowSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $businessUuid = (string) $request->route('business_uuid');

        if ($businessUuid === '') {
            return response()->json(['error' => 'missing_business_uuid'], 400);
        }

        // SUPERADMIN: webhook público pré-auth — resolve business via uuid no path
        // ANTES de verificar HMAC. Necessário pra logging de falha de auth com
        // contexto biz_id (debugging cross-tenant).
        $config = WhatsappBusinessConfig::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_uuid', $businessUuid)
            ->first();

        if ($config === null) {
            \Log::warning('[whatsapp.webhook.whatsmeow] business_uuid não encontrado', [
                'business_uuid' => $businessUuid,
                'ip' => $request->ip(),
            ]);
            return response()->json(['error' => 'business_not_found'], 404);
        }

        // ─── Verificação HMAC global (defesa primária) ──────────────────
        // Daemon assina cada POST com HMAC-SHA256(body, WUZAPI_GLOBAL_HMAC_KEY).
        // Laravel valida com mesma chave (env WHATSMEOW_HMAC_SECRET).
        $hmacSecret = (string) config('whatsapp.whatsmeow.hmac_secret', '');
        $providedSignature = (string) $request->header('x-hmac-signature', '');

        if ($hmacSecret !== '' && $providedSignature !== '') {
            // Daemon envia formato "sha256=<hex>" ou puro "<hex>" — aceita ambos
            $expectedHash = hash_hmac('sha256', (string) $request->getContent(), $hmacSecret);
            $providedHash = str_starts_with($providedSignature, 'sha256=')
                ? substr($providedSignature, 7)
                : $providedSignature;

            if (! hash_equals($expectedHash, $providedHash)) {
                \Log::warning('[whatsapp.webhook.whatsmeow] HMAC signature inválida', [
                    'business_id' => $config->business_id,
                    'business_uuid' => $businessUuid,
                    'ip' => $request->ip(),
                ]);
                return response()->json(['error' => 'invalid_signature'], 401);
            }

            // HMAC válido — segue resolução de channel
            $channel = $this->resolveChannel($request, $config->business_id);
            $request->attributes->set('whatsapp.config', $config);
            $request->attributes->set('whatsapp.channel', $channel);

            return $next($request);
        }

        // ─── Fallback: Token header (sem HMAC global configurado) ───────
        // Cenário: HMAC global não setado no daemon (dev) — valida via user_token
        // matching algum channel ativo do business. Timing-safe compare.
        $providedToken = (string) $request->header('Token', '');
        if ($providedToken === '') {
            \Log::warning('[whatsapp.webhook.whatsmeow] sem HMAC nem Token header', [
                'business_id' => $config->business_id,
                'business_uuid' => $businessUuid,
            ]);
            return response()->json(['error' => 'invalid_signature'], 401);
        }

        // SUPERADMIN: busca channels do business sem global scope (pré-auth)
        // pra match token. Após pass, attributes carregam channel resolvido.
        $channel = Channel::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $config->business_id)
            ->where('type', Channel::TYPE_WHATSAPP_WHATSMEOW)
            ->get()
            ->first(function (Channel $ch) use ($providedToken) {
                $cfg = $ch->config_json ?? [];
                $expected = (string) ($cfg['whatsmeow_user_token'] ?? '');
                return $expected !== '' && hash_equals($expected, $providedToken);
            });

        if ($channel === null) {
            \Log::warning('[whatsapp.webhook.whatsmeow] Token header não corresponde a nenhum channel', [
                'business_id' => $config->business_id,
                'business_uuid' => $businessUuid,
            ]);
            return response()->json(['error' => 'invalid_signature'], 401);
        }

        $request->attributes->set('whatsapp.config', $config);
        $request->attributes->set('whatsapp.channel', $channel);

        return $next($request);
    }

    /**
     * Tenta resolver channel específico a partir de hint no payload (ex:
     * `Username` que WuzAPI inclui = nosso `whatsmeowUserName()`).
     *
     * Fallback null = controller resolve via outras heurísticas ou recusa.
     */
    private function resolveChannel(Request $request, int $businessId): ?Channel
    {
        $payload = $request->json()->all();
        $userName = $payload['Username'] ?? $payload['user'] ?? null;

        if (! is_string($userName) || $userName === '') {
            return null;
        }

        // SUPERADMIN: lookup pré-auth com global scope bypassado — já passou HMAC
        return Channel::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $businessId)
            ->where('type', Channel::TYPE_WHATSAPP_WHATSMEOW)
            ->get()
            ->first(fn (Channel $ch) => $ch->whatsmeowUserName() === $userName);
    }
}
