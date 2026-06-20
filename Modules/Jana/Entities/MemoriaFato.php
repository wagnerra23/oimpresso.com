<?php

namespace Modules\Jana\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * MemoriaFato — fato persistente sobre user/business pra Jana lembrar.
 *
 * Tabela: jana_memoria_facts (rename ADR 0092; legado copiloto_memoria_facts via VIEW 30d)
 * Multi-tenant: business_id + user_id (US-COPI-MEM-005)
 * Temporal: valid_from / valid_until (futuro: US-COPI-MEM-009 — Mem0/Zep upgrade)
 * LGPD: SoftDeletes — esquecer() = soft delete
 *
 * Indexa em Meilisearch via Scout. toSearchableArray controla o que vai pro index.
 *
 * Ver ADRs 0031/0033/0036/0090.
 */
class MemoriaFato extends Model
{
    use HasBusinessScope;

    use LogsActivity;
    use Searchable;
    use SoftDeletes;

    protected $table = 'jana_memoria_facts';

    /**
     * Wave P — auditoria LGPD ciclo de vida temporal do fato.
     *
     * Schema canônico não tem `relevance` nem `consolidated_at` — janela de
     * validade é `valid_from`/`valid_until` (US-COPI-MEM-005, ADR 0036).
     * Logga consolidação (valid_until set), retirada de circulação e LGPD
     * esquecer() via `deleted_at`. NÃO logga `fato`/`metadata` (PII livre).
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('jana_memoria_fato')
            ->logOnly(['valid_from', 'valid_until', 'deleted_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $fillable = [
        'business_id',
        'user_id',
        'fato',
        'metadata',
        'valid_from',
        'valid_until',
        'event_valid_from',  // event-time bi-temporal (ADR 0295)
        'event_valid_until',
        'supersedes_id',
    ];

    protected $casts = [
        'metadata' => 'array',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
        'event_valid_from' => 'datetime',  // ADR 0295
        'event_valid_until' => 'datetime',
        'supersedes_id' => 'integer',
    ];

    public function searchableAs(): string
    {
        return 'jana_memoria_facts';
    }

    /**
     * Campos indexados em Meilisearch.
     * Embeddings são gerados pelo Meilisearch via embedder configurado no index
     * (provider: openAi, model: text-embedding-3-small) — ver config/scout.php
     * + Modules/Jana/Database/seeders/MeilisearchIndexSetup.php (opcional).
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'business_id' => $this->business_id,
            'user_id' => $this->user_id,
            'fato' => $this->fato,
            'metadata_json' => json_encode($this->metadata ?? []),
            'valid_from' => $this->valid_from?->timestamp,
            'valid_until' => $this->valid_until?->timestamp,
        ];
    }

    /**
     * Só indexa fatos ativos (valid_until = NULL).
     */
    public function shouldBeSearchable(): bool
    {
        return $this->valid_until === null && $this->deleted_at === null;
    }

    /**
     * Scope: só fatos ativos (valid_until = NULL ou no futuro).
     */
    public function scopeAtivos($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('valid_until')->orWhere('valid_until', '>', now());
        });
    }

    /**
     * Scope: tenant — sempre filtrar por business + user.
     */
    public function scopeDoUser($query, int $businessId, int $userId)
    {
        return $query->where('business_id', $businessId)->where('user_id', $userId);
    }
}
