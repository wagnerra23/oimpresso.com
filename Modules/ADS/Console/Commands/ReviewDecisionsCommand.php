<?php

namespace Modules\ADS\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\ADS\Services\ReviewerService;

/**
 * php artisan ads:review-decisions [--limit=10] [--id=N]
 *
 * Roda a cada 15min. Pega decisions com outcome=success/wagner_modified que ainda
 * NÃO têm review_score, e dispara ReviewerAgent (Haiku, n=2 self-consistency).
 */
class ReviewDecisionsCommand extends Command
{
    protected $signature = 'ads:review-decisions
                            {--limit=10 : Máx decisions reviewadas por execução}
                            {--id= : Reviewar apenas decision específica}
                            {--force : Re-reviewar mesmo se já tiver review_score}';

    protected $description = 'Roda ReviewerAgent (G-Eval) em decisions sem review_score';

    public function handle(ReviewerService $service): int
    {
        $query = DB::table('mcp_dual_brain_decisions');

        if ($id = $this->option('id')) {
            $query->where('id', $id);
            if (! $this->option('force')) {
                $query->whereNull('review_score');
            }
        } else {
            $query->whereIn('outcome', ['success', 'wagner_modified', 'fail'])
                  ->whereNull('review_score')
                  ->where('created_at', '>=', now()->subHours(48));
        }

        $decisions = $query->orderBy('id')->limit((int) $this->option('limit'))->get();

        if ($decisions->isEmpty()) {
            $this->info('Nenhuma decision pendente de review.');
            return self::SUCCESS;
        }

        $this->info("Revisando {$decisions->count()} decision(s):");

        $highScore = 0;
        $lowScore = 0;
        $retries = 0;

        foreach ($decisions as $d) {
            $this->line("  #{$d->id} {$d->event_type} ({$d->domain}) outcome={$d->outcome}");
            $result = $service->review($d->id);

            $tag = $result['score'] >= 70 ? "✓" : ($result['score'] >= 50 ? "⚠" : "✗");
            $this->line(sprintf(
                "    %s score=%d conf=%.2f retry=%s",
                $tag, $result['score'], $result['confidence'], $result['should_retry'] ? 'sim' : 'não',
            ));

            if ($result['score'] >= 70) $highScore++;
            elseif ($result['score'] < 50) $lowScore++;
            if ($result['should_retry']) $retries++;
        }

        $this->newLine();
        $this->info(sprintf("Reviewed: %d | ≥70: %d | <50: %d | Retries marcados: %d", $decisions->count(), $highScore, $lowScore, $retries));

        return self::SUCCESS;
    }
}
