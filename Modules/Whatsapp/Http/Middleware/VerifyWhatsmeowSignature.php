<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Http\Middleware;

use App\Business;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Channel;
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
 * **Fail-closed (2026-06-14):** sem assinatura HMAC válida NEM Token válido →
 * 401. O antigo fallback de IP whitelist (`177.74.67.30` + `10.0.0.0/8`) foi
 * REMOVIDO: `$request->ip()` reflete `X-Forwarded-For` sob `TrustProxies = '*'`
 * (app/Http/Middleware/TrustProxies.php), logo qualquer cliente da Internet
 * burlava a assinatura só omitindo o header e forjando o IP. IP allowlist do
 * daemon agora vive no edge (firewall Hostinger / Traefik), não em PHP.
 *
 * @see memory/decisions/0204-whatsmeow-driver-substituto-baileys.md
 * @see Modules/Whatsapp/Http/Controllers/Api/WhatsmeowWebhookController.php
 * @see Modules/Whatsapp/daemon-go/docker-compose.yml (WUZAPI_GLOBAL_HMAC_KEY)
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
        // ANTES de verificar HMAC. ADR 0135 (Caixa Unificada v4) — busca em
        // `business.uuid` (legacy table), NÃO `whatsapp_business_configs` que
        // ficou pra trás na pre-multi-phone era. Sessão 2026-05-27 confirmou.
        $businessRow = DB::table('business')
            ->where('uuid', $businessUuid)
            ->select('id')
            ->first();

        if ($businessRow === null) {
            \Log::warning('[whatsapp.webhook.whatsmeow] business_uuid não encontrado', [
                'business_uuid' => $businessUuid,
                'ip' => $request->ip(),
            ]);
            return response()->json(['error' => 'business_not_found'], 404);
        }

        $businessId = (int) $businessRow->id;

        // ─── Defesa por segredo na URL (hotfix incidente 2026-06-16) ────
        // O daemon WuzAPI (asternic/wuzapi) NÃO assina HMAC nem manda header
        // de auth — a única coisa configurável nele é a URL do webhook. Um
        // segredo compartilhado no query string `?wh=` trafega apenas via TLS
        // daemon→app e NÃO é spoofável como o antigo IP-whitelist removido em
        // #2726 (não depende de `X-Forwarded-For` sob `TrustProxies = '*'`).
        // Restaura o recebimento preservando Tier 0 (ADR 0093). Inerte quando
        // `WHATSMEOW_WEBHOOK_URL_SECRET` não está setado (fail-safe, timing-safe,
        // sem downgrade silencioso). Substituível por HMAC quando/se o daemon
        // ganhar suporte a assinatura.
        $urlSecret = (string) config('whatsapp.whatsmeow.webhook_url_secret', '');
        $providedUrlSecret = is_string($wh = $request->query('wh')) ? $wh : '';
        if ($urlSecret !== '' && hash_equals($urlSecret, $providedUrlSecret)) {
            $channel = $this->resolveChannel($request, $businessId);
            $request->attributes->set('whatsapp.business_id', $businessId);
            $request->attributes->set('whatsapp.channel', $channel);

            return $next($request);
        }

        // ─── Defesa primária: HMAC global SHA-256 (timing-safe) ─────────
        // Daemon assina cada POST com HMAC-SHA256(body, WUZAPI_GLOBAL_HMAC_KEY)
        // — ver daemon-go/docker-compose.yml (WUZAPI_GLOBAL_HMAC_KEY_FILE).
        // Laravel valida com a mesma chave (env WHATSMEOW_HMAC_SECRET).
        $hmacSecret = (string) config('whatsapp.whatsmeow.hmac_secret', '');
        $providedSignature = (string) $request->header('x-hmac-signature', '');

        if ($providedSignature !== '') {
            // Fail-closed: assinatura presente mas secret não configurado NÃO
            // pode fazer downgrade silencioso pra auth mais fraca — recusa alto
            // e claro (misconfig vira 401 + log, não bypass).
            if ($hmacSecret === '') {
                \Log::warning('[whatsapp.webhook.whatsmeow] x-hmac-signature recebida mas WHATSMEOW_HMAC_SECRET ausente', [
                    'business_id' => $businessId,
                    'business_uuid' => $businessUuid,
                    'ip' => $request->ip(),
                ]);
                return response()->json(['error' => 'hmac_not_configured'], 401);
            }

            // Daemon envia formato "sha256=<hex>" ou puro "<hex>" — aceita ambos
            $expectedHash = hash_hmac('sha256', (string) $request->getContent(), $hmacSecret);
            $providedHash = str_starts_with($providedSignature, 'sha256=')
                ? substr($providedSignature, 7)
                : $providedSignature;

            if (! hash_equals($expectedHash, $providedHash)) {
                \Log::warning('[whatsapp.webhook.whatsmeow] HMAC signature inválida', [
                    'business_id' => $businessId,
                    'business_uuid' => $businessUuid,
                    'ip' => $request->ip(),
                ]);
                return response()->json(['error' => 'invalid_signature'], 401);
            }

            // HMAC válido — segue resolução de channel
            $channel = $this->resolveChannel($request, $businessId);
            $request->attributes->set('whatsapp.business_id', $businessId);
            $request->attributes->set('whatsapp.channel', $channel);

            return $next($request);
        }

        // ─── Defesa secundária: Token header (segredo per-channel) ──────
        // Daemon configurado pra enviar o user_token no header `Token` em vez
        // de assinar via HMAC global. Match timing-safe contra
        // channels.config_json.whatsmeow_user_token — segredo real, não IP.
        $providedToken = (string) $request->header('Token', '');

        if ($providedToken === '') {
            // Sem HMAC nem Token = recusa (fail-closed).
            //
            // O fallback de IP whitelist (177.74.67.30 + 10.0.0.0/8) foi
            // REMOVIDO 2026-06-14: era spoofável via X-Forwarded-For sob
            // TrustProxies '*' — qualquer cliente entrava só omitindo a
            // assinatura. IP allowlist do daemon vive no edge, não em PHP.
            \Log::warning('[whatsapp.webhook.whatsmeow] sem HMAC nem Token — recusado', [
                'business_id' => $businessId,
                'business_uuid' => $businessUuid,
                'ip' => $request->ip(),
            ]);
            return response()->json(['error' => 'invalid_signature'], 401);
        }

        // SUPERADMIN: busca channels do business sem global scope (pré-auth)
        // pra match token. Após pass, attributes carregam channel resolvido.
        $channel = Channel::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $businessId)
            ->where('type', Channel::TYPE_WHATSAPP_WHATSMEOW)
            ->get()
            ->first(function (Channel $ch) use ($providedToken) {
                $cfg = $ch->config_json ?? [];
                $expected = (string) ($cfg['whatsmeow_user_token'] ?? '');
                return $expected !== '' && hash_equals($expected, $providedToken);
            });

        if ($channel === null) {
            \Log::warning('[whatsapp.webhook.whatsmeow] Token header não corresponde a nenhum channel', [
                'business_id' => $businessId,
                'business_uuid' => $businessUuid,
            ]);
            return response()->json(['error' => 'invalid_signature'], 401);
        }

        $request->attributes->set('whatsapp.business_id', $businessId);
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
