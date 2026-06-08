<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifica HMAC + replay protection do webhook do daemon Baileys.
 *
 * **US-WA-082** — Dogfood `whatsapp-arch-arte` 2026-05-14 nota security 7/10:
 * "sem replay protection formal no webhook receiver". Threat model: atacante
 * MITM rede CT 100↔Hostinger captura 1 webhook → replay 10× → DDoS PHP-FPM.
 *
 * Solução canônica 2026 (defense-in-depth):
 *
 * 1. Daemon assina body com HMAC-SHA256(API_KEY) → header `x-baileys-signature`
 * 2. Daemon emite `x-baileys-nonce` (UUID v4) único + `x-baileys-ts` (epoch)
 * 3. Hostinger valida:
 *    - HMAC constant-time compare → 401 se inválida
 *    - `x-baileys-ts` ≤5min skew → 401 se fora janela
 *    - nonce não-visto via INSERT IGNORE em `webhook_nonces` → 401 se replay
 *
 * Trade-off: +5-10ms latência por request (HMAC compute + DB INSERT). Aceitável
 * vs ganho enterprise (SOC2/ISO 27001 checklist) + custo do ataque interno.
 *
 * @see Modules/Whatsapp/Database/Migrations/2026_05_14_020001_create_webhook_nonces_table.php
 * @see Modules/Whatsapp/daemon-node/src/webhook/WebhookDispatcher.ts (lado emissor)
 */
class VerifyBaileysWebhookHmac
{
    /** Replay window: requests com `ts` mais antigo que isso são rejeitados (5min). */
    private const REPLAY_WINDOW_SECONDS = 300;

    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = (string) config('whatsapp.baileys.api_key', '');
        if ($apiKey === '') {
            // Sem API_KEY no .env, middleware é no-op (backward compat —
            // daemon antigo sem HMAC ainda funciona durante rollout gradual).
            return $next($request);
        }

        $signature = (string) $request->header('x-baileys-signature', '');
        $nonce = (string) $request->header('x-baileys-nonce', '');
        $tsHeader = (string) $request->header('x-baileys-ts', '');

        // Daemon antigo (pre-PR #834) não envia headers — passa direto até
        // rollout completo. Log warn pra auditoria de migração.
        if ($signature === '' && $nonce === '' && $tsHeader === '') {
            Log::info('[whatsapp.webhook.hmac] daemon sem HMAC headers — backward compat', [
                'path' => $request->path(),
                'user_agent' => $request->userAgent(),
            ]);
            return $next($request);
        }

        // Validação 1: timestamp dentro de replay window
        $ts = (int) $tsHeader;
        $skew = abs(time() - $ts);
        if ($skew > self::REPLAY_WINDOW_SECONDS) {
            Log::warning('[whatsapp.webhook.hmac] replay window expired', [
                'ts_header' => $ts,
                'skew_seconds' => $skew,
                'path' => $request->path(),
            ]);
            return response()->json(['ok' => false, 'error' => 'replay_window_expired'], 401);
        }

        // Validação 2: HMAC constant-time compare
        // Daemon assina: HMAC-SHA256(API_KEY, ts + "." + nonce + "." + body_raw)
        $body = $request->getContent();
        $signedPayload = $tsHeader . '.' . $nonce . '.' . $body;
        $expected = hash_hmac('sha256', $signedPayload, $apiKey);

        if (! hash_equals($expected, $signature)) {
            Log::warning('[whatsapp.webhook.hmac] signature mismatch', [
                'path' => $request->path(),
                'expected_prefix' => substr($expected, 0, 8),
                'received_prefix' => substr($signature, 0, 8),
            ]);
            return response()->json(['ok' => false, 'error' => 'invalid_signature'], 401);
        }

        // Validação 3: nonce não-visto (INSERT IGNORE — atômico, sem race)
        // Se já existe, INSERT é no-op mas affected_rows=0 → replay detectado.
        $inserted = DB::table('webhook_nonces')->insertOrIgnore([
            'nonce' => $nonce,
            'source' => 'baileys',
            'created_at' => now(),
        ]);

        if ($inserted === 0) {
            Log::warning('[whatsapp.webhook.hmac] nonce already seen — replay detected', [
                'nonce_prefix' => substr($nonce, 0, 8),
                'path' => $request->path(),
            ]);
            return response()->json(['ok' => false, 'error' => 'nonce_replayed'], 401);
        }

        return $next($request);
    }
}
