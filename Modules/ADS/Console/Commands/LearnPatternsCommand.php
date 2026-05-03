<?php

namespace Modules\ADS\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\ADS\Services\PatternLearningService;

/**
 * php artisan ads:learn-patterns [--business=1] [--detect-drift]
 *
 * Roda 1× por dia. Para cada decision com outcome != cancelled das últimas 24h
 * que ainda não foi computada em pattern, registra. Opcionalmente detecta drift.
 */
class LearnPatternsCommand extends Command
{
    protected $signature = 'ads:learn-patterns
                            {--business=1 : Business id alvo (default 1; use "all" para todos)}
                            {--detect-drift : Roda detector de drift e imprime}';

    protected $description = 'Atualiza mcp_decision_patterns com decisions recentes (Wilson Score)';

    public function handle(PatternLearningService $service): int
    {
        $bizArg = $this->option('business');
        $businessIds = $bizArg === 'all'
            ? DB::table('business')->pluck('id')->all()
            : [(int) $bizArg];

        foreach ($businessIds as $bizId) {
            $this->info("Business #{$bizId}");

            $decisions = DB::table('mcp_dual_brain_decisions')
                ->where('business_id', $bizId)
                ->whereIn('outcome', ['success', 'fail', 'wagner_modified', 'wagner_rejected'])
                ->where(function ($q) {
                    $q->where('resolved_at', '>=', now()->subDay())
                      ->orWhere('created_at', '>=', now()->subDay());
                })
                ->orderBy('id')
                ->get();

            $count = 0;
            foreach ($decisions as $d) {
                $service->recordOutcome($d);
                $count++;
            }
            $this->line("  Padrões atualizados: {$count}");

            // Promotion candidates
            $candidates = $service->listPromotionCandidates($bizId);
            if (! empty($candidates)) {
                $this->newLine();
                $this->warn("  ⚡ {$bizId} candidato(s) a promoção pra hardcoded ALLOW_BRAIN_A:");
                foreach ($candidates as $c) {
                    $this->line(sprintf(
                        "    - (%s, %s) success=%d/%d wilson_lb=%.3f",
                        $c['domain'], $c['event_type'], $c['success_count'], $c['total_count'], $c['wilson_lower_bound']
                    ));
                    $this->line("      → {$c['recommendation']}");
                }
            }

            if ($this->option('detect-drift')) {
                $drifts = $service->detectDrift($bizId);
                if (! empty($drifts)) {
                    $this->newLine();
                    $this->error('  ⚠ DRIFT detectado:');
                    foreach ($drifts as $d) {
                        $this->line("    - {$d['recommendation']}");
                    }
                }
            }
        }

        return self::SUCCESS;
    }
}
