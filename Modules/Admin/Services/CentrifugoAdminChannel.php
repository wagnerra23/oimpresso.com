<?php

declare(strict_types=1);

namespace Modules\Admin\Services;

use Illuminate\Support\Facades\Log;

/**
 * CentrifugoAdminChannel — esqueleto Wave 23 (G1 FICHA W22).
 *
 * Substituirá polling 5min do Admin/Index pelo canal Centrifugo
 * `admin.wagner` (push em vez de pull). Permite que widgets reajam a eventos
 * em tempo real:
 *   - novo brief diário pronto → atualizar W1 sem refresh
 *   - jana:health-check falha → badge vermelho instantâneo
 *   - novo violation ActionGate → toast notification
 *
 * **Wave 23 = esqueleto** (sem dispatch real). Wave 24 (full impl): integrar
 * com `Modules/Centrifugo/Services/CentrifugoPublisher` + listener no
 * Admin/Index.tsx via `@centrifuge/centrifuge-js`.
 *
 * Tier 0:
 *  - Canal `admin.wagner` é PRIVADO (Centrifugo private channel) — token JWT
 *    é assinado com `admin.user_id=Wagner` no claim `sub`
 *  - Demais usuários NUNCA podem subscribar (server enforces via
 *    HMAC verify) — defense in depth com middleware `is-wagner`
 *  - NÃO publicar payloads com PII real (CPF/CNPJ/email) — usar IDs apenas
 *  - Runtime: CT 100 (Centrifugo lá, NÃO Hostinger — ADR 0062)
 *
 * @see memory/decisions/0058-reverb-substituido-por-centrifugo-frankenphp.md
 * @see memory/decisions/0122-admin-center-ct100.md
 */
class CentrifugoAdminChannel
{
    public const CHANNEL_NAME = 'admin.wagner';

    /**
     * Publica evento no canal admin.wagner.
     *
     * **Wave 23**: apenas loga via Log channel `stack` (esqueleto fail-open).
     * **Wave 24**: integrar com CentrifugoPublisher real.
     *
     * @param  string  $event  Nome do evento (snake_case): brief_updated, health_failed, violation_logged.
     * @param  array<string,mixed>  $payload  Payload SEM PII (IDs + counts apenas).
     */
    public function publish(string $event, array $payload = []): bool
    {
        // Defesa básica: never push PII real (Tier 0 ADR 0093 + ADR 0094 §PII).
        $payload = $this->stripPotentialPii($payload);

        $entry = [
            'channel'      => self::CHANNEL_NAME,
            'event'        => $event,
            'payload'      => $payload,
            'published_at' => now()->toIso8601String(),
            'wave'         => 23,
            'status'       => 'esqueleto-not-dispatched',
        ];

        // Wave 23 fail-open: apenas log estruturado. Wave 24 substitui pelo
        // CentrifugoPublisher::publish(CHANNEL_NAME, $event, $payload).
        Log::channel('stack')->info('admin.centrifugo.publish', $entry);

        return true;
    }

    /**
     * Lista eventos canônicos previstos pra Wave 24 (contract estável).
     *
     * @return array<string,string>
     */
    public function events(): array
    {
        return [
            'brief_updated'      => 'mcp_briefs novo brief diário publicado',
            'health_failed'      => 'jana:health-check 1+ check FAIL (any of 5)',
            'violation_logged'   => 'ActionGate violation nova (route + actor)',
            'mcp_token_regen'    => 'MCP token rotacionado via Admin mutation',
            'curador_applied'    => 'Batch curador aplicado (count + duration)',
            'feature_flag_set'   => 'Feature flag biz-rule mudou (key + biz_id)',
        ];
    }

    /**
     * Strip campos potencialmente PII (defesa básica).
     *
     * Whitelist canônica (campos seguros): IDs numéricos, counts, status
     * enums, datas. Tudo mais vira `[REDACTED-CHANNEL]`.
     *
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    private function stripPotentialPii(array $payload): array
    {
        $piiKeys = ['cpf', 'cnpj', 'email', 'telefone', 'phone', 'address', 'document', 'tax_number'];

        foreach ($payload as $key => $value) {
            if (in_array(strtolower((string) $key), $piiKeys, true)) {
                $payload[$key] = '[REDACTED-CHANNEL]';
            }

            // Strings longas que parecem JSON/CSV — também redacta defensivamente
            if (is_string($value) && strlen($value) > 200) {
                $payload[$key] = '[REDACTED-LONG-STRING]';
            }
        }

        return $payload;
    }
}
