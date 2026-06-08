<?php

declare(strict_types=1);

namespace Modules\Governance\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Governance\Services\ModuleGradeService;

/**
 * Snapshot diário das notas Module Grades (rubrica v3 — ADR 0155).
 *
 * Persiste 1 row por módulo em `mcp_module_grades_history` pra alimentar
 * sparkline 7d em /governance/module-grades/{module} (Show.tsx).
 *
 * Schedule: daily 06:00 BRT pareado com `jana:health-check` (app/Console/Kernel.php).
 *
 * Uso CLI:
 *   php artisan module:grade-snapshot           # snapshot todos (~34 módulos)
 *   php artisan module:grade-snapshot --detail  # log per-module
 *
 * NÃO usa `--verbose` (Symfony reserved — vide rule path-scoped commands.md).
 *
 * @see Modules/Governance/Database/Migrations/2026_05_16_120000_create_mcp_module_grades_history_table.php
 * @see memory/decisions/0153-module-grade-rubrica-v1.md
 */
class ModuleGradeSnapshotCommand extends Command
{
    protected $signature = 'module:grade-snapshot
                            {--detail : Log linha por módulo durante snapshot}';

    protected $description = 'Persiste snapshot diário das notas Module Grades em mcp_module_grades_history (cron daily 06:00 BRT)';

    public function handle(ModuleGradeService $service): int
    {
        $startedAt = microtime(true);
        $now = now();

        $grades = $service->gradeAllModules();
        $count = $grades->count();

        if ($count === 0) {
            $this->warn('Nenhum módulo detectado em Modules/ — snapshot abortado.');
            return self::SUCCESS;
        }

        $rows = [];
        foreach ($grades as $grade) {
            $rows[] = [
                'module'      => $grade['module'],
                'score'       => (int) $grade['score'],
                'bucket'      => $grade['bucket'],
                'dimensions'  => json_encode($grade['dimensions'], JSON_UNESCAPED_UNICODE),
                'snapshot_at' => $now,
            ];

            if ($this->option('detail')) {
                $this->line(sprintf(
                    '  %-30s %3d/100 · %s',
                    $grade['module'],
                    (int) $grade['score'],
                    $grade['bucket'],
                ));
            }
        }

        DB::table('mcp_module_grades_history')->insert($rows);

        $elapsed = round((microtime(true) - $startedAt) * 1000);
        $this->info("Snapshot OK — {$count} módulos persistidos em mcp_module_grades_history ({$elapsed}ms).");

        return self::SUCCESS;
    }
}
