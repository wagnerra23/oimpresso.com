<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Util\OtelHelper;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

/**
 * RagQualityDashboardController — Wave 28 §G3 RAG quality observability.
 *
 * Painel Wagner-only (Tailscale → auth → IsWagner — ADR 0122) que mostra
 * saúde do pipeline RAG do KB/Jana em 3 dimensões canônicas (RAG Observability
 * 2026 best-practice — Future AGI / Comet / Maxim):
 *
 *   1. **Retrieval** — recall@5 + p99 latency Meilisearch hybrid
 *   2. **Rerank**    — nDCG@5 antes/depois BGE-v2-m3 + p99 + fallback rate
 *   3. **Generate**  — LLM call p99 + faithfulness/answer_relevancy se RAGAS
 *
 * Dados consumidos:
 *
 *   - `mcp_observability_aggregates_daily` (Wave 26 — rollup diário p50/p95/p99)
 *     filtrado por `span_name LIKE 'kb.rag.%' OR 'kb.rerank.%' OR 'jana.rerank.%'`
 *   - `mcp_rag_evals` (futuro Wave 29 — gold-set runs RAGAS) — fallback graceful
 *     pra `Modules\KB\Tests\Feature\KbRagasEvalTest` thresholds (mock)
 *   - Top 10 queries lentas — log `copiloto-ai` cross-ref ou span attrs aggregated
 *
 * Multi-tenant Tier 0 ([ADR 0093]):
 *   - Dashboard é cross-business intencional (Wagner-only observability)
 *   - mcp_observability_aggregates_daily é cross-business por design
 *     ([Modules/Governance/Database/Migrations/...mcp_observability_spans])
 *   - Quando Wagner pedir drill-down per business, ler `mcp_observability_spans`
 *     scoped + agregar PHP-side (não é caminho atual)
 *
 * Custo prod:
 *   - 4 queries SELECT em tabelas indexadas (snapshot_date, span_name) — ~5-10ms
 *   - Inertia::defer em props caras (D-14 pattern 300ms → 50ms)
 *
 * @see Modules/KB/Services/KbBgeRerankerService.php (span kb.rerank.bge_v2_m3)
 * @see Modules/Governance/Services/ObservabilitySnapshotService.php
 * @see Modules/KB/Tests/Feature/KbRagasEvalTest.php (thresholds RAGAS)
 * @see memory/decisions/0122-admin-center-ct100.md
 * @see memory/decisions/0162-otel-collector-prod-observability.md
 */
class RagQualityDashboardController extends Controller
{
    /**
     * Span names canônicos por bucket (regex SQL LIKE).
     *
     * Mantido como const pra evitar drift entre callers de OtelHelper::span()
     * em diferentes módulos. Se nome novo entrar (ex: jana.rag.rewrite_query),
     * adicionar aqui + atualizar bucket map.
     */
    protected const BUCKET_SPAN_MAP = [
        'retrieve' => [
            'kb.rag.retrieve',
            'jana.retrieval.meilisearch',
            'jana.retrieval.hybrid',
        ],
        'rerank' => [
            'kb.rerank.bge_v2_m3',
            'kb.rerank.bge',
            'jana.rerank.bge',
            'jana.rerank.rrf',
            'jana.rerank.llm',
        ],
        'generate' => [
            'kb.rag.generate',
            'jana.agent.answer',
            'jana.llm.completion',
        ],
    ];

    public function __invoke(Request $request): Response
    {
        $windowDays = (int) $request->query('window', 30);
        $windowDays = max(7, min(90, $windowDays)); // clamp 7..90

        $meta = [
            'subdomain'    => config('admin.subdomain', 'admin.oimpresso.com'),
            'environment'  => app()->environment(),
            'bypass_local' => (bool) (config('admin.bypass_local') && app()->environment('local')),
            'generated_at' => now()->toIso8601String(),
            'window_days'  => $windowDays,
            'bge_enabled'  => (bool) config('kb.bge.enabled', false),
            'bge_endpoint' => (string) config('kb.bge.endpoint', ''),
            'buckets'      => array_keys(self::BUCKET_SPAN_MAP),
        ];

        return Inertia::render('Admin/RagQualityDashboard', [
            'meta'             => $meta,
            'latency_buckets'  => Inertia::defer(fn () => $this->buildLatencyBucketsPayload($windowDays)),
            'ndcg_trend'       => Inertia::defer(fn () => $this->buildNdcgTrendPayload($windowDays)),
            'recall_trend'     => Inertia::defer(fn () => $this->buildRecallTrendPayload($windowDays)),
            'top_slow_queries' => Inertia::defer(fn () => $this->buildTopSlowQueriesPayload($windowDays)),
            'fallback_rate'    => Inertia::defer(fn () => $this->buildFallbackRatePayload($windowDays)),
        ]);
    }

