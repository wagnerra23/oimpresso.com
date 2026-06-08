<?php

namespace Modules\Jana\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * MEM-CACHE-1 — Entrada de cache semântico.
 *
 * Estratégia HIT (em ordem de custo crescente):
 *   1. Lookup direto por cache_key (SHA256 query_normalizada) — exato, ~5ms
 *   2. FULLTEXT MATCH AGAINST query_normalizada — fuzzy textual, ~30ms
 *   3. Cosine similarity em query_embedding — fuzzy semântico, ~50ms
 *
 * Threshold default: 0.95 (semântico). Configurável por categoria.
 *
 * D7 LGPD audit trail — Wave 10 (2026-05-16): LogsActivity registra
 * expiração + hits acumulados. NÃO loga query/resposta (texto livre).
 * Retention 90d (Config/retention.php) — cache derivado regenerável.
 */
class CacheSemantico extends Model
{
    use HasBusinessScope;
    use LogsActivity;

    protected $table = 'jana_cache_semantico';

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

    /**
     * D7 LGPD audit — metricas de cache (hits, expiração, custo). NÃO loga
     * query_original/resposta (texto livre PII-relevante). Cache key (hash)
     * é determinístico e seguro pra trilha.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('jana_cache_semantico')
            ->logOnly(['cache_key', 'hits', 'ultimo_hit_em', 'expira_em', 'custo_brl_original'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

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
