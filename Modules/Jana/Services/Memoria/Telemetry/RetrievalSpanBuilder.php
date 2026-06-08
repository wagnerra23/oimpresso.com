<?php

declare(strict_types=1);

namespace Modules\Jana\Services\Memoria\Telemetry;

use Illuminate\Support\Facades\Log;
use Modules\Jana\Services\Telemetry\LangfuseClient;
use Throwable;

/**
 * RetrievalSpanBuilder — factory canônica dos 7 spans do pipeline retrieval Jana
 * (D8 gap #3 — OTel GenAI semantic conventions — 2026-05-15, +2pp 86→88).
 *
 * Pipeline canônico (MeilisearchDriver::buscar):
 *
 *   jana.retrieval.query          (root parent — full pipeline)
 *   ├─ jana.retrieval.negative_cache    (lookup NegativeCache — fast path skip)
 *   ├─ jana.retrieval.hyde              (HydeQueryExpander — doc hipotético)
 *   ├─ jana.retrieval.embedding         (Scout hybrid semantic — vector lane)
 *   ├─ jana.retrieval.bm25              (Scout hybrid lexical — full-text lane)
 *   ├─ jana.retrieval.merge             (RRF fusion 60-k Cormack)
 *   ├─ jana.retrieval.time_decay        (half-life weighting K1 dossier 2026-05-13)
 *   ├─ jana.retrieval.rerank            (RRF / LLM / BGE — config('copiloto.reranker'))
 *   └─ jana.retrieval.context_select    (top-K pro LLM)
 *
 * Atributos canônicos OTel GenAI 2026:
 *   gen_ai.system               = "anthropic" | "openai" | "self" (oimpresso self-host)
 *   gen_ai.operation.name       = "retrieval"
 *   gen_ai.retrieval.query      = sha256(query) (redacted) OU raw se redact=false
 *   gen_ai.retrieval.query_chars= strlen($query)
 *   gen_ai.retrieval.top_k      = K
 *   gen_ai.retrieval.candidates_count = N
 *   gen_ai.retrieval.latency_ms = T (auto-derivado de durationMs em end)
 *
 * Custom (não-canon OTel — prefix `oimpresso.*`):
 *   oimpresso.business_id       = $businessId   (Tier 0 multi-tenant — ADR 0093)
 *   oimpresso.user_id           = $userId
 *   oimpresso.rerank.driver     = rrf|llm|bge|null
 *   oimpresso.embedder          = qwen3_local|openai|...
 *
 * Custo overhead: ~5-15ms por query (8 spans × ~1ms POPO + 1 Langfuse dispatch
 * async + 1 audit log INSERT). Negligível vs ~200-800ms latência média retrieval.
 *
 * Fail-open: erros internos do builder NUNCA propagam pro caller — log warning
 * em channel `copiloto-ai` e segue. Filosofia: observability não pode quebrar prod.
 *
 * @see config/otel.php
 * @see memory/decisions/0051-jana-schema-proprio-adapter-otel-genai.md
 * @see memory/decisions/0132-langfuse-self-host-ct100.md
 * @see https://opentelemetry.io/docs/specs/semconv/gen-ai/gen-ai-spans/
 */
final class RetrievalSpanBuilder
{
    public function __construct(
        private readonly ?LangfuseClient $langfuse = null,
    ) {
    }

    /**
     * Inicia root span da query. Devolve span pronto pra sub-spans.
     */
    public function startQuery(string $query, int $businessId, int $userId, int $topK): RetrievalSpan
    {
        $redact = (bool) config('copiloto.telemetry.redact_query', true);

        return new RetrievalSpan(
            name: 'jana.retrieval.query',
            parentSpanId: null,
            attributes: [
                'gen_ai.system' => 'self',
                'gen_ai.operation.name' => 'retrieval',
                'gen_ai.retrieval.query' => $redact
                    ? hash('sha256', $query)
                    : $query,
                'gen_ai.retrieval.query_chars' => strlen($query),
                'gen_ai.retrieval.top_k' => $topK,
                'oimpresso.business_id' => $businessId,
                'oimpresso.user_id' => $userId,
                'oimpresso.query_redacted' => $redact,
            ],
        );
    }

    public function startNegativeCache(RetrievalSpan $parent): RetrievalSpan
    {
        return new RetrievalSpan(
            name: 'jana.retrieval.negative_cache',
            parentSpanId: $parent->spanId,
            attributes: [
                'gen_ai.operation.name' => 'retrieval.negative_cache',
                'oimpresso.business_id' => $parent->attributes['oimpresso.business_id'] ?? null,
            ],
        );
    }

    public function startHyde(RetrievalSpan $parent): RetrievalSpan
    {
        return new RetrievalSpan(
            name: 'jana.retrieval.hyde',
            parentSpanId: $parent->spanId,
            attributes: [
                'gen_ai.operation.name' => 'retrieval.query_expansion',
                'gen_ai.retrieval.technique' => 'hyde',
                'oimpresso.business_id' => $parent->attributes['oimpresso.business_id'] ?? null,
            ],
        );
    }