    /**
     * Sparkline 3 buckets (retrieve / rerank / generate) — p99 últimos N dias.
     *
     * @return array<string, array{spans:array<int,string>, daily_p99:array<int,array{date:string, p99_ms:int, count:int}>}>
     */
    protected function buildLatencyBucketsPayload(int $windowDays): array
    {
        return OtelHelper::span('admin.rag_quality.latency_buckets', [
            'component'   => 'rag_quality_dashboard',
            'window_days' => $windowDays,
        ], function () use ($windowDays) {
            $out = [];

            if (! Schema::hasTable('mcp_observability_aggregates_daily')) {
                foreach (array_keys(self::BUCKET_SPAN_MAP) as $bucket) {
                    $out[$bucket] = ['spans' => self::BUCKET_SPAN_MAP[$bucket], 'daily_p99' => []];
                }

                return $out;
            }

            $since = CarbonImmutable::now()->subDays($windowDays)->toDateString();

            foreach (self::BUCKET_SPAN_MAP as $bucket => $spans) {
                $rows = DB::table('mcp_observability_aggregates_daily')
                    ->whereIn('span_name', $spans)
                    ->where('snapshot_date', '>=', $since)
                    ->orderBy('snapshot_date', 'asc')
                    ->get([
                        'snapshot_date',
                        DB::raw('MAX(p99_ms) as p99_ms'),
                        DB::raw('SUM(count_total) as count_total'),
                    ]);

                // Agrupa por date (caso múltiplos span_names retornem rows soltas)
                $byDate = [];
                foreach ($rows as $r) {
                    $d = (string) $r->snapshot_date;
                    if (! isset($byDate[$d])) {
                        $byDate[$d] = ['date' => $d, 'p99_ms' => 0, 'count' => 0];
                    }
                    $byDate[$d]['p99_ms'] = max($byDate[$d]['p99_ms'], (int) $r->p99_ms);
                    $byDate[$d]['count'] += (int) $r->count_total;
                }

                $out[$bucket] = [
                    'spans'     => $spans,
                    'daily_p99' => array_values($byDate),
                ];
            }

            return $out;
        });
    }

    /**
     * Trend nDCG@5 últimos N dias — cross-correlation com mcp_rag_evals se existir,
     * fallback pra ratio ndcg_after/ndcg_before extraído dos spans (futuro).
     *
     * @return array<int, array{date:string, ndcg_at_5:float, source:string}>
     */
    protected function buildNdcgTrendPayload(int $windowDays): array
    {
        // mcp_rag_evals ainda não foi materializada (Wave 29 escopo) — fallback
        // graceful retorna trend vazio mas dashboard renderiza placeholder em vez
        // de quebrar. Quando schema chegar, this branch passa a hidratar.
        if (! Schema::hasTable('mcp_rag_evals')) {
            return [];
        }

        $since = CarbonImmutable::now()->subDays($windowDays)->toDateString();

        $rows = DB::table('mcp_rag_evals')
            ->where('eval_date', '>=', $since)
            ->where('metric', 'ndcg_at_5')
            ->orderBy('eval_date', 'asc')
            ->get(['eval_date', 'value']);

        return $rows->map(fn ($r) => [
            'date'      => (string) $r->eval_date,
            'ndcg_at_5' => round((float) $r->value, 4),
            'source'    => 'mcp_rag_evals',
        ])->all();
    }

