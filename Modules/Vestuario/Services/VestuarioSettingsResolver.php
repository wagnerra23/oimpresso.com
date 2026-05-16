<?php

namespace Modules\Vestuario\Services;

use App\Util\OtelHelper;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Modules\Vestuario\Entities\VestuarioSetting;

/**
 * VestuarioSettingsResolver — API canônica pra outros módulos consultarem
 * settings do vertical Vestuario per-business sem importar Model direto.
 *
 * Sprint 2 ADR 0121 §P7. Foi pensado pra vir depois da primeira migration
 * (PR #401 mergeado).
 *
 * Uso:
 *   $resolver = app(VestuarioSettingsResolver::class);
 *   $shift = $resolver->get('format_date_shift_hours', default: 0);
 *   $value = $resolver->get('feature_x.threshold', default: 100);
 *
 *   // Override per-business em test/admin context:
 *   $resolver->forBusiness(4)->get('format_date_shift_hours', 0);
 *
 * Cache: 5min por business (settings JSON raramente muda).
 *
 * @see Modules/Vestuario/Entities/VestuarioSetting.php
 * @see memory/decisions/0121-oimpresso-modular-especializado-por-vertical.md §P7
 */
class VestuarioSettingsResolver
{
    private const CACHE_TTL = 300; // 5 min

    private ?int $overrideBusinessId = null;

    /**
     * Override business_id pra próxima chamada (chainable).
     * Útil em jobs/CLI que rodam fora session web.
     */
    public function forBusiness(int $businessId): self
    {
        $clone = clone $this;
        $clone->overrideBusinessId = $businessId;
        return $clone;
    }

    /**
     * Get setting com default fallback. Cache 5min per business.
     *
     * Aceita dot notation: `get('feature.x.threshold')` busca aninhado em settings JSON.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $businessId = $this->resolveBusinessId();
        if ($businessId === null) {
            return $default;
        }

        return OtelHelper::spanBiz('vestuario.settings.get', function () use ($key, $default, $businessId) {
            $settings = $this->loadSettings($businessId);
            return data_get($settings, $key, $default);
        }, [
            'module'       => 'Vestuario',
            'setting_key'  => $key,
            'business_id'  => $businessId,
        ]);
    }

    /**
     * Get inteiro com default safe (cast + bounds opcionais).
     */
    public function getInt(string $key, int $default = 0, ?int $min = null, ?int $max = null): int
    {
        $value = (int) $this->get($key, $default);
        if ($min !== null && $value < $min) return $default;
        if ($max !== null && $value > $max) return $default;
        return $value;
    }

    /**
     * Get bool com default safe (truthy strings 'true'/'1'/'yes' aceitas).
     */
    public function getBool(string $key, bool $default = false): bool
    {
        $value = $this->get($key, $default);
        if (is_bool($value)) return $value;
        if (is_string($value)) {
            return in_array(strtolower($value), ['true', '1', 'yes', 'sim', 'on'], true);
        }
        return (bool) $value;
    }

    /**
     * Set setting (auto-saves + invalida cache).
     */
    public function set(string $key, mixed $value): self
    {
        $businessId = $this->resolveBusinessId();
        if ($businessId === null) {
            return $this;
        }

        OtelHelper::spanBiz('vestuario.settings.set', function () use ($key, $value, $businessId) {
            $row = VestuarioSetting::firstOrCreate(['business_id' => $businessId], ['settings' => []]);
            $row->set($key, $value);
            $this->invalidateCache($businessId);

            // D9.b log estruturado: setting alterado (sem expor valor — só chave + tipo + biz).
            Log::info('vestuario.settings.changed', [
                'business_id' => $businessId,
                'setting_key' => $key,
                'value_type'  => is_scalar($value) ? gettype($value) : 'compound',
            ]);
        }, [
            'module'       => 'Vestuario',
            'setting_key'  => $key,
            'business_id'  => $businessId,
        ]);

        return $this;
    }

    /**
     * Limpa cache do business atual (use após mudança via SQL externo).
     */
    public function refresh(): self
    {
        $businessId = $this->resolveBusinessId();
        if ($businessId !== null) {
            $this->invalidateCache($businessId);
        }
        return $this;
    }

    private function resolveBusinessId(): ?int
    {
        if ($this->overrideBusinessId !== null) {
            return $this->overrideBusinessId;
        }
        return session('user.business_id') ?? session('business.id');
    }

    private function loadSettings(int $businessId): array
    {
        return Cache::remember(
            $this->cacheKey($businessId),
            self::CACHE_TTL,
            function () use ($businessId): array {
                try {
                    $row = VestuarioSetting::query()
                        ->withoutGlobalScopes(['business_id']) // SUPERADMIN: resolver pode rodar fora sessão (jobs/CLI)
                        ->where('business_id', $businessId)
                        ->first();
                    return $row?->settings ?? [];
                } catch (\Throwable) {
                    return [];
                }
            }
        );
    }

    private function invalidateCache(int $businessId): void
    {
        Cache::forget($this->cacheKey($businessId));
    }

    private function cacheKey(int $businessId): string
    {
        return "vestuario.settings.{$businessId}";
    }
}
