<?php

namespace Modules\ProjectMgmt\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Jana\Entities\Mcp\McpEpic;
use Modules\Jana\Entities\Mcp\McpProject;
use Modules\Jana\Entities\Mcp\McpTask;
use Modules\Jana\Services\TaskRegistry\TaskCrudService;

/**
 * BacklogController — /project-mgmt/backlog (US-TR-202).
 *
 * Lista densa com filtros + bulk edit. Diferente do Board: mostra TODOS
 * status (incluindo done/cancelled quando 'all') e suporta seleção múltipla
 * com ação em lote (status/priority/owner/sprint).
 *
 * Permissão: copiloto.mcp.usage.all.
 */
class BacklogController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('can:copiloto.mcp.usage.all');
    }

    public function index(Request $request): Response
    {
        $project = $this->resolveProject($request);

        $filters = [
            'status'   => $request->get('status'),
            'priority' => $request->get('priority'),
            'owner'    => $request->get('owner'),
            'epic'     => (int) $request->get('epic', 0) ?: null,
            'cycle'    => (int) $request->get('cycle', 0) ?: null,
            'sprint'   => $request->get('sprint'),
            'q'        => trim((string) $request->get('q', '')),
            'sort'     => $request->get('sort', 'priority'),
        ];
        $projectId = $project?->id;

        // RUNBOOK-inertia-defer-pattern.md (Wave 11 D6.a) — defer queries pesadas.
        // `filters` (UI state) + `project` (cheap) ficam eager.
        // tasks/kpis compartilham mesma query — agrupados em 1 closure.
        // epics/owners/sprints são queries independentes — closures separadas
        // permitem partial reload (ex `only:['tasks','kpis']` ao filtrar).
        return Inertia::render('ProjectMgmt/Backlog/Index', [
            'project' => $project ? ['id' => $project->id, 'key' => $project->key, 'name' => $project->name] : null,
            'tasks'   => Inertia::defer(fn () => $this->buildTasksAndKpis($projectId, $filters)['tasks']),
            'kpis'    => Inertia::defer(fn () => $this->buildTasksAndKpis($projectId, $filters)['kpis']),
            'epics'   => Inertia::defer(fn () => $this->buildEpicsPayload($projectId)),
            'owners'  => Inertia::defer(fn () => $this->buildOwnersPayload($projectId)),
            'sprints' => Inertia::defer(fn () => $this->buildSprintsPayload($projectId)),
            'filters' => $filters,
        ]);
    }

    /**
     * Constrói tasks + kpis (compartilham mesma query). Memoiza por (projectId, filters)
     * pra evitar dobrar query quando ambos requested numa render.
     *
     * @param array<string,mixed> $filters
     * @return array{tasks: \Illuminate\Support\Collection, kpis: array<string,int>}
     */
    protected function buildTasksAndKpis(?int $projectId, array $filters): array
    {
        static $cache = [];
        $cacheKey = md5(serialize([$projectId, $filters]));
        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        $q = McpTask::query()
            ->when($projectId, fn ($qq) => $qq->where('project_id', $projectId))
            ->when($filters['priority'], fn ($qq, $p) => $qq->where('priority', $p))
            ->when($filters['owner'], fn ($qq, $o) => $qq->where('owner', $o))
            ->when($filters['epic'], fn ($qq, $e) => $qq->where('epic_id', $e))
            ->when($filters['cycle'], fn ($qq, $c) => $qq->where('cycle_id', $c))
            ->when($filters['sprint'], fn ($qq, $s) => $qq->where('sprint', $s));

        $statusFilter = $filters['status'];
        if ($statusFilter && $statusFilter !== 'all') {
            $q->where('status', $statusFilter);
        } elseif ($statusFilter !== 'all') {
            $q->whereNotIn('status', ['cancelled']);
        }

        $search = $filters['q'];
        if ($search !== '') {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $search) . '%';
            $q->where(function ($qq) use ($like) {
                $qq->where('title', 'like', $like)
                   ->orWhere('task_id', 'like', $like)
                   ->orWhere('identifier', 'like', $like)
                   ->orWhere('owner', 'like', $like)
                   ->orWhere('module', 'like', $like);
            });
        }

        match ($filters['sort']) {
            'recent' => $q->orderBy('updated_at', 'desc'),
            'due'    => $q->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END')
                          ->orderBy('due_date', 'asc'),
            'title'  => $q->orderBy('title'),
            'id'     => $q->orderBy('task_id'),
            default  => $q
                ->orderByRaw("FIELD(status,'doing','review','todo','blocked','backlog','done','cancelled')")
                ->orderByRaw("FIELD(priority,'p0','p1','p2','p3','')")
                ->orderBy('task_id'),
        };

        $tasks = $q->limit(500)->get()->map(fn (McpTask $t) => $this->serializeTask($t));

        $kpis = [
            'total'   => $tasks->count(),
            'active'  => $tasks->whereNotIn('status', ['done', 'cancelled'])->count(),
            'p0'      => $tasks->where('priority', 'p0')->whereNotIn('status', ['done', 'cancelled'])->count(),
            'overdue' => $tasks->where('is_overdue', true)->count(),
            'unowned' => $tasks->whereNull('owner')->whereNotIn('status', ['done', 'cancelled'])->count(),
        ];

        $cache[$cacheKey] = ['tasks' => $tasks, 'kpis' => $kpis];
        return $cache[$cacheKey];
    }

    /** @return array<int,array{id:int,key:string,title:string}> */
    protected function buildEpicsPayload(?int $projectId): array
    {
        if (! $projectId) {
            return [];
        }
        return McpEpic::where('project_id', $projectId)
            ->orderBy('sort_order')->orderBy('key')->get()
            ->map(fn ($e) => ['id' => $e->id, 'key' => $e->key, 'title' => $e->title])->all();
    }

    /** @return array<int,string> */
    protected function buildOwnersPayload(?int $projectId): array
    {
        return McpTask::when($projectId, fn ($qq) => $qq->where('project_id', $projectId))
            ->whereNotNull('owner')->distinct()->orderBy('owner')->pluck('owner')->all();
    }

    /** @return array<int,string> */
    protected function buildSprintsPayload(?int $projectId): array
    {
        return McpTask::when($projectId, fn ($qq) => $qq->where('project_id', $projectId))
            ->whereNotNull('sprint')->distinct()->orderBy('sprint')->pluck('sprint')->all();
    }

    public function bulk(Request $request): JsonResponse
    {
        $taskIds = (array) $request->input('task_ids', []);
        $fields  = (array) $request->input('fields', []);

        $allowed = ['status', 'priority', 'owner', 'sprint'];
        $fields  = array_intersect_key($fields, array_flip($allowed));

        if (empty($taskIds) || empty($fields)) {
            return response()->json(['error' => 'task_ids e fields são obrigatórios'], 422);
        }

        if (isset($fields['status']) && ! in_array($fields['status'], McpTask::STATUSES, true)) {
            return response()->json(['error' => "Status '{$fields['status']}' inválido"], 422);
        }
        if (isset($fields['priority']) && ! in_array($fields['priority'], McpTask::PRIORITIES, true)) {
            return response()->json(['error' => "Priority '{$fields['priority']}' inválido"], 422);
        }

        $author = $this->resolveAuthor($request);
        $result = app(TaskCrudService::class)->bulkUpdate($taskIds, $fields, $author);

        return response()->json([
            'ok'         => true,
            'updated'    => $result['updated'],
            'errors'     => $result['errors'],
            'bulk_op_id' => $result['bulk_op_id'],
        ]);
    }

    protected function resolveProject(Request $request): ?McpProject
    {
        $key = strtoupper((string) $request->get('project', config('projectmgmt.default_project_key', 'COPI')));
        if ($key === '') return null;
        return McpProject::where('key', $key)->first();
    }

    protected function resolveAuthor(Request $request): string
    {
        $explicit = trim((string) $request->input('author', ''));
        if ($explicit !== '') return $explicit;
        $u = $request->user();
        return $u ? strtolower($u->username ?? $u->first_name ?? 'system') : 'system';
    }

    /** @return array<string,mixed> */
    protected function serializeTask(McpTask $t): array
    {
        return [
            'task_id'      => $t->task_id,
            'identifier'   => $t->identifier,
            'display_id'   => $t->getDisplayIdAttribute(),
            'title'        => $t->title,
            'module'       => $t->module,
            'owner'        => $t->owner,
            'sprint'       => $t->sprint,
            'priority'     => $t->priority ?? 'p2',
            'status'       => $t->status,
            'type'         => $t->type,
            'estimate_h'   => $t->estimate_h !== null ? (float) $t->estimate_h : null,
            'story_points' => $t->story_points !== null ? (float) $t->story_points : null,
            'due_date'     => optional($t->due_date)->toDateString(),
            'epic_id'      => $t->epic_id,
            'cycle_id'     => $t->cycle_id,
            'component_id' => $t->component_id,
            'blocked_by'   => $t->blocked_by ?? [],
            'is_blocked'   => $t->status === 'blocked',
            'is_overdue'   => $t->due_date && $t->due_date->isPast() && ! in_array($t->status, ['done', 'cancelled'], true),
        ];
    }
}
