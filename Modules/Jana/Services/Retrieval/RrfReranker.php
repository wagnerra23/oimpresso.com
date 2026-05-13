<?php

declare(strict_types=1);

namespace Modules\Jana\Services\Retrieval;

use Illuminate\Support\Facades\Log;

/**
 * RrfReranker — Reciprocal Rank Fusion canônico (Cormack 2009, k=60).
 *
 * Driver default do contrato Reranker — MVP zero-custo / zero-latência / zero-deps.
 *
 * Funciona em 2 cenários:
 *
 *  1. **Multi-source merge** (HyDE/hybrid): candidato traz `rank_sources` (array de ranks
 *     por source) → soma RRF cross-source (literatura clássica RRF).
 *
 *  2. **Single-source rerank** (fallback): candidato traz só `score` flat → usa ordem de
 *     entrada como rank implícito + boost por score relativo (degenera pra topK
 *     respeitando ordem, mas com `score_rerank` calibrado 0..1).
 *
 * Trade-off vs LLM/cross-encoder:
 *   - GANHO: latência ~1ms (sort + map), custo R$ [redacted Tier 0] sem dep externa, idempotente puro
 *   - PERDA: ~5pp NDCG@10 vs BGE/Cohere/LLM (literatura — sem semantic awareness)
 *
 * Quando upgrade pra LlmRerankerAdapter: quando `jana:health-check` detectar
 * recall@5 estagnado <0.6 OU Wagner pedir explicitamente. RAGAS eval matrix
 * em [memory/requisitos/Jana/RAGAS-EVAL.md] (referência futura).
 *
 * Referências:
 *   - Cormack et al. 2009 — "Reciprocal Rank Fusion outperforms Condorcet and individual Rank Learning"
 *   - [AUDITORIA-KNOWLEDGE-ARCHITECTURE-2026-05-13.md §5 G3]
 */
class RrfReranker implements Reranker
{
    /** Constante canônica RRF (Cormack 2009 — robusta entre 40-100, 60 é o sweet-spot). */
    private const RRF_K = 60;

    public function reranquear(string $query, array $candidatos, int $topK = 5): array
    {
        if (empty($candidatos) || $topK <= 0) {
            return [];
        }

        // Calcula RRF score por id
        $scores  = [];
        $payload = [];

        foreach ($candidatos as $rank => $candidato) {
            $id = $candidato['id'] ?? null;
            if ($id === null) {
                continue;
            }

            $payload[$id] = $candidato;

            // Caso 1: candidato tem rank_sources (multi-source merge)
            if (isset($candidato['rank_sources']) && is_array($candidato['rank_sources'])) {
                $somaRrf = 0.0;
                foreach ($candidato['rank_sources'] as $rankNaSource) {
                    $somaRrf += 1.0 / (self::RRF_K + (int) $rankNaSource + 1);
                }
                $scores[$id] = ($scores[$id] ?? 0.0) + $somaRrf;

                continue;
            }

            // Caso 2: single-source — ordem de entrada é o rank
            $scores[$id] = ($scores[$id] ?? 0.0) + 1.0 / (self::RRF_K + $rank + 1);
        }

        // Sort desc por score (estável: PHP arsort preserva ordem de entrada em empates → idempotente)
        arsort($scores);

        $topIds = array_slice(array_keys($scores), 0, $topK);

        // Normaliza score_rerank pra 0..1 (max=1.0, demais escalados pelo score do top-1)
        $maxScore = empty($topIds) ? 1.0 : $scores[$topIds[0]];
        if ($maxScore <= 0.0) {
            $maxScore = 1.0;
        }

        $reordenados = [];
        foreach ($topIds as $id) {
            $candidato                  = $payload[$id];
            $candidato['score_rerank']  = round($scores[$id] / $maxScore, 4);
            $reordenados[]              = $candidato;
        }

        Log::channel('copiloto-ai')->debug('RrfReranker::reranquear', [
            'query_chars'    => strlen($query),
            'candidatos_in'  => count($candidatos),
            'candidatos_out' => count($reordenados),
            'top_k'          => $topK,
            'driver'         => 'rrf',
        ]);

        return $reordenados;
    }
}
