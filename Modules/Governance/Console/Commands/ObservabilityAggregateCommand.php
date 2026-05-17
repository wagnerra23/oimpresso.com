<?php

declare(strict_types=1);

namespace Modules\Governance\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Modules\Governance\Services\ObservabilitySnapshotService;

/**
 * observability:aggregate-daily — rollup diário de OTel spans (Wave 26 Agent 3 — 2026-05-17).
 *
 * Pega spans crus em `mcp_observability_spans` da data alvo (default ontem),
 * computa p50/p95/p99 + error rate por par (module, span_name) e popula
 * `mcp_observability_aggregates_daily` via upsert idempotente.
 *
 * Schedule: daily 02:00 BRT (Kernel.php) — janela conservadora antes do
 * `jana:health-check` (06:00) e `module:grade-snapshot` (06:05).
 *
 * Flags Tier 0:
 *   - NÃO usa `--verbose` (Symfony reserved — rule path-scoped commands.md)
 *   - `--date=` overrride pra reprocessar histórico (default 'yesterday')
 *   - `--detail` log linha-por-par durante execução
 *
 * Uso CLI:
 *   php artisan observability:aggregate-daily
 *   php artisan observability:aggregate-daily --date=2026-05-15
 *   php artisan observability:aggregate-daily --detail
 *
 * @see Modules\Governance\Services\ObservabilitySnapshotService
 * @see Modules/Governance/Database/Migrations/2026_05_17_000002_create_mcp_observability_spans_table.php
 * @see memory/decisions/0162-otel-collector-prod-observability.md
 */
class ObservabilityAggregateCommand extends Command
{
    protected $signature = 'observability:aggregate-daily
                            {--date=yesterday : Data alvo (yesterday | YYYY-MM-DD)}
                            {--detail : Log linha-por-par durante execução}';

    protected $description = 'Rollup diário OTel spans → aggregates p50/p95/p99 (cron 02:00 BRT) — Wave 26 ADR 0162';

    public function handle(ObservabilitySnapshotService $svc): int
    {
        $startedAt = microtime(true);

        if (! Schema::hasTable('mcp_observability_spans')) {
            $this->warn('Tabela mcp_observability_spans ausente — rode `php artisan migrate` primeiro.');
            return self::SUCCESS; // soft-fail (collector OTel pode não estar ativo ainda)
        }
        if (! Schema::hasTable('mcp_observability_aggregates_daily')) {
            $this->error('Tabela mcp_observability_aggregates_daily ausente — migration faltando.');
            return self::FAILURE;
        }

        $date   = (string) $this->option('date');
        $detail = (bool) $this->option('detail');

        $inserted = $svc->aggregateDaily($date);

        if ($detail) {
            $this->line("Rollup OTel pra data=`{$date}` — {$inserted} pares (module, span_name) agregados.");
        }

        $elapsed = (int) round((microtime(true) - $startedAt) * 1000);
        $this->info(sprintf(
            'Aggregate OK — %d aggregates persistidos em mcp_observability_aggregates_daily (%dms).',
            $inserted,
            $elapsed,
        ));

        return self::SUCCESS;
    }
}
