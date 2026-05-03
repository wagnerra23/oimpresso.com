<?php

namespace Modules\ADS\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\ADS\Services\BrainBService;

/**
 * Processa decisions pendentes com destination=brain_b.
 *
 * Uso:
 *   php artisan ads:process-brain-b              # processa até 5 pendentes
 *   php artisan ads:process-brain-b --limit=20
 *   php artisan ads:process-brain-b --id=42      # processa apenas uma específica
 *   php artisan ads:process-brain-b --dry-run    # lista sem chamar Claude API
 */
class ProcessBrainBCommand extends Command
{
    protected $signature = 'ads:process-brain-b
                            {--limit=5 : Quantas decisions processar nesta rodada}
                            {--id= : Processa apenas a decision com este id}
                            {--dry-run : Lista pendentes sem chamar Claude API}';

    protected $description = 'Processa decisions pendentes do Brain B (chama Claude API)';

    public function handle(BrainBService $service): int
    {
        $query = DB::table('mcp_dual_brain_decisions')
            ->where('destination', 'brain_b')
            ->where('brain_used', 'none');

        if ($id = $this->option('id')) {
            $query->where('id', $id);
        }

        $decisions = $query->orderBy('id')
            ->limit((int) $this->option('limit'))
            ->get();

        if ($decisions->isEmpty()) {
            $this->info('Nenhuma decision pendente em brain_b.');
            return self::SUCCESS;
        }

        $this->info("Processando {$decisions->count()} decision(s):");

        $dryRun = (bool) $this->option('dry-run');
        $ok = 0;
        $fail = 0;

        foreach ($decisions as $d) {
            $this->line("  #{$d->id} {$d->event_type} ({$d->domain}) risk={$d->risk_score}");

            if ($dryRun) {
                continue;
            }

            $result = $service->process($d->id);

            if ($result['error']) {
                $this->error("    falhou: {$result['error']}");
                $fail++;
                continue;
            }

            $instruction = $result['instruction'];
            $title = $instruction['title'] ?? '(sem title)';
            $confInstr = $instruction['confidence_in_instruction'] ?? '?';
            $this->info("    ✓ \"{$title}\" (conf_instr={$confInstr})");
            $ok++;
        }

        $this->newLine();
        $this->info("OK: {$ok} · Falhou: {$fail}");

        return self::SUCCESS;
    }
}
