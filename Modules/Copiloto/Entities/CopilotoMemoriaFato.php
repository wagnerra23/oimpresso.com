<?php

namespace Modules\Copiloto\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

/**
 * CopilotoMemoriaFato — fato persistente sobre user/business pro Copiloto lembrar.
 *
 * Tabela: copiloto_memoria_facts
 * Multi-tenant: business_id + user_id (US-COPI-MEM-005)
 * Temporal: valid_from / valid_until (futuro: US-COPI-MEM-009 — Mem0/Zep upgrade)
 * LGPD: SoftDeletes — esquecer() = soft delete
 *
 * Indexa em Meilisearch via Scout. toSearchableArray controla o que vai pro index.
 *
 * Ver ADRs 0031/0033/0036.
 */
class CopilotoMemoriaFato extends Model
{
    use Searchable;
    use SoftDeletes;

    protected $table = 'copiloto_memoria_facts';

    protected $fillable = [
        'business_id',
        'user_id',
        'fato',
        'metadata',
        'valid_from',
        'valid_until',
    ];

    protected $casts = [
        'metadata' => 'array',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
    ];

    public function searchableAs(): string
    {
        return 'copiloto_memoria_facts';
    }

    /**
     * Campos indexados em Meilisearch.
     * Embeddings são gerados pelo Meilisearch via embedder configurado no index
     * (provider: openAi, model: text-embedding-3-small) — ver config/scout.php
     * + Modules/Copiloto/Database/seeders/MeilisearchIndexSetup.php (opcional).
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
