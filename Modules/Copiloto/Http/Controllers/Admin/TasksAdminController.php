<?php

namespace Modules\Copiloto\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Copiloto\Entities\Mcp\McpTask;
use Modules\Copiloto\Services\TaskRegistry\TaskCrudService;

/**
 * TaskRegistry Fase 2 (US-TR-007) — Page /copiloto/admin/tasks.
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

        // Kanban: tasks ativas (todo/doing/review/done) agrupadas por status
        $baseKanban = McpTask::query()
            ->when($modulo, fn ($q) => $q->where('module', $modulo))
            ->when($owner,  fn ($q) => $q->where('owner', $owner))
            ->when($sprint, fn ($q) => $q->where('sprint', $sprint))
            ->whereIn('status', ['todo', 'doing', 'review', 'done'])
            ->orderByRaw("FIELD(priority,'p0','p1','p2','p3')")
            ->orderBy('task_id');

        $porStatus = $baseKanban->get()
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

        // Backlog: todas as tasks com filtros mais amplos
        $backlogQ = McpTask::query()
            ->when($modulo, fn ($q) => $q->where('module', $modulo))
            ->when($owner,  fn ($q) => $q->where('owner', $owner))
            ->when($sprint, fn ($q) => $q->where('sprint', $sprint))
            ->orderByRaw("FIELD(status,'doing','review','todo','blocked','done','cancelled')")
            ->orderByRaw("FIELD(priority,'p0','p1','p2','p3')")
            ->orderBy('task_id')
            ->limit(200);

        $backlog = $backlogQ->get()->map(fn ($t) => [
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

        // KPIs
        $all = McpTask::query()
            ->when($modulo, fn ($q) => $q->where('module', $modulo))
            ->when($owner,  fn ($q) => $q->where('owner', $owner))
            ->get();

        $kpis = [
            'total'     => $all->whereNotIn('status', ['cancelled'])->count(),
            'p0'        => $all->where('priority', 'p0')->whereNotIn('status', ['done','cancelled'])->count(),
            'doing'     => $all->where('status', 'doing')->count(),
            'blocked'   => $all->where('status', 'blocked')->count(),
            'done'      => $all->where('status', 'done')->count(),
            'cancelled' => $all->where('status', 'cancelled')->count(),
            'total_h'   => (float) $all->whereNotIn('status', ['done','cancelled'])->sum('estimate_h'),
        ];

        // Listas de filtros disponíveis
        $modulos  = McpTask::distinct()->orderBy('module')->pluck('module');
        $owners   = McpTask::whereNotNull('owner')->distinct()->orderBy('owner')->pluck('owner');
        $sprints  = McpTask::whereNotNull('sprint')->distinct()->orderBy('sprint')->pluck('sprint');

        return Inertia::render('Copiloto/Admin/Tasks/Index', [
            'kanban'  => $porStatus,
            'backlog' => $backlog,
            'kpis'    => $kpis,
            'modulos' => $modulos,
            'owners'  => $owners,
            'sprints' => $sprints,
            'filters' => [
                'module' => $modulo,
                'owner'  => $owner,
                'sprint' => $sprint,
            ],
        ]);
    }

    /**
     * PATCH /copiloto/admin/tasks/{taskId}/status
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
