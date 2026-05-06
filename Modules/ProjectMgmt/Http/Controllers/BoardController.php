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
     */
    public function updateStatus(Request $request, string $taskId): JsonResponse
    {
        $status = (string) $request->input('status');

        if (! in_array($status, McpTask::STATUSES, true)) {
            return response()->json(['error' => "Status '{$status}' inválido."], 422);
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
