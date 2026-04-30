<?php

namespace Modules\Copiloto\Services\Memoria;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * MEM-MEM-WIRE Phase 2 — Negative cache de queries sem resultado.
 *
 * Quando uma query retorna 0 resultados no Meilisearch, marca essa query
 * como "negativa" por N segundos. Chamadas subsequentes idênticas/similares
 * retornam [] imediatamente — evita chamadas Scout + LLM desnecessárias.
 *
 * Ganho: -100% overhead pra queries que nunca encontram nada (ex.: Larissa
 * perguntando sobre módulos não-relacionados ao negócio dela).
 *
 * Habilitado via config copiloto.negative_cache.enabled (env COPILOTO_NEGATIVE_CACHE_ENABLED).
 * TTL padrão: 300s (5 min) — queries raras não ficam bloqueadas por muito tempo.
 *
 * Chave normalizada: lowercase + sem pontuação → SHA-256. Escoped por business_id + user_id.
 */
class NegativeCacheService
{
    public function ehNegativo(int $businessId, int $userId, string $query): bool
    {
        if (! config('copiloto.negative_cache.enabled', false)) {
            return false;
        }

        $hit = Cache::has($this->cacheKey($businessId, $userId, $query));

        if ($hit) {
            Log::channel('copiloto-ai')->debug('NegativeCache: hit', [
                'business_id' => $businessId,
                'query_chars' => strlen($query),
            ]);
        }

        return $hit;
    }

    public function marcarNegativo(int $businessId, int $userId, string $query): void
    {
        if (! config('copiloto.negative_cache.enabled', false)) {
            return;
        }

        $ttl = (int) config('copiloto.negative_cache.ttl_segundos', 300);
        Cache::put($this->cacheKey($businessId, $userId, $query), true, $ttl);

        Log::channel('copiloto-ai')->debug('NegativeCache: marcado', [
            'business_id' => $businessId,
            'ttl_segundos' => $ttl,
            'query_chars'  => strlen($query),
        ]);
    }

    private function cacheKey(int $businessId, int $userId, string $query): string
    {
        // Normaliza: lowercase + remove pontuação não-alfanumérica + colapsa espaços
        $normalized = mb_strtolower(trim((string) preg_replace('/[^\p{L}\p{N}\s]/u', '', $query)));
        $normalized = (string) preg_replace('/\s+/', ' ', $normalized);

        return 'mem:neg:' . $businessId . ':' . $userId . ':' . hash('sha256', $normalized);
    }
}
