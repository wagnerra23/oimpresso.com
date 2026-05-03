<?php

namespace Modules\ADS\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\ADS\Services\PlannerService;

/**
 * php artisan ads:plan-decisions [--id=N] [--limit=3]
 *
 * Decompõe decisions complexas em subtarefas via PlannerAgent.
 *
 * Critério de complexidade (heurística):
 *   - event_type em [service_layer_refactor, new_module_creation, db_schema_change]
 *   - SEM parent_decision_id (não decompõe subtarefa de subtarefa)
 *   - SEM children ainda (não re-planeja)
 *   - destination = pending_wagner OU brain_b com instruction já gerada
 */
class PlanDecisionsCommand extends Command
{
    protected $signature = 'ads:plan-decisions
                            {--id= : Decompõe apenas decision específica}
                            {--limit=3 : Máx decisions decompostas por execução}';

    protected $description = 'PlannerAgent: decompõe decisions complexas em subtarefas (T9)';

    private const COMPLEX_TYPES = [
        'service_layer_refactor',
        'new_module_creation',
        'db_schema_change',
        'composer_json_change',
    ];

    public function handle(PlannerService $service): int
    {
        $query = DB::table('mcp_dual_brain_decisions');

        if ($id = $this->option('id')) {
            $query->where('id', $id);
        } else {
            $query->whereIn('event_type', self::COMPLEX_TYPES)
                  ->whereNull('parent_decision_id')
                  ->whereIn('destination', ['pending_wagner', 'brain_b'])
                  ->where('outcome', 'cancelled')
                  ->whereNotIn('id', function ($sub) {
                      $sub->select('parent_decision_id')
                          ->from('mcp_dual_brain_decisions')
                          ->whereNotNull('parent_decision_id');
                  });
        }

        $decisions = $query->orderBy('id')->limit((int) $this->option('limit'))->get();

        if ($decisions->isEmpty()) {
            $this->info('Nenhuma decision complexa pra decompor.');
            return self::SUCCESS;
        }

        $this->info("Decompondo {$decisions->count()} decision(s):");

        $totalSubtasks = 0;
        foreach ($decisions as $d) {
            $this->line("  #{$d->id} {$d->event_type} ({$d->domain})");
            $result = $service->plan($d->id);

            if ($result['error']) {
                $this->error("    ✗ falhou: {$result['error']}");
                continue;
            }

            if (! empty($result['plan']['rejected'])) {
                $this->warn("    ⚠ planner rejeitou: " . ($result['plan']['rejection_reason'] ?? ''));
                continue;
            }

            $count = $result['subtasks_created'];
            $confidence = $result['plan']['confidence'] ?? '?';
            $summary = mb_strimwidth($result['plan']['decomposition_summary'] ?? '', 0, 80, '…');

            $this->info("    ✓ {$count} subtarefas criadas (conf={$confidence})");
            $this->line("      Estratégia: {$summary}");
            $totalSubtasks += $count;
        }

        $this->newLine();
        $this->info("Total: {$totalSubtasks} subtarefas criadas");

        return self::SUCCESS;
    }
}
