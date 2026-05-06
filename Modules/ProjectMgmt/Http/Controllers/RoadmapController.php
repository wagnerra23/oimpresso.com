<?php

namespace Modules\ProjectMgmt\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Jana\Entities\Mcp\McpEpic;
use Modules\Jana\Entities\Mcp\McpProject;
use Modules\Jana\Entities\Mcp\McpTask;

/**
 * RoadmapController — /project-mgmt/roadmap (US-TR-203).
 *
 * Visão por quarter: epics agrupados por target_quarter. Cada epic mostra
 * progresso (done/total), owner, target_quarter. Sem epic = "Sem quarter".
 *
 * Permissão: copiloto.mcp.usage.all.
 */
class RoadmapController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('can:copiloto.mcp.usage.all');
    }

    public function index(Request $request): Response
    {
        $project = $this->resolveProject($request);

        $epics = $project
            ? McpEpic::where('project_id', $project->id)
                ->whereIn('status', ['planning', 'active', 'done'])
                ->orderBy('target_quarter')
                ->orderBy('sort_order')
                ->orderBy('key')
                ->get()
            : collect();

        $epicIds = $epics->pluck('id')->all();
        $taskCounts = McpTask::query()
            ->whereIn('epic_id', $epicIds)
            ->selectRaw('epic_id, status, COUNT(*) as n')
            ->groupBy('epic_id', 'status')
            ->get()
            ->groupBy('epic_id')
            ->map(function ($rows) {
                $byStatus = $rows->pluck('n', 'status')->toArray();
                $total = array_sum($byStatus);
                $done = (int) ($byStatus['done'] ?? 0);
                $active = $total - $done - (int) ($byStatus['cancelled'] ?? 0);
                return [
                    'total'   => $total,
                    'done'    => $done,
                    'active'  => $active,
                    'percent' => $total > 0 ? (int) round(($done / $total) * 100) : 0,
                ];
            });

        $byQuarter = [];
        foreach ($epics as $e) {
            $q = $e->target_quarter ?: 'Sem quarter';
            $counts = $taskCounts->get($e->id) ?? ['total' => 0, 'done' => 0, 'active' => 0, 'percent' => 0];
            $byQuarter[$q] ??= ['key' => $q, 'epics' => []];
            $byQuarter[$q]['epics'][] = [
                'id'          => $e->id,
                'key'         => $e->key,
                'title'       => $e->title,
                'description' => $e->description,
                'owner'       => $e->owner,
                'status'      => $e->status,
                'color'       => $e->color,
                'tasks'       => $counts,
            ];
        }

        ksort($byQuarter);
        if (isset($byQuarter['Sem quarter'])) {
            $sem = $byQuarter['Sem quarter'];
            unset($byQuarter['Sem quarter']);
            $byQuarter['Sem quarter'] = $sem;
        }

        return Inertia::render('ProjectMgmt/Roadmap/Index', [
            'project'  => $project ? ['id' => $project->id, 'key' => $project->key, 'name' => $project->name] : null,
            'quarters' => array_values($byQuarter),
            'kpis'     => [
                'total_epics'    => $epics->count(),
                'active_epics'   => $epics->where('status', 'active')->count(),
                'planning_epics' => $epics->where('status', 'planning')->count(),
                'done_epics'     => $epics->where('status', 'done')->count(),
            ],
        ]);
    }

    protected function resolveProject(Request $request): ?McpProject
    {
        $key = strtoupper((string) $request->get('project', config('projectmgmt.default_project_key', 'COPI')));
        if ($key === '') return null;
        return McpProject::where('key', $key)->first();
    }
}
