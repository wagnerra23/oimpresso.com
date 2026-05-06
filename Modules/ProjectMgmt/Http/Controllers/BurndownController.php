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

        $cycleId = (int) $request->get('cycle', 0) ?: null;
        $cycle = $cycleId
            ? McpCycle::find($cycleId)
            : ($project ? $project->activeCycle() : null);

        $payload = [
            'project' => $project ? ['id' => $project->id, 'key' => $project->key, 'name' => $project->name] : null,
            'cycle'   => null,
            'series'  => [],
            'cycles'  => $project
                ? McpCycle::where('project_id', $project->id)
                    ->whereIn('status', ['planning', 'active', 'closed'])
                    ->orderBy('start_date', 'desc')
                    ->limit(10)
                    ->get()
                    ->map(fn ($c) => [
                        'id' => $c->id, 'key' => $c->key, 'name' => $c->name,
                        'status' => $c->status, 'is_active' => $c->status === 'active',
                    ])->all()
                : [],
            'kpis' => [
                'total' => 0, 'done' => 0, 'remaining' => 0, 'percent_done' => 0,
                'pace_per_day' => null, 'forecast_days' => null,
            ],
        ];

        if (! $cycle) {
            return Inertia::render('ProjectMgmt/Burndown/Index', $payload);
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

        $payload['cycle'] = [
            'id'             => $cycle->id,
            'key'            => $cycle->key,
            'name'           => $cycle->name,
            'goal'           => $cycle->goal,
            'start_date'     => $start->toDateString(),
            'end_date'       => $end->toDateString(),
            'status'         => $cycle->status,
            'days_remaining' => $cycle->status === 'active' ? $cycle->daysRemaining() : 0,
        ];
        $payload['series'] = $series;
        $payload['kpis'] = [
            'total'         => $total,
            'done'          => $done,
            'remaining'     => $remaining,
            'percent_done'  => $percentDone,
            'pace_per_day'  => round($paceDay, 1),
            'forecast_days' => $forecastDays,
        ];

        return Inertia::render('ProjectMgmt/Burndown/Index', $payload);
    }

    protected function resolveProject(Request $request): ?McpProject
    {
        $key = strtoupper((string) $request->get('project', config('projectmgmt.default_project_key', 'COPI')));
        if ($key === '') return null;
        return McpProject::where('key', $key)->first();
    }
}
