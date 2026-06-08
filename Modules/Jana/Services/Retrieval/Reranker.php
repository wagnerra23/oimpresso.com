<?php

declare(strict_types=1);

namespace Modules\Jana\Services\Retrieval;

/**
 * Reranker — contrato canônico de reranking pós-retrieval (GAP-A — AUDITORIA 2026-05-13 §5 G3).
 *
 * Recebe candidatos já recuperados (BM25/vector/hybrid) e devolve subset reordenado
 * por relevância à query. Implementações canônicas:
 *
 *   - RrfReranker        — Reciprocal Rank Fusion (algorítmico, zero custo, zero latência)
 *   - LlmRerankerAdapter — LLM-as-judge (gpt-4o-mini, ~200ms, R$ 0,0005/rerank)
 *   - NullReranker       — passthrough top-K (default desabilitado)
 *
 * Trade-offs documentados em [AUDITORIA-KNOWLEDGE-ARCHITECTURE-2026-05-13.md] §5 G3:
 *   - Cohere Rerank 3.5 cloud: NDCG@10 ~0.735, $2/1k, 100-300ms — REJEITADO custo
 *   - BGE-reranker-v2-m3 self-hosted: NDCG@10 ~0.715, $0,35/1k, 50-100ms (GPU) — REJEITADO infra
 *   - RRF: NDCG@10 ~0.68 (algorítmico), $0, <5ms — ESCOLHIDO MVP
 *
 * Pattern (industry-standard 2026): RRF como first-stage merge + LLM rerank precision top-N.
 * Aqui RRF é o driver MVP; quando custo/latência justificar, ativa `llm` via config.
 *
 * Multi-tenant Tier 0 ([ADR 0093]): reranker NÃO carrega business_id; assume que
 * caller (MeilisearchDriver) JÁ aplicou filter business_id no recall — reranker só
 * reordena material já scoped.
 */
interface Reranker
{
    /**
     * Reordena candidatos por relevância à query.
     *
     * Contrato:
     *   - retorna no máximo $topK items (idempotente — chamar 2× retorna mesma ordem)
     *   - preserva chaves originais de cada candidato + adiciona `score_rerank` (0..1)
     *   - degrada graciosamente: se falhar, retorna `array_slice($candidatos, 0, $topK)`
     *   - se $candidatos vazio, retorna []
     *   - se $topK <= 0, retorna []
     *
     * @param  string                                                                    $query        Query original do user (pra LLM/cross-encoder; ignorado por RRF)
     * @param  array<int, array{id: int|string, snippet: string, score?: float, rank_sources?: array<int, int>}>  $candidatos   Material recall já scoped multi-tenant
     * @param  int                                                                       $topK         Tamanho do subset final
     * @return array<int, array{id: int|string, snippet: string, score_rerank: float}>                Reordenado, ≤ $topK items
     */
    public function reranquear(string $query, array $candidatos, int $topK = 5): array;
}
