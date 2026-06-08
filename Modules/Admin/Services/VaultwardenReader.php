<?php

namespace Modules\Admin\Services;

use App\Util\OtelHelper;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * VaultwardenReader — Widget W7 (Vaultwarden cofre).
 *
 * Vaultwarden self-hosted (Bitwarden API-compatible v1.35.x) em
 * vault.oimpresso.com:8200 (DNS) ou 192.168.0.50:8200 (LAN).
 * ADMIN_TOKEN em auto-mem `reference_vaultwarden_credenciais.md` (Wagner).
 *
 * Endpoints (Bitwarden API compat):
 * - GET /api/accounts/profile (valida token)
 * - GET /api/ciphers (lista itens — id, name, type, folderId, custom_fields)
 *
 * Sprint 2 W7: count itens + scan tag "cert-vencendo" / custom field
 * "expiry_date" <= now+30d (Bitwarden não tem expiration nativo — convention).
 *
 * Cache 5 min. Graceful fallback se ADMIN_TOKEN ausente / endpoint offline.
 *
 * @see memory/decisions/0044-vaultwarden-self-hosted-cofre.md
 */
class VaultwardenReader
{
    public function fetch(): array
    {
        // D9.a OTel (Wave 17): span envolve HTTP Vaultwarden API.
        // Zero-cost se otel.enabled=false.
        return OtelHelper::spanBiz('admin.vaultwarden.fetch', function () {
        return Cache::remember('admin.widget.vaultwarden', 300, function () {
            $url   = config('admin.vaultwarden_url', 'http://192.168.0.50:8200');
            $token = config('admin.vaultwarden_admin_token');

            if (! $token) {
                return $this->stub('admin_token_missing');
            }

            try {
                $start = microtime(true);
                $response = Http::timeout(3)
                    ->withHeaders(['Authorization' => "Bearer {$token}"])
                    ->get("{$url}/api/ciphers");
                $latency = (int) ((microtime(true) - $start) * 1000);

                if (! $response->successful()) {
                    return $this->stub("http_{$response->status()}");
                }

                $body = $response->json() ?? [];
                $ciphers = $body['Data'] ?? $body['data'] ?? (is_array($body) ? $body : []);
                $total = is_array($ciphers) ? count($ciphers) : 0;

                $expiringSoon = $this->countExpiringSoon($ciphers);

                return [
                    'available'      => true,
                    'reachable'      => true,
                    'latency_ms'     => $latency,
                    'ciphers_total'  => $total,
                    'expiring_30d'   => $expiringSoon,
                    'url'            => $url,
                ];
            } catch (\Throwable $e) {
                Log::warning('admin.widget.vaultwarden.error', ['error' => $e->getMessage()]);
                return $this->stub('exception:' . substr($e->getMessage(), 0, 100));
            }
        });
        }, ['component' => 'admin.widget.w7']);
    }

    /**
     * Scan custom_fields por convention "expiry_date" ISO date.
     * Bitwarden não tem campo nativo — depende do user adicionar.
     */
    private function countExpiringSoon(array $ciphers): int
    {
        $threshold = now()->addDays(30)->timestamp;
        $count = 0;
        foreach ($ciphers as $cipher) {
            $fields = $cipher['Fields'] ?? $cipher['fields'] ?? [];
            if (! is_array($fields)) continue;
            foreach ($fields as $field) {
                $name = strtolower($field['Name'] ?? $field['name'] ?? '');
                $value = $field['Value'] ?? $field['value'] ?? null;
                if (in_array($name, ['expiry_date', 'expires_at', 'vencimento'], true) && $value) {
                    $ts = strtotime($value);
                    if ($ts && $ts <= $threshold && $ts >= now()->timestamp) {
                        $count++;
                    }
                }
            }
        }
        return $count;
    }

    private function stub(string $reason): array
    {
        return [
            'available'    => false,
            'reachable'    => false,
            'reason'       => $reason,
            'ciphers_total' => 0,
            'expiring_30d' => 0,
            'instructions' => 'Configure VAULTWARDEN_ADMIN_TOKEN no .env (auto-mem reference_vaultwarden_credenciais.md).',
        ];
    }
}
