<?php

declare(strict_types=1);

namespace Modules\Jana\Services\Memoria\Telemetry;

use Modules\Jana\Contracts\MemoriaContrato;
use Modules\Jana\Contracts\MemoriaPersistida;
use Modules\Jana\Entities\Mcp\McpAuditLog;
use Throwable;

/**
 * RetrievalTelemetryDecorator — instrumenta MemoriaContrato::buscar com spans
 * OTel GenAI canônicos (D8 gap #3 — 2026-05-15, +2pp 86→88).
 *
 * Pattern Decorator (GoF) — Anthropic recomenda em LLM Observability Guide 2026
 * pra evitar acoplar instrumentação no driver core (MeilisearchDriver continua
 * limpo, sem dependência de Telemetry/*).
 *
 * Métodos não-buscar (lembrar/atualizar/esquecer/listar) passam direto pro inner
 * sem instrumentação por hora (gap escopa retrieval pipeline — write/forget tem
 * audit log próprio em McpAuditLog).
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL: business_id propagado em todo span attribute
 * via SpanBuilder. Pest cross-tenant assertiva test 8.
 *
 * Fail-open: erros internos do decorator (Telemetry, audit log) NÃO propagam
 * pro caller — Wagner regra "observability não derruba prod".
 *
 * Quando MeilisearchDriver::buscar() lançar exception real (Scout down,
 * embedder offline), DEPOIS de marcar erro no span, re-lança pro caller —
 * fail-open vale só pra própria instrumentação, não pra retrieval em si.
 */
final class RetrievalTelemetryDecorator implements MemoriaContrato
{
    public function __construct(
        private readonly MemoriaContrato $inner,
        private readonly RetrievalSpanBuilder $spans,
    ) {
    }

    public function lembrar(int $businessId, int $userId, string $fato, array $metadata = []): MemoriaPersistida
    {
        return $this->inner->lembrar($businessId, $userId, $fato, $metadata);
    }

    /**
     * Buscar instrumentado — wrappa pipeline com spans OTel GenAI.
     *
     * Por que não envolvemos cada step (HyDE/embedding/BM25/...) individualmente?
     * Porque MeilisearchDriver::buscar é uma caixa opaca pro decorator. Pra
     * instrumentar steps internos sem modificá-lo, precisaríamos:
     *   (a) emitir spans via Listeners de events (futuro)
     *   (b) inverter pra pattern Visitor (overkill por enquanto)
     *
     * Por ora o root span captura latência total + atributos canônicos. Sub-spans
     * estão prontos no SpanBuilder pra quando o driver expor hooks (próximo PR
     * Onda 7 — wire LangfuseClient direto no MeilisearchDriver via callable
     * collector injetável).
     */
    public function buscar(int $businessId, int $userId, string $query, int $topK = 5): array
    {
        $rootSpan = $this->spans->startQuery($query, $businessId, $userId, $topK);
        $traceId = null; // sem trace pai por enquanto — futuro: extrair de
                         // Request scope ($request->attributes->get('jana_trace_id'))
        $error = null;
        $result = [];

        try {
            $result = $this->inner->buscar($businessId, $userId, $query, $topK);

            $this->spans->recordResult($rootSpan, [
                'gen_ai.retrieval.candidates_count' => count($result),
                'gen_ai.retrieval.hit' => count($result) > 0,
            ]);

            return $result;
        } catch (Throwable $e) {
            $error = $e;
            $this->spans->recordError($rootSpan, $e);
            throw $e;
        } finally {
            $this->spans->emit($rootSpan, $traceId);
            $this->writeAuditLog($rootSpan, $businessId, $userId, count($result), $error);
        }
    }

    public function atualizar(int $memoriaId, string $novoFato, array $metadata = []): void
    {
        $this->inner->atualizar($memoriaId, $novoFato, $metadata);
    }

    public function esquecer(int $memoriaId): void
    {
        $this->inner->esquecer($memoriaId);
    }

    public function listar(int $businessId, int $userId): array
    {
        return $this->inner->listar($businessId, $userId);
    }

    /**
     * Persiste row em mcp_audit_log com metadata do span (ADR 0053).
     * Fail-open: erros aqui (DB down etc) nunca propagam.
     */
    private function writeAuditLog(
        RetrievalSpan $rootSpan,
        int $businessId,
        int $userId,
        int $candidatesCount,
        ?Throwable $error,
    ): void {
        if (! (bool) config('copiloto.telemetry.audit_log_enabled', true)) {
            return;
        }

        try {
            McpAuditLog::registrar([
                'user_id' => $userId,
                'business_id' => $businessId,
                'endpoint' => 'jana.retrieval',
                'tool_or_resource' => 'memoria.buscar',
                'status' => $error !== null ? 'error' : 'ok',
                'error_message' => $error?->getMessage(),
                'duration_ms' => (int) round($rootSpan->durationMs()),
                'payload_summary' => [
                    'retrieval_latency_ms' => round($rootSpan->durationMs(), 2),
                    'candidates_count' => $candidatesCount,
                    'top_k' => $rootSpan->attributes['gen_ai.retrieval.top_k'] ?? null,
                    'query_hash' => $rootSpan->attributes['gen_ai.retrieval.query'] ?? null,
                    'query_redacted' => $rootSpan->attributes['oimpresso.query_redacted'] ?? null,
                    'rerank_driver' => (string) config('copiloto.reranker.driver', 'rrf'),
                    'embedder' => (string) config('copiloto.memoria.meilisearch.embedder', 'qwen3_local'),
                    'span_id' => $rootSpan->spanId,
                ],
            ]);
        } catch (Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('RetrievalTelemetryDecorator audit log falhou', [
                'error' => $e->getMessage(),
                'business_id' => $businessId,
            ]);
        }
    }
}