    public function startEmbedding(RetrievalSpan $parent, string $embedder): RetrievalSpan
    {
        return new RetrievalSpan(
            name: 'jana.retrieval.embedding',
            parentSpanId: $parent->spanId,
            attributes: [
                'gen_ai.operation.name' => 'retrieval.semantic',
                'gen_ai.request.embedder' => $embedder,
                'oimpresso.business_id' => $parent->attributes['oimpresso.business_id'] ?? null,
                'oimpresso.embedder' => $embedder,
            ],
        );
    }

    public function startBm25(RetrievalSpan $parent): RetrievalSpan
    {
        return new RetrievalSpan(
            name: 'jana.retrieval.bm25',
            parentSpanId: $parent->spanId,
            attributes: [
                'gen_ai.operation.name' => 'retrieval.lexical',
                'gen_ai.retrieval.technique' => 'bm25',
                'oimpresso.business_id' => $parent->attributes['oimpresso.business_id'] ?? null,
            ],
        );
    }

    public function startMerge(RetrievalSpan $parent, int $resultSetsCount): RetrievalSpan
    {
        return new RetrievalSpan(
            name: 'jana.retrieval.merge',
            parentSpanId: $parent->spanId,
            attributes: [
                'gen_ai.operation.name' => 'retrieval.fusion',
                'gen_ai.retrieval.technique' => 'rrf',
                'oimpresso.merge.result_sets' => $resultSetsCount,
                'oimpresso.business_id' => $parent->attributes['oimpresso.business_id'] ?? null,
            ],
        );
    }

    public function startTimeDecay(RetrievalSpan $parent, int $candidatesCount): RetrievalSpan
    {
        return new RetrievalSpan(
            name: 'jana.retrieval.time_decay',
            parentSpanId: $parent->spanId,
            attributes: [
                'gen_ai.operation.name' => 'retrieval.rerank.time_decay',
                'gen_ai.retrieval.candidates_count' => $candidatesCount,
                'oimpresso.business_id' => $parent->attributes['oimpresso.business_id'] ?? null,
            ],
        );
    }

    public function startRerank(RetrievalSpan $parent, int $candidatesCount, string $driver): RetrievalSpan
    {
        return new RetrievalSpan(
            name: 'jana.retrieval.rerank',
            parentSpanId: $parent->spanId,
            attributes: [
                'gen_ai.operation.name' => 'retrieval.rerank',
                'gen_ai.retrieval.candidates_count' => $candidatesCount,
                'oimpresso.rerank.driver' => $driver,
                'oimpresso.business_id' => $parent->attributes['oimpresso.business_id'] ?? null,
            ],
        );
    }

    public function startContextSelect(RetrievalSpan $parent, int $topK): RetrievalSpan
    {
        return new RetrievalSpan(
            name: 'jana.retrieval.context_select',
            parentSpanId: $parent->spanId,
            attributes: [
                'gen_ai.operation.name' => 'retrieval.context_selection',
                'gen_ai.retrieval.top_k' => $topK,
                'oimpresso.business_id' => $parent->attributes['oimpresso.business_id'] ?? null,
            ],
        );
    }

    /** @param array<string,mixed> $attributes */
    public function recordResult(RetrievalSpan $span, array $attributes = []): void
    {
        foreach ($attributes as $k => $v) {
            $span->setAttribute($k, $v);
        }
        $span->setStatus('ok');
        $span->setAttribute('gen_ai.retrieval.latency_ms', $span->durationMs());
        $span->end();
    }

    public function recordError(RetrievalSpan $span, Throwable $e): void
    {
        $span->setStatus('error', $e->getMessage());
        $span->setAttribute('exception.type', $e::class);
        $span->setAttribute('exception.message', $e->getMessage());
        $span->setAttribute('gen_ai.retrieval.latency_ms', $span->durationMs());
        $span->end();
    }

    /**
     * Despacha span pra exporters configurados (Langfuse + log channel).
     * Fail-open: nunca propaga exception.
     *
     * Chamado pelo Decorator no `finally`. Não acopla com span lifecycle.
     */
    public function emit(RetrievalSpan $span, ?string $traceId = null): void
    {
        try {
            // 1. Log channel (sempre, baixo custo). Util pra debug local sem Langfuse.
            Log::channel(config('logging.default'))
                ->debug('jana.retrieval.span', [
                    'name' => $span->name,
                    'span_id' => $span->spanId,
                    'parent_span_id' => $span->parentSpanId,
                    'status' => $span->status,
                    'duration_ms' => round($span->durationMs(), 2),
                    'attributes' => $span->attributes,
                ]);

            // 2. Langfuse dispatch async (se cliente disponível + trace ativo).
            //    Pattern já comprovado em LangfuseClient::recordSpan (linhas 199-227).
            if ($this->langfuse !== null && $traceId !== null) {
                $this->langfuse->recordSpan($traceId, [
                    'name' => $span->name,
                    'duration_ms' => (int) round($span->durationMs()),
                    'metadata' => $span->attributes,
                    'level' => $span->status === 'error' ? 'ERROR' : 'DEFAULT',
                    'status_message' => $span->statusMessage,
                ]);
            }
        } catch (Throwable $e) {
            // Observability não pode quebrar prod — log warning e segue.
            Log::warning('RetrievalSpanBuilder::emit falhou', [
                'span_name' => $span->name,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
