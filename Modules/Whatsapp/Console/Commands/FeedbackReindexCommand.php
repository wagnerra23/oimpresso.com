<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\Whatsapp\Services\FeedbackIndexGenerator;

/**
 * `feedback:reindex` — rescore + gera INDEX.md + archive trimestral.
 *
 * Wagner 2026-05-27 — Fase B do ADR 0195. Substitui job manual de triagem
 * dos feedbacks: a cada execução, recalcula relevance_score, gera
 * `memory/feedback/INDEX.md` (top 20 HOT auto-loaded em sessão Claude)
 * e o `memory/feedback/archive/YYYY-QN.md` (digest COLD trimestral).
 *
 * Uso:
 *   php artisan feedback:reindex                       # todos businesses
 *   php artisan feedback:reindex --business=1          # smoke biz=1
 *   php artisan feedback:reindex --skip-archive        # só INDEX, mais rápido
 *   php artisan feedback:reindex --skip-index          # só rescore + archive
 *
 * Schedule canônico: domingo 03:00 BRT (Kernel.php).
 *
 * Idempotente — re-rodar mesma janela substitui arquivos. Seguro pra cron.
 *
 * @see Modules\Whatsapp\Services\FeedbackIndexGenerator
 * @see memory/decisions/0195-feedback-relevance-scoring-decay-adaptativo.md
 */
class FeedbackReindexCommand extends Command
{
    protected $signature = 'feedback:reindex
                            {--business= : business_id alvo (default: todos)}
                            {--skip-archive : pula geração de archive trimestral}
                            {--skip-index : pula geração de INDEX.md}';

    protected $description = 'Recompute relevance_score + gera INDEX.md (HOT) + archive trimestral (COLD)';

    public function handle(FeedbackIndexGenerator $generator): int
    {
        $businessId = $this->option('business') ? (int) $this->option('business') : null;
        $skipArchive = (bool) $this->option('skip-archive');
        $skipIndex = (bool) $this->option('skip-index');

        $startedAt = microtime(true);

        $this->info('🔄 Feedback reindex iniciando' . ($businessId ? " · biz={$businessId}" : ' · todos businesses'));

        // Fase 1 — rescore
        $stats = $generator->reindexScores($businessId);
        $this->info("✓ Rescore: {$stats['processed']} processados, {$stats['rescored']} re-scored, {$stats['skipped']} sem mudança");

        // Fase 2 — INDEX.md (top 20 HOT)
        if (! $skipIndex) {
            $indexPath = $generator->generateIndex($businessId);
            $relIndex = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $indexPath);
            $this->info('✓ INDEX gerado: ' . $relIndex);
        } else {
            $this->warn('⊘ INDEX skipped');
        }

        // Fase 3 — archive trimestral (COLD digest)
        if (! $skipArchive) {
            $archivePath = $generator->generateArchive();
            $relArchive = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $archivePath);
            $this->info('✓ Archive gerado: ' . $relArchive);
        } else {
            $this->warn('⊘ Archive skipped');
        }

        $duration = round((microtime(true) - $startedAt) * 1000);
        $this->info("✓ Concluído em {$duration}ms");

        Log::info('[feedback.reindex] complete', [
            'business' => $businessId,
            'stats' => $stats,
            'duration_ms' => $duration,
            'skip_index' => $skipIndex,
            'skip_archive' => $skipArchive,
        ]);

        return self::SUCCESS;
    }
}