    /**
     * Trend recall@5 últimos N dias — também via mcp_rag_evals.
     *
     * @return array<int, array{date:string, recall_at_5:float, source:string}>
     */
    protected function buildRecallTrendPayload(int $windowDays): array
    {
        if (! Schema::hasTable('mcp_rag_evals')) {
            return [];
        }

        $since = CarbonImmutable::now()->subDays($windowDays)->toDateString();

        $rows = DB::table('mcp_rag_evals')
            ->where('eval_date', '>=', $since)
            ->where('metric', 'recall_at_5')
            ->orderBy('eval_date', 'asc')
            ->get(['eval_date', 'value']);

        return $rows->map(fn ($r) => [
            'date'        => (string) $r->eval_date,
            'recall_at_5' => round((float) $r->value, 4),
            'source'      => 'mcp_rag_evals',
        ])->all();
    }

    /**
     * Top 10 queries lentas — agrega `mcp_observability_spans` (se existir)
     * por query_hash attr + ordena DESC duration_ms.
     *
     * Tabela cara → cap em 1000 rows lidos (cap também via WHERE timestamp).
     *
     * @return array<int, array{query_hash:string, span_name:string, max_duration_ms:int, count:int}>
     */
    protected function buildTopSlowQueriesPayload(int $windowDays): array
    {
        if (! Schema::hasTable('mcp_observability_spans')) {
            return [];
        }

        $since = CarbonImmutable::now()->subDays($windowDays);
        $allRagSpans = array_merge(...array_values(self::BUCKET_SPAN_MAP));

        // attributes_json é JSON — query_hash está em attrs['query_hash'].
        // JSON_EXTRACT funciona em MySQL 5.7+ — caso Hostinger restrição,
        // fallback PHP-side agrega via foreach.
        $rows = DB::table('mcp_observability_spans')
            ->whereIn('span_name', $allRagSpans)
            ->where('timestamp', '>=', $since)
            ->orderBy('duration_ms', 'desc')
            ->limit(1000)
            ->get(['span_name', 'duration_ms', 'attributes_json']);

        $byKey = [];
        foreach ($rows as $r) {
            $attrs    = json_decode((string) ($r->attributes_json ?? '{}'), true) ?: [];
            $qHash    = (string) ($attrs['query_hash'] ?? 'unknown');
            $key      = $r->span_name.'::'.$qHash;

            if (! isset($byKey[$key])) {
                $byKey[$key] = [
                    'query_hash'      => $qHash,
                    'span_name'       => (string) $r->span_name,
                    'max_duration_ms' => 0,
                    'count'           => 0,
                ];
            }
            $byKey[$key]['max_duration_ms'] = max($byKey[$key]['max_duration_ms'], (int) $r->duration_ms);
            $byKey[$key]['count']++;
        }

        usort($byKey, fn ($a, $b) => $b['max_duration_ms'] <=> $a['max_duration_ms']);

        return array_slice(array_values($byKey), 0, 10);
    }

    /**
     * Fallback rate BGE — ratio (spans com attr `fallback_used=true`) / total.
     *
     * Sinal-alerta: >5% indica CT 100 instável ou bge container down. Útil
     * pra Wagner detectar drift sem precisar olhar log.
     *
     * @return array{rerank_total:int, fallback_count:int, fallback_pct:float, window_days:int}
     */
    protected function buildFallbackRatePayload(int $windowDays): array
    {
        $out = [
            'rerank_total'   => 0,
            'fallback_count' => 0,
            'fallback_pct'   => 0.0,
            'window_days'    => $windowDays,
        ];

        if (! Schema::hasTable('mcp_observability_spans')) {
            return $out;
        }

        $since       = CarbonImmutable::now()->subDays($windowDays);
        $rerankSpans = self::BUCKET_SPAN_MAP['rerank'];

        $rows = DB::table('mcp_observability_spans')
            ->whereIn('span_name', $rerankSpans)
            ->where('timestamp', '>=', $since)
            ->get(['attributes_json']);

        $total    = 0;
        $fallback = 0;
        foreach ($rows as $r) {
            $total++;
            $attrs = json_decode((string) ($r->attributes_json ?? '{}'), true) ?: [];
            if (($attrs['fallback_used'] ?? false) === true) {
                $fallback++;
            }
        }

        $out['rerank_total']   = $total;
        $out['fallback_count'] = $fallback;
        $out['fallback_pct']   = $total > 0 ? round(($fallback / $total) * 100, 2) : 0.0;

        return $out;
    }
}
