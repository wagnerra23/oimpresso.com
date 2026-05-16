<?php

declare(strict_types=1);

namespace Modules\OficinaAuto\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Modules\OficinaAuto\Entities\ServiceOrder;

/**
 * AprovacaoOsService — Geração + validação de token público + PIN
 * pra aprovação de OS via WhatsApp (US-OFICINA-006).
 *
 * Estratégia V0 (sem coluna nova em service_orders):
 *
 *  - Token = HMAC-SHA256(payload | secret) onde payload = base64url(json{
 *      os_id, business_id, exp_ts
 *    })
 *    Token completo = "<payload_b64>.<sig>"
 *    TTL fixo 7 dias (sem coluna `approval_expires_at` ainda — embed no token).
 *
 *  - PIN = 4 dígitos numéricos, random_int seguro.
 *    Armazenado em cache (`Cache::put`) com TTL 7 dias.
 *    Key: "oficina:aprovacao_pin:{os_id}:{business_id}".
 *
 *  - Rate limit por OS pra evitar bruteforce PIN: 5 tentativas → bloqueia 30min.
 *    Key: "oficina:aprovacao_pin_attempts:{os_id}".
 *
 * Multi-tenant Tier 0 (ADR 0093): token CARREGA business_id assinado +
 * `validarToken` re-checa OS pertence ao mesmo business_id que assinou.
 *
 * Quando US-OFICINA-006 entregar coluna `approval_token`, `approval_pin_hash`,
 * `approval_expires_at` no service_orders, migrar daqui pra DB
 * (mantém contrato dos métodos públicos).
 *
 * @see memory/requisitos/OficinaAuto/SPEC.md US-OFICINA-006
 * @see Modules/OficinaAuto/Tests/Feature/WhatsAppAprovacaoPinTest.php
 */
class AprovacaoOsService
{
    /** TTL link aprovação. */
    public const TOKEN_TTL_DAYS = 7;

    /** Máximo de tentativas PIN antes de bloquear. */
    public const MAX_PIN_ATTEMPTS = 5;

    /** Bloqueio em segundos após exceder tentativas. */
    public const PIN_LOCKOUT_SECONDS = 1800; // 30min

