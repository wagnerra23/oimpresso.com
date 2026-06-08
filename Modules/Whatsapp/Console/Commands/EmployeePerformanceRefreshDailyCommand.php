<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Whatsapp\Entities\EmployeePerformance;
use Modules\Whatsapp\Jobs\RebuildEmployeePerformanceJob;

/**
 * US-WA-VOZ-003 — Cron daily refresh employee_performance.
 *
 * Roda 02:30h BRT via `app/Console/Kernel.php`. Re-dispatcha rebuild
 * pra todos atendentes existentes em `employee_performance`.
 *
 * Cron daily 02:30 BRT (offset +30min do customer-memory:refresh-daily
 * 02:00h pra evitar disputa de queue/DB).
 *
 * @see Modules/Whatsapp/Jobs/RebuildEmployeePerformanceJob.php
 */
class EmployeePerformanceRefreshDailyCommand extends Command
{
    protected $signature = 'employee-performance:refresh-daily
        {--business= : business_id alvo (default: todos)}
        {--detail : log breakdown por business}';

    protected $description = 'Cron daily — re-dispatcha rebuild employee_performance (US-WA-VOZ-003).';

    public function handle(): int
    {
        $businessOpt = $this->option('business');
        $detail = (bool) $this->option('detail');

        $businessIds = $businessOpt !== null
            ? [(int) $businessOpt]
            : DB::table('employee_performance')
                ->select('business_id')
                ->distinct()
                ->orderBy('business_id')
                ->pluck('business_id')
                ->map(fn ($id) => (int) $id)
                ->all();

        if (empty($businessIds)) {
            $this->info('Nenhum business com employee_performance — nada a fazer.');
            return Command::SUCCESS;
        }

        $grand = 0;
        $rows = [];

        foreach ($businessIds as $bizId) {
            $perfs = DB::table('employee_performance')
                ->where('business_id', $bizId)
                ->get(['user_id', 'heuristic_name']);

            foreach ($perfs as $p) {
                RebuildEmployeePerformanceJob::dispatch(
                    $bizId,
                    $p->user_id ? (int) $p->user_id : null,
                    $p->heuristic_name,
                    EmployeePerformance::REBUILT_VIA_CRON_DAILY,
                );
            }
            $grand += $perfs->count();
            $rows[] = ['biz' => $bizId, 'dispatched' => $perfs->count()];
            if ($detail) {
                $this->info("biz={$bizId}: {$perfs->count()} jobs dispatched");
            }
        }

        if (! $detail && ! empty($rows)) {
            $this->table(['biz', 'dispatched'], $rows);
        }

        $this->info("Total dispatched: {$grand}");
        return Command::SUCCESS;
    }
}
