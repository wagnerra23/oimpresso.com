<?php

declare(strict_types=1);

namespace Modules\Jana\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Services\Memoria\Freshness\ReindexJobDispatcher;
use Modules\Jana\Services\Memoria\Freshness\StalenessDetectorService;

/**
 * GAP D7 #2 (auditoria memoria-senior 2026-05-15) — Comando de health da memória.
 *
 * Roda diariamente (cron 04:30 BRT em app/Console/Kernel.php) avaliando frescura
 * dos docs em `mcp_memory_documents` (4 níveis FRESH/WARM/STALE/CRITICAL),
 * detectando drift (DB↔git), criando alertas idempotentes e (opcionalmente)
 * disparando re-index pros stale/drift.
 *
 * Convenção `--detail` em vez de `--verbose` ([rule commands.md](.claude/rules/commands.md))
 * — Symfony Console reserva `--verbose`.
 *
 * Uso:
 *   php artisan jana:freshness-check                       # só relatório
 *   php artisan jana:freshness-check --json                # output machine-readable
 *   php artisan jana:freshness-check --alert               # persiste mcp_alertas_eventos
 *   php artisan jana:freshness-check --reindex --limit=50  # dispatch jobs
 *   php artisan jana:freshness-check --dry-run             # nada persiste / nada dispatch
 */
class FreshnessCheckCommand extends Command
{
    protected $signature = 'jana:freshness-check
                            {--alert : Cria alertas mcp_alertas_eventos pra CRITICAL (idempotente por dia)}
                            {--reindex : Dispatch ReindexarDocumentoJob pra stale+drift (max --limit)}
                            {--limit=100 : Limite máximo de jobs enfileirados quando --reindex}
                            {--json : Output JSON machine-readable em vez de tabela}
                            {--dry-run : Não persiste alertas nem dispatcha jobs}
                            {--detail : Log detalhado por doc (substituí --verbose por convenção Symfony)}';

    protected $description = 'GAP D7 #2 — Freshness pipeline: classifica docs memory + alerta CRITICAL + dispatch re-index';

    public function handle(
        StalenessDetectorService $detector,
        ReindexJobDispatcher $dispatcher,
    ): int {
        if (! (bool) config('copiloto.freshness.enabled', true)) {
            $this->warn('Freshness pipeline disabled (config copiloto.freshness.enabled=false).');
            return 0;
        }

        $dryRun = (bool) $this->option('dry-run');

        // Fase 1 — contagem por nível
        $contagem = $detector->contagemPorNivel();

        // Fase 2 — listas stale + drift + critical
        $stale    = $detector->detectStale();
        $critical = $detector->detectCritical();
        $drift    = $detector->detectDrift();

        $stats = [
            'contagem'      => $contagem,
            'stale_qty'     => count($stale),
            'critical_qty'  => count($critical),
            'drift_qty'     => count($drift),
            'alertas_criados' => 0,
            'jobs_dispatched' => 0,
            'dry_run'       => $dryRun,
        ];

        // Fase 3 — alertas CRITICAL
        if ($this->option('alert') && ! $dryRun && count($critical) > 0) {
            $stats['alertas_criados'] = $detector->alertCritical($critical);
        }

        // Fase 4 — reindex stale/drift
        if ($this->option('reindex') && ! $dryRun) {
            $limit = max(1, (int) $this->option('limit'));
            $stats['jobs_dispatched'] = $dispatcher->dispatchStaleAndDrift($limit);
        }

        // Fase 5 — output
        if ($this->option('json')) {
            $this->line(json_encode([
                'checked_at' => now()->toIso8601String(),
                'stats'      => $stats,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->renderTabela($stats);
        }

        if ($this->option('detail')) {
            $this->renderDetalhe($stale, $drift, $critical);
        }

        // Log estruturado
        Log::channel('copiloto-ai')->info('jana:freshness-check', $stats);

        // Exit code: 1 se há CRITICAL ou drift sem reindex (cron/CI alerta)
        $temProblema = $stats['critical_qty'] > 0 || $stats['drift_qty'] > 0;
        return $temProblema ? 1 : 0;
    }

    protected function renderTabela(array $stats): void
    {
        $this->info('═══ Freshness Pipeline — Memory Health ═══');
        $contagem = $stats['contagem'];
        $this->table(
            ['Nível', 'Quantidade', '% do total'],
            [
                ['FRESH',    $contagem['FRESH'],    $this->pct($contagem['FRESH'],    $contagem['total'])],
                ['WARM',     $contagem['WARM'],     $this->pct($contagem['WARM'],     $contagem['total'])],
                ['STALE',    $contagem['STALE'],    $this->pct($contagem['STALE'],    $contagem['total'])],
                ['CRITICAL', $contagem['CRITICAL'], $this->pct($contagem['CRITICAL'], $contagem['total'])],
                ['Total',    $contagem['total'],    '100%'],
            ]
        );

        $this->line('');
        $this->line("Stale total:    {$stats['stale_qty']}");
        $this->line("Critical (≥30d): {$stats['critical_qty']}");
        $this->line("Drift (DB↔git): {$stats['drift_qty']}");
        $this->line('');

        if ($stats['dry_run']) {
            $this->warn('DRY-RUN — alertas e jobs NÃO foram persistidos/dispatchados.');
        } else {
            $this->line("Alertas criados: {$stats['alertas_criados']}");
            $this->line("Jobs reindex dispatched: {$stats['jobs_dispatched']}");
        }
    }

    protected function renderDetalhe(array $stale, array $drift, array $critical): void
    {
        $this->line('');
        $this->info('--- CRITICAL ---');
        foreach (array_slice($critical, 0, 25) as $doc) {
            $idade = $doc->indexed_at ? $doc->indexed_at->diffForHumans() : 'NUNCA indexed';
            $this->line(sprintf('  [%s] %s (%s)', $doc->type, $doc->slug, $idade));
        }
        if (count($critical) > 25) {
            $this->line(sprintf('  ... +%d (truncado)', count($critical) - 25));
        }

        $this->line('');
        $this->info('--- DRIFT ---');
        foreach (array_slice($drift, 0, 25) as $doc) {
            $this->line(sprintf('  [%s] %s — updated_at=%s indexed_at=%s',
                $doc->type, $doc->slug,
                $doc->updated_at?->toDateTimeString() ?? '-',
                $doc->indexed_at?->toDateTimeString() ?? '-',
            ));
        }
        if (count($drift) > 25) {
            $this->line(sprintf('  ... +%d (truncado)', count($drift) - 25));
        }
    }

    protected function pct(int $valor, int $total): string
    {
        if ($total === 0) {
            return '—';
        }
        return number_format(($valor / $total) * 100, 1) . '%';
    }
}
