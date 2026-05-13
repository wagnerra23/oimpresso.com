<?php

declare(strict_types=1);

namespace Modules\Jana\Services\Retrieval;

use Modules\Jana\Services\Memoria\LlmReranker;

/**
 * LlmRerankerAdapter — bridge entre interface canônica Reranker e LlmReranker (Sprint 10).
 *
 * Mantém compat com `Modules\Jana\Services\Memoria\LlmReranker` (LLM-as-judge gpt-4o-mini)
 * que já está em prod desde MEM-S10-2 (ADR 0037), mas expõe via contrato `Reranker`
 * pra orquestração unificada no `MeilisearchDriver`.
 *
 * Trade-off: ~200ms latência, R$ [redacted Tier 0]/rerank, +5pp recall@5 vs RRF.
 *
 * Ativa via `config('copiloto.reranker.driver') === 'llm'` (env COPILOTO_RERANKER_DRIVER=llm).
 * Default = `rrf` (RrfReranker — zero custo).
 */
class LlmRerankerAdapter implements Reranker
{
    public function __construct(private LlmReranker $inner) {}

    public function reranquear(string $query, array $candidatos, int $topK = 5): array
    {
        if (empty($candidatos) || $topK <= 0) {
            return [];
        }

        $reordenados = $this->inner->reranquear($query, $candidatos, $topK);

        // Garante que todos têm score_rerank (LlmReranker já adiciona, mas defensivo)
        foreach ($reordenados as &$c) {
            if (! isset($c['score_rerank'])) {
                $c['score_rerank'] = 0.0;
            }
        }

        return $reordenados;
    }
}
