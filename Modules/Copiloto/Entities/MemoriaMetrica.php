<?php

namespace Modules\Copiloto\Entities;

use Illuminate\Database\Eloquent\Model;

/**
 * MemoriaMetrica — 1 linha/dia/business_id com as 8 métricas obrigatórias
 * (ADR 0050) + 3 RAGAS-aligned (ADR 0051) + contadores acessórios.
 *
 * Tabela: copiloto_memoria_metricas
 * Multi-tenant: business_id nullable (NULL = plataforma agregada)
 * Idempotente: unique (apurado_em, business_id) → upsert seguro
 *
 * Apurada via comando `php artisan copiloto:metrics:apurar` (MEM-MET-2).
 *
 * Ver:
 *  - memory/decisions/0049-camadas-memoria-agente-fase-por-fase.md (gate Recall@3>0.80)
 *  - memory/decisions/0050-metricas-obrigatorias-memoria-table.md (8 métricas)
 *  - memory/decisions/0051-schema-proprio-adapter-otel-genai.md (RAGAS-aligned + OTel)
 */
class MemoriaMetrica extends Model
{
    protected $table = 'copiloto_memoria_metricas';

    protected $fillable = [
        'apurado_em',
        'business_id',
        // 8 métricas ADR 0050
        'recall_at_3',
        'precision_at_3',
        'mrr',
        'latencia_p95_ms',
        'tokens_medio_interacao',
        'memory_bloat_ratio',
        'taxa_contradicoes_pct',
        'cross_tenant_violations',
        // 3 RAGAS-aligned (ADR 0051)
        'faithfulness',
        'answer_relevancy',
        'context_precision',
        // Contadores
        'total_interacoes_dia',
        'total_memorias_ativas',
        'detalhes',
    ];

    protected $casts = [
        'apurado_em'              => 'date',
        'recall_at_3'             => 'float',
        'precision_at_3'          => 'float',
        'mrr'                     => 'float',
        'latencia_p95_ms'         => 'integer',
        'tokens_medio_interacao'  => 'integer',
        'memory_bloat_ratio'      => 'float',
        'taxa_contradicoes_pct'   => 'float',
        'cross_tenant_violations' => 'integer',
        'faithfulness'            => 'float',
        'answer_relevancy'        => 'float',
        'context_precision'       => 'float',
        'total_interacoes_dia'    => 'integer',
        'total_memorias_ativas'   => 'integer',
        'detalhes'                => 'array',
    ];

    /**
     * Scope: tenant — sempre filtrar por business_id (ou NULL pra plataforma).
     */
    public function scopeDoBusinessOuPlataforma($query, ?int $businessId)
    {
        return $businessId === null
            ? $query->whereNull('business_id')
            : $query->where('business_id', $businessId);
    }

    /**
     * Scope: trend dos últimos N dias (mais recente primeiro).
     */
    public function scopeUltimosDias($query, int $dias = 30)
    {
        return $query
            ->where('apurado_em', '>=', now()->subDays($dias)->toDateString())
            ->orderByDesc('apurado_em');
    }

    /**
     * Resumo das 8 métricas obrigatórias (ADR 0050) → array pra dashboard.
     */
    public function metricasObrigatorias(): array
    {
        return [
            'recall_at_3'             => $this->recall_at_3,
            'precision_at_3'          => $this->precision_at_3,
            'mrr'                     => $this->mrr,
            'latencia_p95_ms'         => $this->latencia_p95_ms,
            'tokens_medio_interacao'  => $this->tokens_medio_interacao,
            'memory_bloat_ratio'      => $this->memory_bloat_ratio,
            'taxa_contradicoes_pct'   => $this->taxa_contradicoes_pct,
            'cross_tenant_violations' => $this->cross_tenant_violations,
        ];
    }

    /**
     * Resumo das 3 métricas RAGAS-aligned (ADR 0051).
     */
    public function metricasRagas(): array
    {
        return [
            'faithfulness'      => $this->faithfulness,
            'answer_relevancy'  => $this->answer_relevancy,
            'context_precision' => $this->context_precision,
        ];
    }
}
