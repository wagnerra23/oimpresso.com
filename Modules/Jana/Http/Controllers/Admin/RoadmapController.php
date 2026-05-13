<?php

namespace Modules\Jana\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Onda 5 V1 — Roadmap timeline UI (SVAR React Gantt MIT).
 *
 * Render cronológico das tasks de cycle ativo + sub-issues hierarchy +
 * dependency graph (blocked_by[]). Substitui visão markdown de
 * `tasks-list cycle:current`.
 *
 * Permissão: jana.mcp.tasks.read (existente, lê task board canon).
 * Scope:     `mcp_tasks` é cache cross-business (canon = git via SPEC.md).
 *            Filtros por module/cycle/owner. Não exige `business_id` global
 *            scope porque tabela já é canon governado (ADR 0093 §exceções).
 *
 * Ver:
 *  - memory/requisitos/Jana/ONDA-5-DOSSIER-2026-05-13.md §V1
 *  - ADR 0070 Jira-style task management
 *  - ADR 0093 Multi-tenant Tier 0
 *  - ADR 0110 Cockpit V2
 */
class RoadmapController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        // Reusa permission canônica do task board MCP.
        // Wagner pode promover pra jana.admin.roadmap.view dedicada depois.
        $this->middleware('can:jana.mcp.tasks.read');
    }

    public function index(Request $request): Response
    {
        // Filtros via query params (preserve-state ao trocar).
        $cycleFilter    = $request->get('cycle');     // 'current' | <id> | null
        $ownerFilter    = $request->get('owner');     // username | null
        $priorityFilter = $request->get('priority');  // 'p0' | 'p1' | 'p2' | 'p3' | null
        $moduleFilter   = $request->get('module');    // 'Jana' | 'Repair' | ... | null

        // 1) Cycles disponíveis (active + planning + closed mais recentes pro filtro).
        $cycles = DB::table('mcp_cycles')
            ->select(['id', 'project_id', 'key', 'name', 'start_date', 'end_date', 'status', 'goal'])
            ->whereNull('deleted_at')
            ->orderByRaw("FIELD(status, 'active', 'planning', 'closed')")
            ->orderByDesc('start_date')
            ->limit(20)
            ->get();

        // 2) Resolve cycle ativo padrão se filtro = 'current' (ou null).
        $activeCycle = $cycles->firstWhere('status', 'active');
        $selectedCycleId = null;

        if ($cycleFilter === 'current' || $cycleFilter === null) {
            $selectedCycleId = $activeCycle?->id;
        } elseif (is_numeric($cycleFilter)) {
            $selectedCycleId = (int) $cycleFilter;
        }

        // 3) Query tasks com filtros aplicados.
        $tasksQuery = DB::table('mcp_tasks')
            ->select([
                'id',
                'task_id',
                'identifier',
                'module',
                'title',
                'description',
                'status',
                'owner',
                'priority',
                'estimate_h',
                'story_points',
                'blocked_by',
                'parent_task_id',
                'cycle_id',
                'project_id',
                'type',
                'due_date',
                'started_at',
                'completed_at',
                'created_at',
                'updated_at',
            ])
            ->whereNotIn('status', ['cancelled']);

        if ($selectedCycleId !== null) {
            $tasksQuery->where('cycle_id', $selectedCycleId);
        }

        if ($ownerFilter) {
            $tasksQuery->where('owner', $ownerFilter);
        }

        if ($priorityFilter && in_array($priorityFilter, ['p0', 'p1', 'p2', 'p3'], true)) {
            $tasksQuery->where('priority', $priorityFilter);
        }

        if ($moduleFilter) {
            $tasksQuery->where('module', $moduleFilter);
        }

        $tasks = $tasksQuery->orderBy('module')
            ->orderByRaw("FIELD(priority, 'p0', 'p1', 'p2', 'p3')")
            ->orderBy('due_date')
            ->limit(500)
            ->get();

        // 4) Owners distintos pro filtro dropdown.
        $owners = DB::table('mcp_tasks')
            ->select('owner')
            ->whereNotNull('owner')
            ->distinct()
            ->orderBy('owner')
            ->pluck('owner');

        // 5) Modules distintos pro filtro dropdown.
        $modules = DB::table('mcp_tasks')
            ->select('module')
            ->whereNotNull('module')
            ->distinct()
            ->orderBy('module')
            ->pluck('module');

        return Inertia::render('Jana/Admin/Roadmap', [
            'cycles' => $cycles->map(function ($c) {
                return [
                    'id'         => (int) $c->id,
                    'key'        => $c->key,
                    'name'       => $c->name,
                    'status'     => $c->status,
                    'start_date' => $c->start_date,
                    'end_date'   => $c->end_date,
                    'goal'       => $c->goal,
                ];
            })->values(),
            'tasks' => $tasks->map(function ($t) {
                return [
                    'id'             => (int) $t->id,
                    'task_id'        => $t->task_id,
                    'identifier'     => $t->identifier,
                    'module'         => $t->module,
                    'title'          => $t->title,
                    'description'    => $t->description,
                    'status'         => $t->status,
                    'owner'          => $t->owner,
                    'priority'       => $t->priority,
                    'type'           => $t->type,
                    'estimate_h'     => $t->estimate_h !== null ? (float) $t->estimate_h : null,
                    'story_points'   => $t->story_points !== null ? (float) $t->story_points : null,
                    'parent_task_id' => $t->parent_task_id !== null ? (int) $t->parent_task_id : null,
                    'cycle_id'       => $t->cycle_id !== null ? (int) $t->cycle_id : null,
                    'project_id'     => $t->project_id !== null ? (int) $t->project_id : null,
                    'blocked_by'     => $this->decodeJsonArray($t->blocked_by),
                    'due_date'       => $t->due_date,
                    'started_at'     => $t->started_at,
                    'completed_at'   => $t->completed_at,
                    'created_at'     => $t->created_at,
                    'updated_at'     => $t->updated_at,
                ];
            })->values(),
            'filters' => [
                'cycle'    => $selectedCycleId,
                'owner'    => $ownerFilter,
                'priority' => $priorityFilter,
                'module'   => $moduleFilter,
            ],
            'owners'  => $owners,
            'modules' => $modules,
            'active_cycle_id' => $activeCycle?->id,
        ]);
    }

    /**
     * Decodifica coluna JSON `blocked_by` com segurança (vem como string do PDO).
     */
    protected function decodeJsonArray(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