    /**
     * Gera token assinado HMAC + PIN 4 dígitos e PERSISTE PIN no cache.
     *
     * Retorna array ['token' => string, 'pin' => string, 'expires_at' => Carbon].
     * Caller (Job WhatsApp) é responsável por enviar PIN ao cliente (canal seguro)
     * e link público (canal WhatsApp/SMS).
     */
    public function gerarTokenAprovacao(ServiceOrder $so): array
    {
        $expTs = now()->addDays(self::TOKEN_TTL_DAYS)->timestamp;

        $payload = [
            'os_id'       => (int) $so->id,
            'business_id' => (int) $so->business_id,
            'exp_ts'      => $expTs,
        ];

        $payloadB64 = $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));
        $sig        = $this->sign($payloadB64);
        $token      = $payloadB64.'.'.$sig;

        // PIN aleatório seguro 4 dígitos (0000-9999)
        $pin = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);

        Cache::put(
            $this->pinCacheKey((int) $so->id, (int) $so->business_id),
            hash('sha256', $pin), // armazena hash, NUNCA plain
            now()->addDays(self::TOKEN_TTL_DAYS)
        );

        // Reset contador de tentativas em nova geração
        Cache::forget($this->attemptsCacheKey((int) $so->id));

        return [
            'token'      => $token,
            'pin'        => $pin, // plain-text APENAS pra envio ao cliente (1x)
            'expires_at' => now()->addDays(self::TOKEN_TTL_DAYS),
        ];
    }

    /**
     * Valida token público + retorna ServiceOrder se válido e dentro do TTL.
     *
     * Verifica:
     *  - Assinatura HMAC bate (anti-tampering)
     *  - exp_ts > now (não expirou)
     *  - OS existe, é do business_id do token (multi-tenant), está em status `orcamento`
     *
     * Retorna null se qualquer check falhar. NÃO loga payload do token (pode ter PII via SO).
     */
    public function validarToken(string $token): ?ServiceOrder
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return null;
        }

        [$payloadB64, $sig] = $parts;

        // hash_equals constant-time
        if (! hash_equals($this->sign($payloadB64), $sig)) {
            return null;
        }

        try {
            $payload = json_decode($this->base64UrlDecode($payloadB64), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        if (! is_array($payload) || ! isset($payload['os_id'], $payload['business_id'], $payload['exp_ts'])) {
            return null;
        }

        if ((int) $payload['exp_ts'] <= now()->timestamp) {
            return null;
        }

        // Multi-tenant Tier 0: busca SEM global scope (rota pública sem session),
        // mas valida business_id bate com o do token assinado.
        $os = ServiceOrder::withoutGlobalScopes() // SUPERADMIN: rota pública sem session
            ->where('id', (int) $payload['os_id'])
            ->where('business_id', (int) $payload['business_id'])
            ->first();

        if ($os === null) {
            return null;
        }

        // Apenas OS em status orcamento são elegíveis (cenário 2 do test)
        if ($os->status !== 'orcamento') {
            return null;
        }

        return $os;
    }

    /**
     * Valida PIN digitado contra hash em cache.
     *
     * Rate limit: 5 tentativas → bloqueia 30min.
     * Retorna true se OK; false se inválido OU em lockout.
     */
    public function validarPin(ServiceOrder $so, string $pin): bool
    {
        $osId       = (int) $so->id;
        $businessId = (int) $so->business_id;

        // Check lockout
        $attempts = (int) Cache::get($this->attemptsCacheKey($osId), 0);
        if ($attempts >= self::MAX_PIN_ATTEMPTS) {
            Log::warning('[AprovacaoOsService] PIN locked out', [
                'os_id'       => $osId,
                'business_id' => $businessId,
                'attempts'    => $attempts,
            ]);

            return false;
        }

        $stored = Cache::get($this->pinCacheKey($osId, $businessId));
        if ($stored === null) {
            return false; // expirou ou nunca gerado
        }

        $pin = trim($pin);
        if (! preg_match('/^\d{4}$/', $pin)) {
            $this->incrementAttempts($osId);

            return false;
        }

        if (! hash_equals($stored, hash('sha256', $pin))) {
            $this->incrementAttempts($osId);

            return false;
        }

        // Sucesso — limpa contador + invalida PIN (one-shot)
        Cache::forget($this->attemptsCacheKey($osId));
        Cache::forget($this->pinCacheKey($osId, $businessId));

        return true;
    }

    /**
     * Conta tentativas atuais (pra UI mostrar "x de 5"). Não muta estado.
     */
    public function tentativasRestantes(ServiceOrder $so): int
    {
        $attempts = (int) Cache::get($this->attemptsCacheKey((int) $so->id), 0);

        return max(0, self::MAX_PIN_ATTEMPTS - $attempts);
    }

    // ────────────────────────────────────────────────────────────────────
    // Helpers privados
    // ────────────────────────────────────────────────────────────────────

    private function sign(string $payloadB64): string
    {
        $secret = (string) config('app.key');

        return hash_hmac('sha256', $payloadB64, $secret);
    }

    private function base64UrlEncode(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $b64): string
    {
        $b64 = strtr($b64, '-_', '+/');
        $pad = strlen($b64) % 4;
        if ($pad > 0) {
            $b64 .= str_repeat('=', 4 - $pad);
        }

        return (string) base64_decode($b64, true);
    }

    private function pinCacheKey(int $osId, int $businessId): string
    {
        return "oficina:aprovacao_pin:{$osId}:{$businessId}";
    }

    private function attemptsCacheKey(int $osId): string
    {
        return "oficina:aprovacao_pin_attempts:{$osId}";
    }

    private function incrementAttempts(int $osId): void
    {
        $key = $this->attemptsCacheKey($osId);
        $cur = (int) Cache::get($key, 0);
        Cache::put($key, $cur + 1, now()->addSeconds(self::PIN_LOCKOUT_SECONDS));
    }
}
