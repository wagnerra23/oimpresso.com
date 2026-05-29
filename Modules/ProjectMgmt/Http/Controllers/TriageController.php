<?php

namespace Modules\ProjectMgmt\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Jana\Entities\Mcp\McpCycle;
use Modules\Jana\Entities\Mcp\McpEpic;
use Modules\Jana\Entities\Mcp\McpProject;
use Modules\Jana\Entities\Mcp\McpTask;
use Modules\Jana\Services\TaskRegistry\TaskCrudService;

/**
 * TriageController — /project-mgmt/triage (US-TR-301..303, SPEC-UI-FASE7 Onda 2).
 *
 * Superfície humana da tool MCP `triage`: lista as tasks órfãs (sem owner OU
 * sem priority OU status=backlog) e permite atribuir owner/prioridade/cycle/epic
 * inline sem abrir a task. A lista DEVE bater 1:1 com a tool `triage`
 * (mesmo filtro = `McpTask::triage()` scope, mesma exclusão de done/cancelled).
 *
 * Multi-tenant (ADR 0093 + ADR 0070): mcp_tasks é governança GLOBAL repo-wide
 * (sem business_id por design). O escopo aqui é por-projeto (resolveProject),
 * não por business — idêntico ao Board/Backlog/MyWork.
 *
 * Permissão: copiloto.mcp.usage.all (mesmo padrão do Board/MyWork).
 */
