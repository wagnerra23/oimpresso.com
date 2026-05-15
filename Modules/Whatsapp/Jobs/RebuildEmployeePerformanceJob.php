<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Whatsapp\Entities\EmployeePerformance;
use Modules\Whatsapp\Services\EmployeePerformance\EmployeePerformanceRebuilder;

/**
 * US-WA-VOZ-003 — Async rebuild de 1 employee_performance.
 *
 * Queue separada `employee-performance` (não compete com whatsapp-history).
 * Backoff 60s/300s. 3 tries.
 *
 * @see Modules/Whatsapp/Services/EmployeePerformance/EmployeePerformanceRebuilder.php
 */
class RebuildEmployeePerformanceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;

    public function backoff(): array
    {
        return [60, 300];
    }

    public function __construct(
        public readonly int $businessId,
        public readonly ?int $userId = null,
        public readonly ?string $heuristicName = null,
        public readonly string $via = EmployeePerformance::REBUILT_VIA_CRON_DAILY,
    ) {
        $this->onConnection('database');
        $this->onQueue('employee-performance');
    }

    public function handle(EmployeePerformanceRebuilder $rebuilder): void
    {
        $rebuilder->rebuild($this->businessId, $this->userId, $this->heuristicName, $this->via);
    }

    public function failed(\Throwable $exception): void
    {
        Log::channel('single')->error('[employee_performance.rebuild_job_failed]', [
            'metric_name' => 'employee_performance_rebuild_job_failed',
            'business_id' => $this->businessId,
            'user_id' => $this->userId,
            'heuristic_name' => $this->heuristicName,
            'via' => $this->via,
            'error' => $exception->getMessage(),
        ]);
    }
}
