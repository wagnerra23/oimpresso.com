<?php

namespace App\Services;

use Growthbook\Growthbook;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Feature flag service — wrapper sobre GrowthBook OSS self-hosted (CT 100).
 *
 * Implementa US-INFRA-001 (memory/requisitos/Infra/SPEC.md) — substitui flags
 * ad-hoc em pos_settings JSON por sistema dedicado com percentage rollout +
 * segmentação por business/user + audit trail.
 *
 * Loop fechado: ADS recomenda "ativa pra 10% dos sells em biz=4" → toggle no
 * GrowthBook UI → este service consome via cache 60s + fallback offline-safe.
 *
 * Refs: ADR 0104 (MWART canônico), ADR 0105 (cliente como sinal),
 *       ADR 0106 (recalibração 10x).
 */
class FeatureFlagService
{
    private const CACHE_KEY = 'growthbook.features';

    private const CACHE_TTL_SECONDS = 60;

    private const FETCH_TIMEOUT_SECONDS = 5;

    /**
     * Defaults seguros — se GrowthBook estiver inacessível, estes valores
     * são retornados em vez de quebrar a aplicação.
     *
     * Wagner 2026-05-27: useV2SellsCreate → true após cadeia de hotfixes
     * resolverem todos bugs do rollback original (2026-05-13):
     *   PRs #1716 boot · #1719 route:cache · #1721 SQL · #1726 script PHP
     *   #1729 CustomerSearch · #1732 Status · #1733 AlertDialog · #1746 i18n
     *   #1752 remover hardcode V2-off · #1753 saldo devedor · #1754 price_group
     *   #1755 agrupar variações · #1758 barcode/lot · #1756 quick-add Sheet
     * Wagner: "ative para todos pronto" → flip fallback default p/ TRUE.
     */
    private array $fallbackDefaults = [
        'useV2SellsCreate' => true,
    ];

    public function isOn(string $flag, array $attrs = []): bool
    {
        try {
            $features = $this->getFeatures();

            // Fallback offline-safe: usar o default seguro quando o GrowthBook
            // está inacessível (null) OU quando ele NÃO conhece esta flag
            // (features vazio / flag ausente). Sem o `array_key_exists`, quando
            // o GrowthBook do CT 100 cai / responde non-2xx / não tem a flag
            // definida, getFeatures() devolve [] (não null) e $gb->isOn()
            // retorna false silenciosamente — derrubando v2 (tela React de
            // venda) pro Blade legado pra TODOS os business, ignorando o
            // fallbackDefaults=true. Catalogado 2026-06-04 (tela /sells/create
            // do Martinho reverteu pro layout antigo).
            if ($features === null || ! array_key_exists($flag, $features)) {
                return $this->fallback($flag);
            }

            $gb = Growthbook::create()
                ->withFeatures($features)
                ->withAttributes($attrs);

            return $gb->isOn($flag);
        } catch (Throwable $e) {
            Log::warning('FeatureFlagService: erro ao avaliar flag, usando fallback', [
                'flag' => $flag,
                'error' => $e->getMessage(),
            ]);
            return $this->fallback($flag);
        }
    }

    /**
     * Limpa cache local — útil pra forçar refresh imediato após toggle no UI
     * GrowthBook (TTL padrão 60s pode ser longo demais em emergência).
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private function getFeatures(): ?array
    {
        $sdkKey = (string) env('GROWTHBOOK_SDK_KEY', '');
        $apiHost = (string) env('GROWTHBOOK_API_HOST', '');

        if ($sdkKey === '' || $apiHost === '') {
            return null;
        }

        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function () use ($sdkKey, $apiHost) {
            $url = rtrim($apiHost, '/') . '/api/features/' . $sdkKey;

            $response = Http::timeout(self::FETCH_TIMEOUT_SECONDS)->get($url);
            if (! $response->successful()) {
                Log::warning('FeatureFlagService: GrowthBook respondeu não-2xx', [
                    'status' => $response->status(),
                ]);
                return [];
            }

            return (array) $response->json('features', []);
        });
    }

    private function fallback(string $flag): bool
    {
        return $this->fallbackDefaults[$flag] ?? false;
    }
}