class TriageController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('can:copiloto.mcp.usage.all');
    }

    public function index(Request $request): Response
    {
        $project   = $this->resolveProject($request);
        $projectId = $project?->id;

        // RUNBOOK-inertia-defer-pattern.md (Wave 11 D6.a) — defer queries pesadas.
        // `project`/`filters` cheap eager. tasks/kpis compartilham a query órfã →
        // 1 closure memoizada. epics/cycles/owners alimentam os dropdowns de
        // atribuição inline → closures separadas pra partial reload.
        return Inertia::render('ProjectMgmt/Triage/Index', [
            'project' => $project ? ['id' => $project->id, 'key' => $project->key, 'name' => $project->name] : null,
            'tasks'   => Inertia::defer(fn () => $this->buildTriagePayload($projectId)['tasks']),
            'kpis'    => Inertia::defer(fn () => $this->buildTriagePayload($projectId)['kpis']),
            'cycles'  => Inertia::defer(fn () => $this->buildCyclesPayload($projectId)),
            'epics'   => Inertia::defer(fn () => $this->buildEpicsPayload($projectId)),
            'owners'  => Inertia::defer(fn () => $this->buildOwnersPayload($projectId)),
            'filters' => ['project' => $project?->key],
        ]);
    }

    /**
     * Constrói tasks órfãs + kpis (compartilham a mesma query). Memoiza por
     * projectId pra não dobrar a query quando ambos requested numa render.
     *
     * Filtro = paridade EXATA com Modules/Jana/Mcp/Tools/TriageTool.php:
     *   McpTask::triage() = (owner IS NULL OR priority IS NULL OR status='backlog')
     *   + whereNotIn status [done, cancelled]
     *   + orderByDesc created_at.
     *
     * @return array{tasks: \Illuminate\Support\Collection, kpis: array<string,int>}
     */
    protected function buildTriagePayload(?int $projectId): array
    {
        static $cache = [];
        $key = (string) ($projectId ?? 'all');
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $tasks = McpTask::triage()
            ->whereNotIn('status', ['done', 'cancelled'])
            ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
            ->orderByDesc('created_at')
            ->limit(200)
            ->get()
            ->map(fn (McpTask $t) => $this->serializeTask($t));

        $kpis = [
            'total'      => $tasks->count(),
            'sem_owner'  => $tasks->where('needs_owner', true)->count(),
            'sem_prio'   => $tasks->where('needs_prio', true)->count(),
            'backlog'    => $tasks->where('is_backlog', true)->count(),
        ];

        $cache[$key] = ['tasks' => $tasks, 'kpis' => $kpis];
        return $cache[$key];
    }

    /** @return array<int,array<string,mixed>> */
    protected function buildCyclesPayload(?int $projectId): array
    {
        if (! $projectId) {
            return [];
        }
        return McpCycle::where('project_id', $projectId)
            ->whereIn('status', ['planning', 'active'])
            ->orderBy('start_date', 'desc')
            ->get()
            ->map(fn (McpCycle $c) => [
                'id'        => $c->id,
                'key'       => $c->key,
                'name'      => $c->name,
                'status'    => $c->status,
                'is_active' => $c->status === 'active',
            ])
            ->all();
    }

    /** @return array<int,array{id:int,key:string,title:string}> */
    protected function buildEpicsPayload(?int $projectId): array
    {
        if (! $projectId) {
            return [];
        }
        return McpEpic::where('project_id', $projectId)
            ->whereIn('status', ['planning', 'active'])
            ->orderBy('sort_order')
            ->orderBy('key')
            ->get()
            ->map(fn (McpEpic $e) => ['id' => $e->id, 'key' => $e->key, 'title' => $e->title])
            ->all();
    }

    /** @return array<int,string> — owners já existentes (autocomplete de atribuição). */
    protected function buildOwnersPayload(?int $projectId): array
    {
        return McpTask::query()
            ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
            ->whereNotNull('owner')
            ->distinct()
            ->orderBy('owner')
            ->pluck('owner')
            ->all();
    }

    /**
     * PATCH /project-mgmt/triage/{taskId}/assign — US-TR-302/303.
     *
     * Atribuição inline de owner + prioridade (+ cycle/epic opcional) sem abrir
     * a task. Reusa TaskCrudService::update() — MESMA via que a tool MCP
     * `tasks-update` — então gera mcp_task_events (assigned/field_updated) e
     * dispara McpInboxNotification pro novo owner (paridade UI ↔ tool).
     *
     * Aceita só o subset relevante de Triage; valida priority/cycle/epic.
     */
    public function assign(Request $request, string $taskId): JsonResponse
    {
        $input = $request->only(['owner', 'priority', 'cycle_id', 'epic_id']);

        $fields = [];

        // owner — string vazia limpa (TaskCrudService converte '' → null)
        if ($request->has('owner')) {
            $owner = trim((string) ($input['owner'] ?? ''));
            $fields['owner'] = $owner;
        }

        // priority — precisa ser canônica
        if ($request->has('priority')) {
            $priority = (string) ($input['priority'] ?? '');
            if ($priority !== '' && ! in_array($priority, McpTask::PRIORITIES, true)) {
                return response()->json(['error' => "Priority '{$priority}' inválida."], 422);
            }
            $fields['priority'] = $priority;
        }

        // cycle_id — null limpa, senão precisa existir
        if ($request->has('cycle_id')) {
            $cycleId = $input['cycle_id'];
            if ($cycleId === null || $cycleId === '' || $cycleId === 0 || $cycleId === '0') {
                $fields['cycle_id'] = null;
            } else {
                if (! McpCycle::whereKey((int) $cycleId)->exists()) {
                    return response()->json(['error' => "Cycle '{$cycleId}' não encontrado."], 422);
                }
                $fields['cycle_id'] = (int) $cycleId;
            }
        }

        // epic_id — null limpa, senão precisa existir
        if ($request->has('epic_id')) {
            $epicId = $input['epic_id'];
            if ($epicId === null || $epicId === '' || $epicId === 0 || $epicId === '0') {
                $fields['epic_id'] = null;
            } else {
                if (! McpEpic::whereKey((int) $epicId)->exists()) {
                    return response()->json(['error' => "Epic '{$epicId}' não encontrado."], 422);
                }
                $fields['epic_id'] = (int) $epicId;
            }
        }

        if (empty($fields)) {
            return response()->json(['error' => 'Nada pra atribuir: informe owner, priority, cycle_id ou epic_id.'], 422);
        }

        $author = $this->resolveAuthor($request);

        try {
            $result = app(TaskCrudService::class)->update($taskId, $fields, $author);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }

        $task = $result['task'];

        // Recalcula se ainda é órfã (some da lista no front se deixou de ser).
        $stillTriage = $task->owner === null
            || $task->priority === null
            || $task->status === 'backlog';

        return response()->json([
            'ok'           => true,
            'task'         => $this->serializeTask($task),
            'still_triage' => $stillTriage,
        ]);
    }

    // ---------- helpers ----------

    protected function resolveProject(Request $request): ?McpProject
    {
        $key = strtoupper((string) $request->get('project', config('projectmgmt.default_project_key', 'COPI')));
        if ($key === '') {
            return null;
        }
        return McpProject::where('key', $key)->first();
    }

    protected function resolveAuthor(Request $request): string
    {
        $explicit = trim((string) $request->input('author', ''));
        if ($explicit !== '') {
            return $explicit;
        }
        $user = $request->user();
        if ($user) {
            return strtolower($user->username ?? $user->first_name ?? 'system');
        }
        return 'system';
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
            // priority_raw preserva NULL (pra UI mostrar "sem prio"); priority
            // mantém fallback p2 pro badge não quebrar (igual Board/Backlog).
            'priority_raw' => $t->priority,
            'priority'     => $t->priority ?? 'p2',
            'status'       => $t->status,
            'type'         => $t->type,
            'epic_id'      => $t->epic_id,
            'cycle_id'     => $t->cycle_id,
            'due_date'     => optional($t->due_date)->toDateString(),
            'created_at'   => optional($t->created_at)->toIso8601String(),
            // Motivos pelos quais caiu na triage (chips na UI).
            'needs_owner'  => $t->owner === null,
            'needs_prio'   => $t->priority === null,
            'is_backlog'   => $t->status === 'backlog',
        ];
    }
}
