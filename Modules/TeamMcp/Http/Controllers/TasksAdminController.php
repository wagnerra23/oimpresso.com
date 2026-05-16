<?php

namespace Modules\TeamMcp\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Jana\Entities\Mcp\McpTask;
use Modules\Jana\Services\TaskRegistry\TaskCrudService;

/**
 * TaskRegistry Fase 2 (US-TR-007) — Page /team-mcp/tasks.
 *
 * Kanban (todo/doing/review/done) + Backlog filtros.
 * Permissão: copiloto.mcp.usage.all (Wagner/superadmin).
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
            ->map(fn ($tasks) => $tasks->map(fn ($t) => [
                'task_id'    => $t->task_id,
                'title'      => $t->title,
                'module'     => $t->module,
                'owner'      => $t->owner,
                'sprint'     => $t->sprint,
                'priority'   => $t->priority ?? 'p2',
                'estimate_h' => $t->estimate_h,
                'blocked_by' => $t->blocked_by ?? [],
                'status'     => $t->status,
            ])->values())
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

        return $backlogQ->get()->map(fn ($t) => [
            'task_id'    => $t->task_id,
            'title'      => $t->title,
            'module'     => $t->module,
            'owner'      => $t->owner,
            'sprint'     => $t->sprint,
            'priority'   => $t->priority ?? 'p2',
            'estimate_h' => $t->estimate_h,
            'blocked_by' => $t->blocked_by ?? [],
            'status'     => $t->status,
        ])->values()->toArray();
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
}
