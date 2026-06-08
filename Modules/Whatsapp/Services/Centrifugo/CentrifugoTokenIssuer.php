<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Services\Centrifugo;

/**
 * CentrifugoTokenIssuer — emite JWT HS256 puro (sem dep) pra subscribe channel.
 *
 * Centrifugo aceita JWT HS256 nativo: header `{"typ":"JWT","alg":"HS256"}` +
 * payload `{"sub":"<user_id>","channels":["whatsapp:business:4"],"exp":<unix>}`.
 *
 * Implementação minimal sem firebase/php-jwt — evita nova dep Composer.
 * Token rotaciona a cada page load (TTL curto, default 1h) — re-emitido
 * pelo backend a cada Inertia render do Show.tsx.
 *
 * Observabilidade D9.a (ADR 0155): emissão de token é HMAC local sub-µs —
 * Tracer overhead injustificável; mas `OtelHelper::span(` está disponível
 * para envolver caso passe a chamar Centrifugo upstream.
 *
 * @see https://centrifugal.dev/docs/server/authentication
 * @see ADR 0058 (Centrifugo CT 100)
 */
class CentrifugoTokenIssuer
{
    /**
     * Emite JWT pro user atual subscriber em channels específicos.
     *
     * @param  array<int, string>  $channels  Lista de channels permitidos (ex: ['whatsapp:business:4'])
     * @param  int  $ttlSeconds  Validade do token (default 3600 = 1h)
     */
    public function issue(int $userId, array $channels, int $ttlSeconds = 3600): ?string
    {
        $secret = (string) config('whatsapp.centrifugo.token_hmac_secret', '');
        if ($secret === '') {
            return null;
        }

        $header = [
            'typ' => 'JWT',
            'alg' => 'HS256',
        ];

        $payload = [
            'sub' => (string) $userId,
            'channels' => array_values($channels),
            'exp' => time() + $ttlSeconds,
            'iat' => time(),
        ];

        $b64Header = $this->base64UrlEncode((string) json_encode($header, JSON_UNESCAPED_SLASHES));
        $b64Payload = $this->base64UrlEncode((string) json_encode($payload, JSON_UNESCAPED_SLASHES));
        $signingInput = $b64Header . '.' . $b64Payload;

        $signature = hash_hmac('sha256', $signingInput, $secret, true);
        $b64Signature = $this->base64UrlEncode($signature);

        return $signingInput . '.' . $b64Signature;
    }

    /**
     * Valida token (decode + verify HMAC + verify exp). Útil pra testes.
     *
     * @return array<string, mixed>|null  Payload se válido; null se inválido/expirado
     */
    public function verify(string $token): ?array
    {
        $secret = (string) config('whatsapp.centrifugo.token_hmac_secret', '');
        if ($secret === '') {
            return null;
        }

        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$b64Header, $b64Payload, $b64Signature] = $parts;
        $signingInput = $b64Header . '.' . $b64Payload;

        $expectedSignature = hash_hmac('sha256', $signingInput, $secret, true);
        $actualSignature = $this->base64UrlDecode($b64Signature);

        if (! hash_equals($expectedSignature, $actualSignature)) {
            return null;
        }

        $payload = json_decode($this->base64UrlDecode($b64Payload), true);
        if (! is_array($payload)) {
            return null;
        }

        if (isset($payload['exp']) && (int) $payload['exp'] < time()) {
            return null;
        }

        return $payload;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        $padded = str_pad($data, strlen($data) + ((4 - strlen($data) % 4) % 4), '=');
        return (string) base64_decode(strtr($padded, '-_', '+/'));
    }
}
