<?php

namespace Modules\ProjectMgmt\Http\Controllers;

use App\Http\Controllers\Controller;
use App\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Jana\Entities\Mcp\McpCycle;
use Modules\Jana\Entities\Mcp\McpEpic;
use Modules\Jana\Entities\Mcp\McpProject;
use Modules\Jana\Entities\Mcp\McpTask;
use Modules\Jana\Entities\Mcp\McpTaskComment;
use Modules\Jana\Entities\Mcp\McpTaskDependency;
use Modules\Jana\Entities\Mcp\McpTaskEvent;
use Modules\Jana\Services\TaskRegistry\TaskCrudService;

/**
 * BoardController — Kanban /project-mgmt/board (US-TR-201).
 *
 * Promovido do Modules/TeamMcp em 2026-05-04 (Wagner pediu módulo próprio
 * pra Project Mgmt; backlog tem 11 tasks de UI Web Fase 7 do ADR 0070).
 *
 * Difere do TasksAdminController velho:
 *   - Filtra por Project (default = config('projectmgmt.default_project_key'))
 *   - Carrega Cycle ativo + goals + dias restantes pro header
 *   - Filtros: cycle, epic, owner, component (não só module/sprint)
 *   - Inclui coluna `backlog` (ADR 0070 adicionou esse status)
 *   - Inclui `identifier` Linear-style + `due_date` no card
 *
 * Permissão: copiloto.mcp.usage.all (mesmo padrão TeamMcp).
 */
class BoardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('can:copiloto.mcp.usage.all');
    }

    public function index(Request $request): Response
    {
        $project = $this->resolveProject($request);

        $cycleId    = (int) $request->get('cycle', 0) ?: null;
        $epicId     = (int) $request->get('epic', 0) ?: null;
        $componente = (int) $request->get('component', 0) ?: null;
        $owner      = $request->get('owner');

        // Cycle ativo do projeto (default se nada filtrado)
        $cycleAtivo = $project ? $project->activeCycle() : null;

        // Cycle "em foco" no board: o filtro explícito > cycle ativo > nada
        $cycleFoco = $cycleId
            ? McpCycle::find($cycleId)
            : $cycleAtivo;

        $colunas = config('projectmgmt.kanban_columns', ['backlog', 'todo', 'doing', 'review', 'done']);

        // Query base — tasks NÃO canceladas e dentro das colunas visíveis (+ blocked como overlay)
        $statusVisiveis = array_merge($colunas, ['blocked']);

        $baseQ = McpTask::query()
            ->when($project, fn ($q) => $q->where('project_id', $project->id))
            ->when($cycleFoco, fn ($q) => $q->where('cycle_id', $cycleFoco->id))
            ->when($epicId, fn ($q) => $q->where('epic_id', $epicId))
            ->when($componente, fn ($q) => $q->where('component_id', $componente))
            ->when($owner, fn ($q) => $q->where('owner', $owner))
            ->whereIn('status', $statusVisiveis)
            ->orderByRaw("FIELD(priority,'p0','p1','p2','p3','')")
            ->orderBy('due_date')
            ->orderBy('task_id');

        $tasks = $baseQ->get()->map(fn (McpTask $t) => $this->serializeTask($t));

        // Agrupa por status (blocked entra na coluna onde estava antes — mas como
        // mcp_tasks não guarda "previous status", deixa em todo + flag is_blocked)
        $kanban = [];
        foreach ($colunas as $col) {
            $kanban[$col] = $tasks->where('status', $col)->values()->all();
        }
        // Bloqueadas: jogamos em `todo` por default e marcamos. Se Wagner achar
        // ruim no uso, abrimos coluna própria via config.
        $bloqueadas = $tasks->where('status', 'blocked')->map(function ($t) {
            $t['is_blocked'] = true;
            return $t;
        })->values()->all();
        $kanban['todo'] = array_merge($bloqueadas, $kanban['todo'] ?? []);

        // Filtros disponíveis (apenas do projeto em foco)
        $epics = $project
            ? McpEpic::where('project_id', $project->id)
                ->whereIn('status', ['planning', 'active'])
                ->orderBy('sort_order')
                ->orderBy('key')
                ->get()
                ->map(fn ($e) => ['id' => $e->id, 'key' => $e->key, 'title' => $e->title])
                ->all()
            : [];

        $cycles = $project
            ? McpCycle::where('project_id', $project->id)
                ->whereIn('status', ['planning', 'active'])
                ->orderBy('start_date', 'desc')
                ->get()
                ->map(fn ($c) => [
                    'id'       => $c->id,
                    'key'      => $c->key,
                    'name'     => $c->name,
                    'status'   => $c->status,
                    'is_active' => $c->status === 'active',
                ])
                ->all()
            : [];

        $owners = McpTask::query()
            ->when($project, fn ($q) => $q->where('project_id', $project->id))
            ->whereNotNull('owner')
            ->distinct()
            ->orderBy('owner')
            ->pluck('owner')
            ->all();

        // Header context: cycle ativo + goal + progresso
        $cycleHeader = null;
        if ($cycleFoco) {
            $cycleHeader = [
                'id'              => $cycleFoco->id,
                'key'             => $cycleFoco->key,
                'name'            => $cycleFoco->name,
                'goal'            => $cycleFoco->goal,
                'start_date'      => optional($cycleFoco->start_date)->toDateString(),
                'end_date'        => optional($cycleFoco->end_date)->toDateString(),
                'status'          => $cycleFoco->status,
                'days_remaining'  => $cycleFoco->daysRemaining(),
                'progress_percent' => round($cycleFoco->progressPercent(), 0),
            ];
        }

        // KPIs globais (em cima do filtro atual — útil pro topo)
        $kpis = [
            'total'     => $tasks->whereNotIn('status', ['cancelled'])->count(),
            'doing'     => $tasks->where('status', 'doing')->count(),
            'review'    => $tasks->where('status', 'review')->count(),
            'blocked'   => $tasks->where('status', 'blocked')->count(),
            'p0_aberto' => $tasks->where('priority', 'p0')->whereNotIn('status', ['done', 'cancelled'])->count(),
        ];

        return Inertia::render('ProjectMgmt/Board/Index', [
            'project' => $project ? [
                'id'   => $project->id,
                'key'  => $project->key,
                'name' => $project->name,
            ] : null,
            'cycle'   => $cycleHeader,
            'kanban'  => $kanban,
            'kpis'    => $kpis,
            'columns' => $colunas,
            'epics'   => $epics,
            'cycles'  => $cycles,
            'owners'  => $owners,
            'filters' => [
                'project'   => $project?->key,
                'cycle'     => $cycleFoco?->id,
                'epic'      => $epicId,
                'component' => $componente,
                'owner'     => $owner,
            ],
        ]);
    }

    /**
     * PATCH /project-mgmt/board/{taskId}/status
     * Atualiza status via drag-drop no Kanban.
     *
     * Optimistic-lock opcional via `expected_updated_at` (timestamp unix
     * em segundos): se fornecido e diferente do atual, retorna 409 Conflict
     * com `current` state pra frontend reconciliar (PMG-001, ADR 0100).
     */
    public function updateStatus(Request $request, string $taskId): JsonResponse
    {
        $status = (string) $request->input('status');

        if (! in_array($status, McpTask::STATUSES, true)) {
            return response()->json(['error' => "Status '{$status}' inválido."], 422);
        }

        // R-PMG-005 — optimistic-lock pra detectar concurrent edit
        $expectedUpdatedAt = $request->input('expected_updated_at');
        if ($expectedUpdatedAt !== null) {
            $task = McpTask::where('task_id', strtoupper($taskId))->first()
                ?? McpTask::where('task_id', $taskId)->first()
                ?? McpTask::where('identifier', strtoupper($taskId))->first();

            if (! $task) {
                return response()->json(['error' => "Task '{$taskId}' não encontrada."], 404);
            }

            $currentTs = (int) ($task->updated_at?->timestamp ?? 0);
            if ((int) $expectedUpdatedAt !== $currentTs) {
                return response()->json([
                    'error'   => 'conflict',
                    'message' => 'Task atualizada por outro usuário. Recarregue.',
                    'current' => [
                        'task_id'    => $task->task_id,
                        'status'     => $task->status,
                        'updated_at' => $currentTs,
                    ],
                ], 409);
            }
        }

        $author = $this->resolveAuthor($request);

        try {
            $result = app(TaskCrudService::class)->update($taskId, ['status' => $status], $author);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }

        return response()->json([
            'ok'         => true,
            'task_id'    => $result['task']->task_id,
            'identifier' => $result['task']->identifier,
            'status'     => $status,
            'updated_at' => (int) ($result['task']->updated_at?->timestamp ?? 0),
        ]);
    }

    /**
     * GET /project-mgmt/board/{taskId}/detail
     *
     * Retorna payload completo do DetailSheet (PMG-004, ADR 0100):
     *   - task: serializeTask + description + parent_task_id
     *   - comments: lista ASC (até 100)
     *   - events: lista DESC (até 50, audit trail)
     *   - subtasks: lista filhos (parent_task_id = $task->id)
     *   - dependencies: lista raw (com target task_id; target detail é separate query)
     *   - dependency_targets: map {task_id => {display_id, title, status}} pra render
     *
     * Permission: copiloto.mcp.usage.all (middleware controller).
     */
    public function show(Request $request, string $taskId): JsonResponse
    {
        $task = McpTask::with('project:id,key,name')
            ->where('task_id', strtoupper($taskId))
            ->first()
            ?? McpTask::with('project:id,key,name')
                ->where('task_id', $taskId)
                ->first()
            ?? McpTask::with('project:id,key,name')
                ->where('identifier', strtoupper($taskId))
                ->first();

        if (! $task) {
            return response()->json(['error' => "Task '{$taskId}' não encontrada."], 404);
        }

        $comments = McpTaskComment::where('task_id', $task->task_id)
            ->orderBy('created_at')
            ->limit(100)
            ->get()
            ->map(fn (McpTaskComment $c) => [
                'id' => (int) $c->id,
                'author' => $c->author,
                'body' => $c->body,
                'created_at' => optional($c->created_at)->toIso8601String(),
            ])
            ->all();

        $events = McpTaskEvent::where('task_id', $task->task_id)
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (McpTaskEvent $e) => [
                'id' => (int) $e->id,
                'event_type' => $e->event_type,
                'from_value' => $e->from_value,
                'to_value' => $e->to_value,
                'author' => $e->author,
                'note' => $e->note,
                'occurred_at' => optional($e->occurred_at ?? $e->created_at)->toIso8601String(),
            ])
            ->all();

        $subtasks = McpTask::where('parent_task_id', $task->id)
            ->select('id', 'task_id', 'identifier', 'title', 'status', 'priority')
            ->orderBy('priority')
            ->orderBy('id')
            ->get()
            ->map(fn (McpTask $s) => [
                'task_id' => $s->task_id,
                'display_id' => $s->getDisplayIdAttribute(),
                'title' => $s->title,
                'status' => $s->status,
                'priority' => $s->priority ?? 'p2',
            ])
            ->all();

        $deps = McpTaskDependency::where('task_id', $task->task_id)->get();
        $targetIds = $deps->pluck('depends_on_task_id')->filter()->unique()->all();
        $targetMap = [];
        if (! empty($targetIds)) {
            $targets = McpTask::whereIn('task_id', $targetIds)
                ->select('id', 'task_id', 'identifier', 'title', 'status')
                ->get();
            foreach ($targets as $t) {
                $targetMap[$t->task_id] = [
                    'display_id' => $t->getDisplayIdAttribute(),
                    'title' => $t->title,
                    'status' => $t->status,
                ];
            }
        }

        $dependencies = $deps->map(fn (McpTaskDependency $d) => [
            'id' => (int) $d->id,
            'depends_on_task_id' => $d->depends_on_task_id,
            'type' => $d->type ?? 'blocks',
            'target' => $targetMap[$d->depends_on_task_id] ?? null,
        ])->all();

        $taskPayload = $this->serializeTask($task);
        $taskPayload['description'] = $task->description;
        $taskPayload['parent_task_id'] = $task->parent_task_id;
        $taskPayload['project_key'] = optional($task->project)->key;
        $taskPayload['project_name'] = optional($task->project)->name;

        return response()->json([
            'task' => $taskPayload,
            'comments' => $comments,
            'events' => $events,
            'subtasks' => $subtasks,
            'dependencies' => $dependencies,
        ]);
    }

    /**
     * POST /project-mgmt/board/{taskId}/comment
     *
     * Adiciona comentário com parsing automático de @mentions
     * (PMG-005, ADR 0100). Reusa TaskCrudService::comment() que já:
     *   - Cria mcp_task_comments row
     *   - Loga mcp_task_events com event_type=commented
     *   - Parseia regex /@([a-z][a-z0-9_-]+)/i
     *   - Cria mcp_inbox_notifications pra cada user mencionado
     *
     * Permission: copiloto.mcp.usage.all (middleware controller).
     */
    public function addComment(Request $request, string $taskId): JsonResponse
    {
        $validated = $request->validate([
            'body' => 'required|string|min:1|max:5000',
        ]);

        $body = (string) $validated['body'];
        $author = $this->resolveAuthor($request);

        try {
            $comment = app(TaskCrudService::class)->comment($taskId, $body, $author);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }

        return response()->json([
            'ok' => true,
            'comment' => [
                'id' => (int) $comment->id,
                'task_id' => $comment->task_id,
                'author' => $comment->author,
                'body' => $comment->body,
                'created_at' => optional($comment->created_at)->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * GET /project-mgmt/board/users/suggest?q=
     *
     * Autocomplete de usuários pra MentionInput (PMG-005, ADR 0100).
     * Filtra por permission `copiloto.mcp.usage.all` (mesmo gate de
     * acesso ao módulo) + LIKE em username/first_name/last_name.
     * Min 1 char, limit 10.
     */
    public function suggestUsers(Request $request): JsonResponse
    {
        $q = trim((string) $request->get('q', ''));

        if ($q === '') {
            return response()->json(['users' => []]);
        }

        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';

        $users = User::query()
            ->where(function ($qb) use ($like) {
                $qb->where('username', 'like', $like)
                    ->orWhere('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like);
            })
            ->whereHas('roles.permissions', function ($qb) {
                $qb->where('name', 'copiloto.mcp.usage.all');
            })
            ->orderBy('username')
            ->limit(10)
            ->get(['id', 'username', 'first_name', 'last_name'])
            ->map(fn (User $u) => [
                'id' => (int) $u->id,
                'username' => $u->username,
                'name' => trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')) ?: $u->username,
            ])
            ->values()
            ->all();

        return response()->json(['users' => $users]);
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
            // PMG-001 (ADR 0100) — timestamp pra optimistic-lock 409 conflict
            'updated_at'   => (int) ($t->updated_at?->timestamp ?? 0),
        ];
    }
}
