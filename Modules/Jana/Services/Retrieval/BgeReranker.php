<?php

declare(strict_types=1);

namespace Modules\Jana\Services\Retrieval;

use App\Util\OtelHelper;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * BgeReranker — Cross-encoder reranker BGE-v2-m3 self-host CT 100 (Onda 4 — R1 P0).
 *
 * Driver alvo da AUDITORIA-KNOWLEDGE-ARCHITECTURE-2026-05-13 §5 G3 (R1 P0):
 *   - Fecha hybrid retrieval (Knowledge R3) — BM25 + vector + RRF + cross-encoder rerank.
 *   - Modelo `BAAI/bge-reranker-v2-m3` (568M params, ~2.27GB, multilingual PT-BR ok).
 *   - Servido por FastAPI + FlagEmbedding rodando em container Docker no CT 100
 *     (RUNBOOK-bge-reranker-ct100.md). Endpoint canônico
 *     `http://bge-reranker.ct100:8080/rerank` (LAN Tailscale) ou
 *     `https://bge-reranker.ct100.oimpresso.com/rerank` (Traefik IP-whitelisted).
 *
 * Trade-off vs alternativas (medidos / literatura 2026):
 *   - RrfReranker (default atual)        — NDCG@10 baseline, ~1ms,    R$ 0,      algorítmico
 *   - **BgeReranker (este)**             — NDCG@10 +6pp,    100-300ms (CPU CT 100), R$ 0, self-host
 *   - Cohere Rerank 3.5 cloud            — NDCG@10 +8pp,    100-300ms, $2/1k tokens, dep cloud
 *   - LlmRerankerAdapter (gpt-4o-mini)   — NDCG@10 +5pp,    ~200ms,   R$ 0,0005/rerank
 *
 * Comportamento idempotente: BGE é determinístico (model.eval mode + sem dropout)
 * → mesma query + mesmos docs + mesmo top_k → mesma ordem de saída.
 *
 * Graceful degradation: HTTP fail (timeout, 5xx, conn refused) → delega pra fallback
 * injetado (default RrfReranker no provider). Garante zero downtime do Jana
 * quando container BGE estiver fora — só perde os +6pp NDCG@10 temporariamente.
 *
 * Multi-tenant Tier 0 ([ADR 0093]): NÃO carrega business_id — reranking é stateless
 * sobre material já scoped pelo caller (MeilisearchDriver aplicou filter biz_id
 * no recall ANTES de chamar este driver).
 *
 * Referências:
 *   - HF model card: https://huggingface.co/BAAI/bge-reranker-v2-m3
 *   - FlagEmbedding wrapper: https://github.com/FlagOpen/FlagEmbedding
 *   - AUDITORIA-KNOWLEDGE-ARCHITECTURE-2026-05-13.md §5 G3
 *   - GAP-ANALYSIS-91-100-2026-05-13.md (Onda 4 R1 P0)
 */
class BgeReranker implements Reranker
{
    private readonly int $timeoutSeconds;

    /**
     * @param  int|null  $timeout  Alias canon (Provider passa `timeout:` named arg).
     */
    public function __construct(
        private readonly string $endpoint,
        private readonly Reranker $fallback,
        int $timeoutSeconds = 5,
        ?int $timeout = null,
    ) {
        // Provider usa `timeout:` named arg; back-compat aceitando ambos.
        $this->timeoutSeconds = $timeout ?? $timeoutSeconds;
    }

    public function reranquear(string $query, array $candidatos, int $topK = 5): array
    {
        return OtelHelper::spanBiz('jana.rerank.bge', function () use ($query, $candidatos, $topK) {
            return $this->doReranquear($query, $candidatos, $topK);
        }, [
            'candidatos_in' => count($candidatos),
            'top_k' => $topK,
            'endpoint' => $this->endpoint,
        ]);
    }

    private function doReranquear(string $query, array $candidatos, int $topK): array
    {
        if (empty($candidatos) || $topK <= 0) {
            return [];
        }

        // Filtra candidatos sem id (alinha com contrato RrfReranker) + indexa por posição
        // para reaplicar payload original após rerank (preserva snippet / score legacy).
        $indexById = [];
        $documentos = [];
        foreach ($candidatos as $rank => $cand) {
            $id = $cand['id'] ?? null;
            if ($id === null) {
                continue;
            }
            $indexById[$id]  = $cand;
            $documentos[]    = [
                'id'      => $id,
                'snippet' => (string) ($cand['snippet'] ?? ''),
            ];
        }

        if (empty($documentos)) {
            return [];
        }

        try {
            $payload = [
                'query'     => $query,
                'documents' => array_column($documentos, 'snippet'),
                'top_k'     => $topK,
            ];

            $response = Http::timeout($this->timeoutSeconds)
                ->acceptJson()
                ->asJson()
                ->post($this->endpoint, $payload);

            if (! $response->successful()) {
                Log::channel('copiloto-ai')->warning('BgeReranker::http_not_ok — fallback RRF', [
                    'status'        => $response->status(),
                    'endpoint'      => $this->endpoint,
                    'query_chars'   => strlen($query),
                    'candidatos_in' => count($documentos),
                ]);

                return $this->fallback->reranquear($query, $candidatos, $topK);
            }

            $body    = $response->json();
            $results = is_array($body['results'] ?? null) ? $body['results'] : null;

            if ($results === null) {
                Log::channel('copiloto-ai')->warning('BgeReranker::malformed_response — fallback RRF', [
                    'endpoint' => $this->endpoint,
                    'body'     => $body,
                ]);

                return $this->fallback->reranquear($query, $candidatos, $topK);
            }

            // Parse: serviço retorna [{index: int, score: float}, ...] já ordenado desc.
            // Reconstrói candidatos preservando payload original + adiciona score_rerank.
            $reordenados = [];
            $maxScore    = 0.0;

            foreach ($results as $r) {
                $idx   = $r['index']   ?? null;
                $score = $r['score']   ?? null;
                if ($idx === null || ! isset($documentos[$idx])) {
                    continue;
                }
                $maxScore = max($maxScore, (float) $score);
            }
            if ($maxScore <= 0.0) {
                $maxScore = 1.0;
            }

            foreach ($results as $r) {
                $idx   = $r['index']   ?? null;
                $score = $r['score']   ?? null;
                if ($idx === null || ! isset($documentos[$idx])) {
                    continue;
                }

                $id = $documentos[$idx]['id'];
                $original = $indexById[$id];
                $original['score_rerank'] = round(((float) $score) / $maxScore, 4);

                $reordenados[] = $original;

                if (count($reordenados) >= $topK) {
                    break;
                }
            }

            Log::channel('copiloto-ai')->debug('BgeReranker::reranquear', [
                'query_chars'    => strlen($query),
                'candidatos_in'  => count($documentos),
                'candidatos_out' => count($reordenados),
                'top_k'          => $topK,
                'driver'         => 'bge',
                'endpoint'       => $this->endpoint,
            ]);

            return $reordenados;
        } catch (Throwable $e) {
            // Connection refused, timeout, DNS fail — degrada pra fallback RRF.
            Log::channel('copiloto-ai')->warning('BgeReranker::exception — fallback RRF', [
                'exception'   => $e::class,
                'message'     => $e->getMessage(),
                'endpoint'    => $this->endpoint,
                'query_chars' => strlen($query),
            ]);

            return $this->fallback->reranquear($query, $candidatos, $topK);
        }
    }
}
