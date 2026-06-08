<?php

namespace Modules\ProjectMgmt\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Jana\Entities\Mcp\McpCycle;
use Modules\Jana\Entities\Mcp\McpProject;
use Modules\Jana\Entities\Mcp\McpTask;
use Modules\Jana\Entities\Mcp\McpTaskEvent;

/**
 * BurndownController — /project-mgmt/burndown (US-TR-206).
 *
 * Gráfico burn-down do cycle ativo (ou cycle escolhido):
 *   - linha "ideal": decrescente linear de total inicial até 0 no end_date
 *   - linha "real": pra cada dia, conta tasks status=done <= aquele dia
 *
 * Reconstrução histórica via `mcp_task_events` (event_type=status_changed,
 * to_value=done). Aceita-se ruído pré-existente.
 *
 * Permissão: copiloto.mcp.usage.all.
 */
class BurndownController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('can:copiloto.mcp.usage.all');
    }

    public function index(Request $request): Response
    {
        $project = $this->resolveProject($request);
        $projectId = $project?->id;
        $cycleId = (int) $request->get('cycle', 0) ?: null;

        // RUNBOOK-inertia-defer-pattern.md (Wave 11 D6.a) — defer queries pesadas.
        // `project` cheap eager; cycles list/series/kpis/cycle deferidos.
        // series/kpis/cycle compartilham mesma query histórica McpTaskEvent —
        // agrupados em 1 closure pra evitar reprocessar.
        return Inertia::render('ProjectMgmt/Burndown/Index', [
            'project' => $project ? ['id' => $project->id, 'key' => $project->key, 'name' => $project->name] : null,
            'cycles'  => Inertia::defer(fn () => $this->buildCyclesPayload($projectId)),
            'cycle'   => Inertia::defer(fn () => $this->buildBurndownPayload($projectId, $cycleId)['cycle']),
            'series'  => Inertia::defer(fn () => $this->buildBurndownPayload($projectId, $cycleId)['series']),
            'kpis'    => Inertia::defer(fn () => $this->buildBurndownPayload($projectId, $cycleId)['kpis']),
            'filters' => ['cycle' => $cycleId],
        ]);
    }

    /** @return array<int,array<string,mixed>> */
    protected function buildCyclesPayload(?int $projectId): array
    {
        if (! $projectId) {
            return [];
        }
        return McpCycle::where('project_id', $projectId)
            ->whereIn('status', ['planning', 'active', 'closed'])
            ->orderBy('start_date', 'desc')
            ->limit(10)
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id, 'key' => $c->key, 'name' => $c->name,
                'status' => $c->status, 'is_active' => $c->status === 'active',
            ])->all();
    }

    /**
     * Constrói cycle header + series histórica + kpis (compartilham mesma query
     * de McpTask/McpTaskEvent). Memoiza por (projectId, cycleId) na request.
     *
     * @return array{cycle: ?array<string,mixed>, series: array<int,array<string,mixed>>, kpis: array<string,mixed>}
     */
    protected function buildBurndownPayload(?int $projectId, ?int $cycleId): array
    {
        static $cache = [];
        $key = "{$projectId}::{$cycleId}";
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $project = $projectId ? McpProject::find($projectId) : null;
        $cycle = $cycleId
            ? McpCycle::find($cycleId)
            : ($project ? $project->activeCycle() : null);

        $emptyKpis = [
            'total' => 0, 'done' => 0, 'remaining' => 0, 'percent_done' => 0,
            'pace_per_day' => null, 'forecast_days' => null,
        ];

        if (! $cycle) {
            return $cache[$key] = ['cycle' => null, 'series' => [], 'kpis' => $emptyKpis];
        }

        $tasks = McpTask::where('cycle_id', $cycle->id)
            ->whereNotIn('status', ['cancelled'])
            ->get();
        $total = $tasks->count();
        $done  = $tasks->where('status', 'done')->count();

        $events = McpTaskEvent::whereIn('task_id', $tasks->pluck('task_id')->all())
            ->where('event_type', 'status_changed')
            ->where('to_value', 'done')
            ->orderBy('created_at')
            ->get();

        $start = Carbon::parse($cycle->start_date);
        $end   = Carbon::parse($cycle->end_date);
        $today = Carbon::today();

        $series = [];
        $totalDays = max(1, $start->diffInDays($end));
        $idealStep = $total / $totalDays;

        $cursor = $start->copy();
        $i = 0;
        while ($cursor->lte($end)) {
            $iso = $cursor->toDateString();

            $real = null;
            if ($cursor->lte($today)) {
                $doneByDay = $events->filter(fn ($e) => $e->created_at && $e->created_at->lte($cursor->copy()->endOfDay()))->count();
                $real = max(0, $total - $doneByDay);
            }

            $ideal = max(0, $total - ($idealStep * $i));

            $series[] = [
                'date'  => $iso,
                'ideal' => round($ideal, 2),
                'real'  => $real !== null ? (int) $real : null,
            ];

            $cursor->addDay();
            $i++;
        }

        $remaining = $total - $done;
        $percentDone = $total > 0 ? (int) round(($done / $total) * 100) : 0;

        $cutoff = $today->copy()->subDays(7);
        $recentDones = $events->filter(fn ($e) => $e->created_at && $e->created_at->gte($cutoff))->count();
        $paceDay = $recentDones / 7;
        $forecastDays = $paceDay > 0 ? (int) ceil($remaining / $paceDay) : null;

        $cache[$key] = [
            'cycle' => [
                'id'             => $cycle->id,
                'key'            => $cycle->key,
                'name'           => $cycle->name,
                'goal'           => $cycle->goal,
                'start_date'     => $start->toDateString(),
                'end_date'       => $end->toDateString(),
                'status'         => $cycle->status,
                'days_remaining' => $cycle->status === 'active' ? $cycle->daysRemaining() : 0,
            ],
            'series' => $series,
            'kpis'   => [
                'total'         => $total,
                'done'          => $done,
                'remaining'     => $remaining,
                'percent_done'  => $percentDone,
                'pace_per_day'  => round($paceDay, 1),
                'forecast_days' => $forecastDays,
            ],
        ];

        return $cache[$key];
    }

    protected function resolveProject(Request $request): ?McpProject
    {
        $key = strtoupper((string) $request->get('project', config('projectmgmt.default_project_key', 'COPI')));
        if ($key === '') return null;
        return McpProject::where('key', $key)->first();
    }
}
