<?php

declare(strict_types=1);

namespace Modules\KB\Services;

use App\Util\OtelHelper;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Services\Retrieval\BgeReranker;
use Modules\Jana\Services\Retrieval\NullReranker;
use Modules\Jana\Services\Retrieval\Reranker;
use Modules\Jana\Services\Retrieval\RrfReranker;

/**
 * KbBgeRerankerService — Wave 23 KB §G1 — BGE Reranker v2-m3 plug pra KbRagService.
 *
 * Wrapper canônico que REUSA Modules\Jana\Services\Retrieval\BgeReranker
 * (self-host CT 100, modelo BAAI/bge-reranker-v2-m3, ADR 0058+0062 runtime
 * canon Ollama-style self-host — NÃO SaaS). Improve nDCG@10 +15-25% sobre
 * o re-rank algorítmico atual do KbRagService (bonus por tipo + score
 * Meilisearch base).
 *
 * Trade-offs (medidos / literatura 2026):
 *   - KbRagService::rerank() atual    — algorítmico, ~1ms, +0 NDCG vs Meilisearch raw
 *   - **BGE-v2-m3 (este wrapper)**    — +6-15pp NDCG@10, 100-300ms (CPU CT 100), R$ [redacted Tier 0]
 *   - LLM rerank (gpt-4o-mini)        — +5pp NDCG, ~200ms, R$ [redacted Tier 0]/rerank
 *   - Cohere Rerank 3.5 cloud         — +8pp NDCG, ~200ms, $2/1k tokens, dep cloud
 *
 * Multi-tenant Tier 0 (ADR 0093):
 *   - $businessId é parâmetro EXPLÍCITO em rerank() pra audit (BGE driver é stateless)
 *   - Material de entrada já scoped pelo caller (KbRagService aplicou filter biz_id
 *     no recall Meilisearch ANTES)
 *
 * Pattern de uso (preferência futura — refactor KbRagService::rerank()):
 *   $reranker = app(KbBgeRerankerService::class);
 *   $topN = $reranker->rerank($query, $topK, $businessId, topN: 5);
 *
 * Graceful degradation:
 *   - HTTP fail (timeout, 5xx, conn refused) → fallback automático BgeReranker
 *     já delega pra RrfReranker (ADR-aligned).
 *   - Config `kb.bge.enabled=false` → este service skipa e devolve top-N por score
 *     bruto (mesmo comportamento do KbRagService::rerank() legacy bonus-aware).
 *
 * Custo / latência prod (CT 100 ct100-mcp):
 *   - Cold start container BGE: ~3s
 *   - Inference: ~100-300ms para top-20 → top-5
 *   - Throughput: ~30 reranks/s single-CPU
 *
 * @see Modules/Jana/Services/Retrieval/BgeReranker.php
 * @see config/kb.php (kb.bge.enabled, kb.bge.endpoint, kb.bge.timeout)
 * @see Wave 22 FICHA KB §G1 BGE Reranker v2-m3 (+15-25% nDCG)
 */
class KbBgeRerankerService
{
    public function __construct(
        private readonly Reranker $driver,
    ) {}

    /**
     * Re-ranqueia top-K → top-N usando BGE v2-m3 self-host.
     *
     * Input shape esperado (compat com KbCorpusBuilder::retrieve()):
     *   $topK = Collection< [
     *     'kb_node_id' => int,
     *     'slug' => string,
     *     'type' => string,
     *     'title' => string,
     *     'snippet' => string,
     *     'score' => float,  // score Meilisearch base
     *   ] >
     *
     * Output: Collection mesmo shape, mas reordenado + `score_rerank` adicionado
     * (normalized 0..1 from BGE cross-encoder).
     *
     * @param  Collection<int,array<string,mixed>>  $topK
     * @return Collection<int,array<string,mixed>>
     */
    public function rerank(string $query, Collection $topK, int $businessId, int $topN = 5): Collection
    {
        $this->assertBusinessId($businessId);

        return OtelHelper::span('kb.rerank.bge', [
            'module' => 'KB',
            'business_id' => $businessId,
            'top_k_in' => $topK->count(),
            'top_n' => $topN,
            'query_hash' => substr(sha1(trim($query)), 0, 12),
        ], function () use ($query, $topK, $topN) {
            return $this->doRerank($query, $topK, $topN);
        });
    }

    /**
     * @param  Collection<int,array<string,mixed>>  $topK
     * @return Collection<int,array<string,mixed>>
     */
    protected function doRerank(string $query, Collection $topK, int $topN): Collection
    {
        if ($topK->isEmpty() || $topN <= 0) {
            return collect();
        }

        // Config gate — se BGE desabilitado, devolve top-N por score legacy
        if (! (bool) config('kb.bge.enabled', false)) {
            Log::channel('copiloto-ai')->debug('KbBgeRerankerService::disabled — fallback score-only');

            return $topK->sortByDesc('score')->take($topN)->values();
        }

        // Converte topK → shape esperado pelo BgeReranker Jana (id + snippet)
        $candidatos = $topK->map(function (array $hit) {
            return [
                'id' => (string) ($hit['kb_node_id'] ?? $hit['slug'] ?? ''),
                'snippet' => (string) ($hit['snippet'] ?? $hit['title'] ?? ''),
            ];
        })->all();

        $reranked = $this->driver->reranquear($query, $candidatos, $topN);

        if (empty($reranked)) {
            // Driver falhou e fallback interno também — devolve top-N legacy
            return $topK->sortByDesc('score')->take($topN)->values();
        }

        // Re-merge: pega ordem do reranked + payload original do topK
        $byId = $topK->keyBy(fn ($hit) => (string) ($hit['kb_node_id'] ?? $hit['slug'] ?? ''));

        $out = [];
        foreach ($reranked as $r) {
            $id = (string) ($r['id'] ?? '');
            $original = $byId->get($id);
            if ($original === null) {
                continue;
            }
            $original['score_rerank'] = (float) ($r['score_rerank'] ?? 0.0);
            $out[] = $original;
            if (count($out) >= $topN) {
                break;
            }
        }

        return collect($out);
    }

    /**
     * Factory canônico — monta cadeia driver + fallback RRF default.
     *
     * Usar via Service Provider:
     *   $this->app->singleton(KbBgeRerankerService::class, fn ($app) =>
     *       KbBgeRerankerService::makeDefault());
     */
    public static function makeDefault(): self
    {
        $endpoint = (string) config('kb.bge.endpoint', env('KB_BGE_ENDPOINT', 'http://bge-reranker.ct100:8080/rerank'));
        $timeout = (int) config('kb.bge.timeout', 5);

        $fallback = class_exists(RrfReranker::class)
            ? new RrfReranker()
            : new NullReranker();

        $driver = new BgeReranker(
            endpoint: $endpoint,
            fallback: $fallback,
            timeout: $timeout,
        );

        return new self($driver);
    }

    protected function assertBusinessId(int $businessId): void
    {
        if ($businessId <= 0) {
            throw new \InvalidArgumentException(
                'KbBgeRerankerService exige business_id positivo (Tier 0 — ADR 0093).'
            );
        }
    }
}
