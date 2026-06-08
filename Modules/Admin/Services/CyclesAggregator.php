<?php

namespace Modules\Admin\Services;

use App\Util\OtelHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * CyclesAggregator — Widget W3 (cycles ativos + tasks por dev).
 *
 * Lê tabelas MCP canônicas:
 * - `mcp_cycles` (ciclos) — ADR 0070
 * - `mcp_tasks` (tasks Jira-style)
 *
 * Sprint 1: lê SQL direto (single-business=1 superadmin Wagner).
 * Sprint 2: pode chamar tools MCP `cycles-active` + `my-work` via DI.
 *
 * Graceful fallback: tabela ausente → stub com instruções.
 */
class CyclesAggregator
{
    public function fetch(): array
    {
        // D9.a OTel (Wave 17): span envolve query mcp_cycles + tasks_by_dev
        // GROUP BY. Zero-cost se otel.enabled=false.
        return OtelHelper::spanBiz('admin.cycles_aggregator.fetch', function () {
        try {
            if (! Schema::hasTable('mcp_cycles') || ! Schema::hasTable('mcp_tasks')) {
                return $this->stub('tables_missing');
            }

            // Cycles ativos (status=active)
            $cycles = DB::table('mcp_cycles')
                ->where('status', 'active')
                ->orderByDesc('start_date')
                ->limit(3)
                ->get(['id', 'name', 'start_date', 'end_date', 'goal_summary'])
                ->toArray();

            // Tasks por dev (current cycle)
            $currentCycleId = $cycles[0]->id ?? null;

            $tasksByDev = $currentCycleId
                ? DB::table('mcp_tasks')
                    ->select('owner', DB::raw('COUNT(*) as total'),
                        DB::raw("SUM(CASE WHEN status='doing' THEN 1 ELSE 0 END) as doing"),
                        DB::raw("SUM(CASE WHEN status='done' THEN 1 ELSE 0 END) as done"))
                    ->where('cycle_id', $currentCycleId)
                    ->whereNotNull('owner')
                    ->groupBy('owner')
                    ->orderBy('owner')
                    ->get()
                    ->toArray()
                : [];

            return [
                'available'      => true,
                'cycles_active'  => $cycles,
                'tasks_by_dev'   => $tasksByDev,
                'current_cycle'  => $currentCycleId,
            ];
        } catch (\Throwable $e) {
            Log::warning('admin.widget.cycles.error', ['error' => $e->getMessage()]);
            return $this->stub('exception:' . substr($e->getMessage(), 0, 120));
        }
        }, ['component' => 'admin.widget.w3']);
    }

    private function stub(string $reason): array
    {
        return [
            'available'     => false,
            'reason'        => $reason,
            'cycles_active' => [],
            'tasks_by_dev'  => [],
        ];
    }
}
