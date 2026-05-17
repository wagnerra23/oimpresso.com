<?php

declare(strict_types=1);

namespace Modules\Governance\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ObservabilitySnapshotService — rollup diário de OTel spans (Wave 26 Agent 3 — 2026-05-17).
 *
 * Computa percentis p50/p95/p99 em PHP (MySQL <8.0 não tem `PERCENTILE_CONT`) e
 * popula `mcp_observability_aggregates_daily` via upsert idempotente.
 *
 * Consumido por:
 *   - `ScopedScorecardEvaluator::detectOtelQuery()` D9.b (governance v4)
 *   - UI Governance/Observability (sparkline 7d p99)
 *
 * Schedule: daily 02:00 BRT via `observability:aggregate-daily` (Kernel.php).
 *
 * Multi-tenant Tier 0 ADR 0093: spans crus preservam `business_id`; aggregates são
 * cross-business (governance v4 olha módulo, não business). Quando futuro D9.b
 * por business for necessário, adicionar segunda tabela `..._aggregates_daily_biz`.
 *
 * @see Modules/Governance/Database/Migrations/2026_05_17_000002_create_mcp_observability_spans_table.php
 * @see Modules\Governance\Console\Commands\ObservabilityAggregateCommand
 * @see memory/decisions/0162-otel-collector-prod-observability.md
 */
class ObservabilitySnapshotService
{
    /**
     * Roda agregação diária pra uma data (default 'yesterday').
     *
     * @return int Quantidade de aggregates persistidos (1 por par module+span_name).
     */
    public function aggregateDaily(string $date = 'yesterday'): int
    {
        if (! Schema::hasTable('mcp_observability_spans')
            || ! Schema::hasTable('mcp_observability_aggregates_daily')) {
            // Fail-safe: tabelas ausentes em dev sem migrate — não quebra
            return 0;
        }

        $start = Carbon::parse($date)->startOfDay();
        $end   = Carbon::parse($date)->endOfDay();

        // Agrega COUNTs primeiro (cheap SQL); percentis em PHP por par (module, span).
        $groups = DB::table('mcp_observability_spans')
            ->select([
                'module',
                'span_name',
                DB::raw('COUNT(*) as count_total'),
                DB::raw("SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) as count_error"),
            ])
            ->whereBetween('timestamp', [$start, $end])
            ->groupBy('module', 'span_name')
            ->get();

        $inserted = 0;
        foreach ($groups as $group) {
            $percentiles = $this->computePercentiles(
                (string) $group->module,
                (string) $group->span_name,
                $start,
                $end,
            );

            DB::table('mcp_observability_aggregates_daily')->updateOrInsert(
                [
                    'module'        => $group->module,
                    'span_name'     => $group->span_name,
                    'snapshot_date' => $start->toDateString(),
                ],
                [
                    'count_total' => (int) $group->count_total,
                    'count_error' => (int) $group->count_error,
                    'p50_ms'      => $percentiles['p50'],
                    'p95_ms'      => $percentiles['p95'],
                    'p99_ms'      => $percentiles['p99'],
                    'created_at'  => now(),
                ]
            );
            $inserted++;
        }

        return $inserted;
    }

    /**
     * Computa percentis em PHP via ordenação + índice nearest-rank.
     *
     * Trade-off vs PERCENTILE_CONT MySQL 8.0+:
     *   - PHP carrega todos `duration_ms` do par em memória (cap volume via sampling
     *     5% no collector — ADR 0162 §6).
     *   - Independente de versão MySQL (Hostinger pode ser <8.0).
     *   - Custo aceitável: ~1000 spans/par/dia × ~12 pares = ~12k rows total/dia.
     *
     * @return array{p50: int, p95: int, p99: int}
     */
    private function computePercentiles(string $module, string $span, Carbon $start, Carbon $end): array
    {
        $durations = DB::table('mcp_observability_spans')
            ->where('module', $module)
            ->where('span_name', $span)
            ->whereBetween('timestamp', [$start, $end])
            ->orderBy('duration_ms')
            ->pluck('duration_ms')
            ->map(fn ($v) => (int) $v)
            ->values()
            ->toArray();

        $n = count($durations);
        if ($n === 0) {
            return ['p50' => 0, 'p95' => 0, 'p99' => 0];
        }

        return [
            'p50' => $this->nearestRank($durations, $n, 0.50),
            'p95' => $this->nearestRank($durations, $n, 0.95),
            'p99' => $this->nearestRank($durations, $n, 0.99),
        ];
    }

    /**
     * Nearest-rank percentile (NIST recommended pra small samples).
     * Garante index dentro do range [0, n-1].
     *
     * @param  array<int, int>  $sortedAsc
     */
    private function nearestRank(array $sortedAsc, int $n, float $p): int
    {
        $idx = (int) ceil($p * $n) - 1;
        $idx = max(0, min($n - 1, $idx));
        return (int) $sortedAsc[$idx];
    }

    /**
     * Lê aggregates dos últimos N dias pra um módulo — consumido por
     * `ScopedScorecardEvaluator::detectOtelQuery()` D9.b.
     *
     * @return array<int, object>
     */
    public function getModuleHealth(string $module, int $lastDays = 7): array
    {
        if (! Schema::hasTable('mcp_observability_aggregates_daily')) {
            return [];
        }
        return DB::table('mcp_observability_aggregates_daily')
            ->where('module', $module)
            ->where('snapshot_date', '>=', now()->subDays($lastDays)->toDateString())
            ->orderBy('snapshot_date', 'desc')
            ->get()
            ->all();
    }
}
