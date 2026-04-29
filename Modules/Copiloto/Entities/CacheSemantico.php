<?php

namespace Modules\Copiloto\Entities;

use Illuminate\Database\Eloquent\Model;

/**
 * MEM-CACHE-1 — Entrada de cache semântico.
 *
 * Estratégia HIT (em ordem de custo crescente):
 *   1. Lookup direto por cache_key (SHA256 query_normalizada) — exato, ~5ms
 *   2. FULLTEXT MATCH AGAINST query_normalizada — fuzzy textual, ~30ms
 *   3. Cosine similarity em query_embedding — fuzzy semântico, ~50ms
 *
 * Threshold default: 0.95 (semântico). Configurável por categoria.
 */
class CacheSemantico extends Model
{
    protected $table = 'copiloto_cache_semantico';

    protected $fillable = [
        'cache_key', 'business_id', 'user_id',
        'query_original', 'query_normalizada', 'query_embedding',
        'resposta', 'metadata',
        'hits', 'ultimo_hit_em',
        'tokens_in', 'tokens_out', 'custo_brl_original',
        'expira_em',
    ];

    protected $casts = [
        'metadata' => 'array',
        'hits' => 'integer',
        'ultimo_hit_em' => 'datetime',
        'expira_em' => 'datetime',
        'tokens_in' => 'integer',
        'tokens_out' => 'integer',
        'custo_brl_original' => 'float',
    ];

    public function scopeNaoExpirado($q)
    {
        return $q->where(function ($qq) {
            $qq->whereNull('expira_em')->orWhere('expira_em', '>', now());
        });
    }

    public function scopeDoEscopo($q, ?int $businessId, ?int $userId)
    {
        // Cache é por (business, user) — nunca cross-tenant
        return $q->where(function ($qq) use ($businessId, $userId) {
            $qq->where('business_id', $businessId)
               ->where('user_id', $userId);
        });
    }

    /**
     * Calcula custo bruto economizado total (todos os hits acumulados).
     */
    public function totalEconomizado(): float
    {
        return ($this->hits ?? 0) * (float) ($this->custo_brl_original ?? 0);
    }

    /**
     * Marca um hit (incrementa contador + atualiza last_hit).
     */
    public function registrarHit(): void
    {
        $this->increment('hits');
        $this->update(['ultimo_hit_em' => now()]);
    }
}
