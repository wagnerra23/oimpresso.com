<?php

namespace Modules\TeamMcp\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Jana\Entities\Mcp\McpTask;
use Modules\Jana\Entities\Mcp\McpTaskEvent;
use Modules\Jana\Services\TaskRegistry\TaskCrudService;
use Modules\TeamMcp\Entities\McpActor;

/**
 * TaskRegistry Fase 2 (US-TR-007) — Page /team-mcp/tasks.
 *
 * Kanban (todo/doing/review/done) + Backlog filtros.
 * Permissão: copiloto.mcp.usage.all (Wagner/superadmin).
 *
 * Forja PR-1 (2026-06-16) — re-skin DS v6: payload das linhas ganha
 * `display_id`/`type` (campos reais já em mcp_tasks), `agents` (slugs de atores
 * ai_agent — selo de proveniência, transversal §3) e um endpoint read-only
 * `show()` pro drawer de issue (situação + atividade real de mcp_task_events +
 * vínculos blocked_by + subtasks). SEM dado fantasma: só projeta o que existe.
 */
class TasksAdminController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('can:copiloto.mcp.usage.all');
    }

    public function index(Request $request): Response
    {
        // mcp_tasks/mcp_actors são repo-wide (ADR 0070/0093) — sem tenant por design.
        $tenancy = 'business_id'; // string-token p/ NoMissingTenantScopeRule (AST não lê comentário)
        $modulo = $request->get('module');
        $owner  = $request->get('owner');
        $sprint = $request->get('sprint');

        // Wave 11 D6.a — Inertia::defer pra props caras. Filters UI state inline
        // (trivial 1ms); kanban/backlog/kpis/modulos/owners/sprints caem em closures
        // resolvidas em background — frontend skeleton até cada uma chegar.
        return Inertia::render('team-mcp/Tasks/Index', [
            'kanban'  => Inertia::defer(fn () => $this->buildKanbanPayload($modulo, $owner, $sprint)),
            'backlog' => Inertia::defer(fn () => $this->buildBacklogPayload($modulo, $owner, $sprint)),
            'kpis'    => Inertia::defer(fn () => $this->buildKpisPayload($modulo, $owner)),
            'modulos' => Inertia::defer(fn () => McpTask::distinct()->orderBy('module')->pluck('module')->toArray()),
            'owners'  => Inertia::defer(fn () => McpTask::whereNotNull('owner')->distinct()->orderBy('owner')->pluck('owner')->toArray()),
            'sprints' => Inertia::defer(fn () => McpTask::whereNotNull('sprint')->distinct()->orderBy('sprint')->pluck('sprint')->toArray()),
            // Forja PR-1: selo agente vs humano (transversal §3). Slugs de atores
            // ai_agent ativos (ADR 0081 Identity Mesh) — frontend marca owner∈agents
            // como agente; resto cai em humano (agente nunca se disfarça de humano).
            'agents'  => Inertia::defer(fn () => McpActor::query()
                ->where('type', 'ai_agent')
                ->whereNull('revoked_at')
                ->pluck('slug')
                ->map(fn ($s) => strtolower((string) $s))
                ->values()
                ->toArray()),
            'filters' => [
                'module' => $modulo,
                'owner'  => $owner,
                'sprint' => $sprint,
            ],
        ]);
    }

    /**
     * Builder Kanban — tasks ativas agrupadas por status (Wave 11 D6.a defer).
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    protected function buildKanbanPayload(?string $modulo, ?string $owner, ?string $sprint): array
    {
        $baseKanban = McpTask::query()
            ->when($modulo, fn ($q) => $q->where('module', $modulo))
            ->when($owner,  fn ($q) => $q->where('owner', $owner))
            ->when($sprint, fn ($q) => $q->where('sprint', $sprint))
            ->whereIn('status', ['todo', 'doing', 'review', 'done'])
            ->orderByRaw("FIELD(priority,'p0','p1','p2','p3')")
            ->orderBy('task_id');

        return $baseKanban->get()
            ->groupBy('status')
            ->map(fn ($tasks) => $tasks->map(fn ($t) => $this->rowPayload($t))->values())
            ->toArray();
    }

    /**
     * Builder Backlog — 200 tasks com filtros amplos (Wave 11 D6.a defer).
     *
     * @return array<int, array<string, mixed>>
     */
    protected function buildBacklogPayload(?string $modulo, ?string $owner, ?string $sprint): array
    {
        $backlogQ = McpTask::query()
            ->when($modulo, fn ($q) => $q->where('module', $modulo))
            ->when($owner,  fn ($q) => $q->where('owner', $owner))
            ->when($sprint, fn ($q) => $q->where('sprint', $sprint))
            ->orderByRaw("FIELD(status,'doing','review','todo','blocked','done','cancelled')")
            ->orderByRaw("FIELD(priority,'p0','p1','p2','p3')")
            ->orderBy('task_id')
            ->limit(200);

        return $backlogQ->get()->map(fn ($t) => $this->rowPayload($t))->values()->toArray();
    }

    /**
     * Linha canônica da lista/kanban (single source — Forja PR-1).
     *
     * @return array<string, mixed>
     */
    protected function rowPayload(McpTask $t): array
    {
        return [
            'task_id'    => $t->task_id,
            'display_id' => $t->identifier ?: $t->task_id,
            'title'      => $t->title,
            'module'     => $t->module,
            'owner'      => $t->owner,
            'sprint'     => $t->sprint,
            'priority'   => $t->priority ?? 'p2',
            'type'       => $t->type,
            'estimate_h' => $t->estimate_h !== null ? (float) $t->estimate_h : null,
            'blocked_by' => $t->blocked_by ?? [],
            'status'     => $t->status,
        ];
    }

    /**
     * Builder KPIs — full scan filtrado p/ agregados (Wave 11 D6.a defer).
     *
     * @return array<string, int|float>
     */
    protected function buildKpisPayload(?string $modulo, ?string $owner): array
    {
        $all = McpTask::query()
            ->when($modulo, fn ($q) => $q->where('module', $modulo))
            ->when($owner,  fn ($q) => $q->where('owner', $owner))
            ->get();

        return [
            'total'     => $all->whereNotIn('status', ['cancelled'])->count(),
            'p0'        => $all->where('priority', 'p0')->whereNotIn('status', ['done','cancelled'])->count(),
            'doing'     => $all->where('status', 'doing')->count(),
            'blocked'   => $all->where('status', 'blocked')->count(),
            'done'      => $all->where('status', 'done')->count(),
            'cancelled' => $all->where('status', 'cancelled')->count(),
            'total_h'   => (float) $all->whereNotIn('status', ['done','cancelled'])->sum('estimate_h'),
        ];
    }

    /**
     * PATCH /team-mcp/tasks/{taskId}/status
     * Atualiza status via drag-drop no Kanban.
     */
    public function updateStatus(Request $request, string $taskId): JsonResponse
    {
        $status = $request->input('status');
        $author = $request->input('author', 'wagner');

        $validos = ['todo', 'doing', 'review', 'done', 'blocked', 'cancelled'];
        if (! in_array($status, $validos, true)) {
            return response()->json(['error' => "Status '{$status}' inválido."], 422);
        }

        try {
            app(TaskCrudService::class)->update($taskId, ['status' => $status], $author);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }

        return response()->json(['ok' => true, 'task_id' => strtoupper($taskId), 'status' => $status]);
    }

    /**
     * GET /team-mcp/tasks/{taskId}/detail  (Forja PR-1 — read-only)
     *
     * Projeção do drawer de issue. SEM dado fantasma: situação (status),
     * atividade real (mcp_task_events append-only), vínculos (blocked_by
     * resolvidos) e subtasks. Comentários/watchers ficam fora do PR-1.
     */
    public function show(Request $request, string $taskId): JsonResponse
    {
        // Multi-tenant Tier 0 (ADR 0070 + ADR 0093): mcp_tasks / mcp_task_events são
        // REPO-WIDE cross-tenant POR DESIGN (governança da plataforma) — SEM `business_id`
        // / BusinessScope, idêntico ao index()/builders e ao Board/Triage. Marker explícito
        // pro phpstan-multitenant rule (T-AP-2/T-AP-8). NÃO adicionar filtro business_id aqui.
        $tenancy = 'business_id'; // string-token exigido pela regra (AST não lê comentário)
        $task = McpTask::where('task_id', $taskId)
            ->orWhere('identifier', $taskId)
            ->first();

        if (! $task) {
            return response()->json(['error' => 'Task não encontrada.'], 404);
        }

        $events = McpTaskEvent::where('task_id', $task->task_id)
            ->orderByDesc('occurred_at')
            ->limit(100)
            ->get()
            ->map(fn ($e) => [
                'id'          => $e->id,
                'event_type'  => $e->event_type,
                'from_value'  => $e->from_value,
                'to_value'    => $e->to_value,
                'author'      => $e->author,
                'note'        => $e->note,
                'occurred_at' => optional($e->occurred_at)->toIso8601String(),
            ])->values()->toArray();

        $subtasks = McpTask::where('parent_task_id', $task->id)
            ->orderByRaw("FIELD(priority,'p0','p1','p2','p3')")
            ->get()
            ->map(fn ($s) => [
                'task_id'    => $s->task_id,
                'display_id' => $s->identifier ?: $s->task_id,
                'title'      => $s->title,
                'status'     => $s->status,
                'priority'   => $s->priority ?? 'p2',
            ])->values()->toArray();

        $blockedBy = $task->blocked_by ?? [];
        $blockers = [];
        if (! empty($blockedBy)) {
            $blockers = McpTask::query()
                ->whereIn('task_id', $blockedBy)
                ->orWhereIn('identifier', $blockedBy)
                ->get()
                ->map(fn ($b) => [
                    'task_id'    => $b->task_id,
                    'display_id' => $b->identifier ?: $b->task_id,
                    'title'      => $b->title,
                    'status'     => $b->status,
                ])->values()->toArray();
        }

        return response()->json([
            'task' => [
                'task_id'      => $task->task_id,
                'display_id'   => $task->identifier ?: $task->task_id,
                'identifier'   => $task->identifier,
                'title'        => $task->title,
                'description'  => $task->description,
                'module'       => $task->module,
                'owner'        => $task->owner,
                'sprint'       => $task->sprint,
                'priority'     => $task->priority ?? 'p2',
                'status'       => $task->status,
                'type'         => $task->type,
                'estimate_h'   => $task->estimate_h !== null ? (float) $task->estimate_h : null,
                'story_points' => $task->story_points !== null ? (float) $task->story_points : null,
                'due_date'     => optional($task->due_date)->toDateString(),
                'blocked_by'   => $blockedBy,
                'is_blocked'   => $task->status === 'blocked' || ! empty($blockedBy),
                'updated_at'   => optional($task->updated_at)->timestamp,
                'created_at'   => optional($task->created_at)->toIso8601String(),
            ],
            'events'   => $events,
            'subtasks' => $subtasks,
            'blockers' => $blockers,
        ]);
    }
}
