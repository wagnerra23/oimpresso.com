<?php

namespace Modules\ADS\Console\Commands;

use Illuminate\Console\Command;
use Modules\ADS\Services\AutoTaskGeneratorService;

/**
 * php artisan ads:auto-generate-tasks [--business=1] [--dry-run]
 *
 * Roda 1× por hora. Scans estado real do projeto (ADRs sem frontmatter,
 * links MD quebrados, session log gap, MCP sync) e gera decisions.
 *
 * Limites: 3 tasks/hora, 10/dia (dentro do AutoTaskGeneratorService).
 */
class AutoGenerateTasksCommand extends Command
{
    protected $signature = 'ads:auto-generate-tasks
                            {--business=1}
                            {--dry-run : Lista candidatas sem submeter}';

    protected $description = 'Self-Instruct goal-directed: scan estado real e gera tasks';

    public function handle(AutoTaskGeneratorService $service): int
    {
        $bizId = (int) $this->option('business');
        $this->info("Gerando tasks pra business #{$bizId}…");

        if ($this->option('dry-run')) {
            $this->warn('DRY-RUN: candidatas só impressas, não submetidas');
            // Não temos hook dry-run no service ainda; retorna 0
            $this->line('  (dry-run completo seria mais útil — V2)');
            return self::SUCCESS;
        }

        $result = $service->generateTasks($bizId);

        $this->line(sprintf(
            "Scanned: %d candidatas | Generated: %d decisions | Skipped: %d",
            $result['scanned'], $result['generated'], count($result['skipped'])
        ));

        if (! empty($result['skipped'])) {
            $this->newLine();
            $this->warn('Skipped:');
            foreach ($result['skipped'] as $s) {
                $reason = $s['reason'] ?? 'unknown';
                $cand = $s['cand'] ?? '';
                $this->line("  - {$reason}" . ($cand ? " (cand: {$cand})" : ''));
            }
        }

        return self::SUCCESS;
    }
}
