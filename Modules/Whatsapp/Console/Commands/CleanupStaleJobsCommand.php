<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * US-WA-084 — Cleanup de jobs presos na fila `whatsapp-history`.
 *
 * Jobs com `reserved_at` antigo (worker crashou mid-flight) ou `created_at`
 * antigo (jobs órfãos, worker nunca pegou) ficam consumindo queue depth
 * sem progresso. Esta command:
 *
 *   1. DELETE jobs reservados há mais de `--max-age` horas (default 6h)
 *   2. DELETE jobs criados há mais de `--max-age` horas que nunca foram
 *      reservados (provavelmente lixo de teste / queue worker offline há dias)
 *   3. Reporta contagens e loga warning se >0
 *
 * Uso:
 *   php artisan whatsapp:jobs-cleanup-stale                 # >6h
 *   php artisan whatsapp:jobs-cleanup-stale --max-age=12    # >12h
 *   php artisan whatsapp:jobs-cleanup-stale --dry-run       # preview
 *
 * Schedule: hourly via app/Console/Kernel.php
 *
 * @see Modules/Whatsapp/Http/Middleware/EnforceWebhookBackpressure.php
 * @see Modules/Whatsapp/Jobs/PersistHistorySyncBatchJob.php
 */
class CleanupStaleJobsCommand extends Command
{
    protected $signature = 'whatsapp:jobs-cleanup-stale
                            {--max-age= : Idade em horas pra purga (default config whatsapp.backpressure.stale_job_max_age_hours)}
                            {--queue= : Nome da fila (default config whatsapp.backpressure.queue_name)}
                            {--dry-run : Só conta, sem deletar}';

    protected $description = 'Purga jobs presos da fila whatsapp-history (worker crashed / órfãos).';

    public function handle(): int
    {
        $maxAge = $this->option('max-age');
        $maxAgeHours = $maxAge !== null
            ? (int) $maxAge
            : (int) config('whatsapp.backpressure.stale_job_max_age_hours', 6);

        $queueName = (string) ($this->option('queue') ?? config('whatsapp.backpressure.queue_name', 'whatsapp-history'));
        $dryRun = (bool) $this->option('dry-run');

        if ($maxAgeHours < 1) {
            $this->error('--max-age precisa ser ≥1');

            return self::FAILURE;
        }

        $cutoffUnix = now()->subHours($maxAgeHours)->timestamp;

        // jobs.reserved_at e created_at são unsigned int (epoch seconds) em Laravel
        $reservedCount = DB::table('jobs')
            ->where('queue', $queueName)
            ->whereNotNull('reserved_at')
            ->where('reserved_at', '<', $cutoffUnix)
            ->count();

        $orphanedCount = DB::table('jobs')
            ->where('queue', $queueName)
            ->whereNull('reserved_at')
            ->where('created_at', '<', $cutoffUnix)
            ->count();

        $total = $reservedCount + $orphanedCount;

        if ($dryRun) {
            $this->info(sprintf(
                '[DRY RUN] %s: %d reservados-presos + %d órfãos = %d total (cutoff %dh)',
                $queueName,
                $reservedCount,
                $orphanedCount,
                $total,
                $maxAgeHours,
            ));

            return self::SUCCESS;
        }

        $deletedReserved = DB::table('jobs')
            ->where('queue', $queueName)
            ->whereNotNull('reserved_at')
            ->where('reserved_at', '<', $cutoffUnix)
            ->delete();

        $deletedOrphaned = DB::table('jobs')
            ->where('queue', $queueName)
            ->whereNull('reserved_at')
            ->where('created_at', '<', $cutoffUnix)
            ->delete();

        $deleted = $deletedReserved + $deletedOrphaned;

        if ($deleted > 0) {
            Log::warning('[whatsapp.jobs-cleanup-stale] jobs purgados', [
                'queue' => $queueName,
                'reserved_stuck' => $deletedReserved,
                'orphaned' => $deletedOrphaned,
                'cutoff_hours' => $maxAgeHours,
            ]);
        }

        $this->info(sprintf(
            '%s: deletados %d reservados-presos + %d órfãos = %d total (cutoff %dh)',
            $queueName,
            $deletedReserved,
            $deletedOrphaned,
            $deleted,
            $maxAgeHours,
        ));

        return self::SUCCESS;
    }
}
