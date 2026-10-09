<?php

namespace Modules\Forja\Http\Controllers;

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
 * Epic `cancelled` não entra (D8 — [CC] 2026-10-07, pergunta pulada pelo [W] — UC-RQV-04).
 *
 * Permissão: jana.mcp.usage.all.
 */
class RoadmapController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('can:jana.mcp.usage.all');
    }

    public function index(Request $request): Response
    {
        $project = $this->resolveProject($request);
        $projectId = $project?->id;

        // RUNBOOK-inertia-defer-pattern.md (Wave 11 D6.a) — defer queries pesadas.
        // `project` é cheap (resolveProject já consultou); demais props deferidas
        // pra desbloquear initial render. Quarters+kpis compartilham mesma query
        // de epics — agrupados em 1 closure pra evitar duplicar.
        return Inertia::render('Forja/Roadmap/Index', [
            'project'  => $project ? ['id' => $project->id, 'key' => $project->key, 'name' => $project->name] : null,
            'quarters' => Inertia::defer(fn () => $this->buildRoadmapPayload($projectId)['quarters']),
            'kpis'     => Inertia::defer(fn () => $this->buildRoadmapPayload($projectId)['kpis']),
        ]);
    }

    /**
     * Constrói quarters (epics agrupados) + kpis a partir de epics + task_counts.
     * Chamado por 2 closures defer — caching via memoization simples na request.
     *
     * @return array{quarters: array<int,array<string,mixed>>, kpis: array<string,int>}
     */
    protected function buildRoadmapPayload(?int $projectId): array
    {
        static $cache = [];
        $key = (string) ($projectId ?? 'none');
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $epics = $projectId
            ? McpEpic::where('project_id', $projectId)
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

        // Ordem CRONOLÓGICA (D9, [W] 2026-10-07 — UC-RQV-05). `ksort` ordenava como texto e
        // punha `Q1-2027` antes de `Q2-2026`. Aceita os dois formatos que existem no dado
        // (`Q2-2026` do schema e `2026-Q2` do `epics-update`); o que não parsear vem depois,
        // em ordem de texto, e "Sem quarter" fica sempre por último.
        uksort($byQuarter, fn (string $a, string $b): int => $this->chaveCronologica($a) <=> $this->chaveCronologica($b));

        $cache[$key] = [
            'quarters' => array_values($byQuarter),
            'kpis'     => [
                'total_epics'    => $epics->count(),
                'active_epics'   => $epics->where('status', 'active')->count(),
                'planning_epics' => $epics->where('status', 'planning')->count(),
                'done_epics'     => $epics->where('status', 'done')->count(),
            ],
        ];

        return $cache[$key];
    }

    /**
     * Chave de ordenação de um quarter: [grupo, ano, trimestre, texto].
     * grupo 0 = quarter parseado · 1 = texto não parseável · 2 = "Sem quarter".
     *
     * @return array{0:int,1:int,2:int,3:string}
     */
    protected function chaveCronologica(string $quarter): array
    {
        if ($quarter === 'Sem quarter') {
            return [2, 0, 0, ''];
        }
        if (preg_match('/^Q([1-4])-(\d{4})$/i', $quarter, $m)) {
            return [0, (int) $m[2], (int) $m[1], $quarter];
        }
        if (preg_match('/^(\d{4})-Q([1-4])$/i', $quarter, $m)) {
            return [0, (int) $m[1], (int) $m[2], $quarter];
        }

        return [1, 0, 0, $quarter];
    }

    protected function resolveProject(Request $request): ?McpProject
    {
        $key = strtoupper((string) $request->get('project', config('projectmgmt.default_project_key', 'COPI')));
        if ($key === '') return null;
        return McpProject::where('key', $key)->first();
    }
}
