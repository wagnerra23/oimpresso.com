<?php

namespace Modules\ProjectMgmt\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Jana\Entities\Mcp\McpProject;
use Modules\Jana\Entities\Mcp\McpTask;
use Modules\Jana\Entities\Mcp\McpTaskEvent;

/**
 * ActivityController — /project-mgmt/activity (US-TR-205).
 *
 * Timeline cronológica de eventos em `mcp_task_events` (append-only).
 * Filtros: type, author, task_id, days.
 *
 * Permissão: copiloto.mcp.usage.all.
 */
class ActivityController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('can:copiloto.mcp.usage.all');
    }

    public function index(Request $request): Response
    {
        $project = $this->resolveProject($request);

        $type   = $request->get('type');
        $author = $request->get('author');
        $taskId = $request->get('task');
        $days   = (int) $request->get('days', 7);
        if ($days < 1) $days = 7;
        if ($days > 90) $days = 90;

        $taskIdsScope = null;
        if ($project) {
            $taskIdsScope = McpTask::where('project_id', $project->id)->pluck('task_id')->all();
        }

        $q = McpTaskEvent::query()
            ->where('created_at', '>=', now()->subDays($days))
            ->when($type, fn ($qq, $t) => $qq->where('event_type', $t))
            ->when($author, fn ($qq, $a) => $qq->where('author', $a))
            ->when($taskId, fn ($qq, $t) => $qq->where('task_id', strtoupper($t)))
            ->when($taskIdsScope !== null, fn ($qq) => $qq->whereIn('task_id', $taskIdsScope ?: ['__none__']))
            ->orderBy('created_at', 'desc')
            ->limit(300);

        $events = $q->get();

        $taskIds = $events->pluck('task_id')->unique()->all();
        $titleMap = McpTask::whereIn('task_id', $taskIds)->pluck('title', 'task_id')->toArray();

        $serialized = $events->map(fn (McpTaskEvent $e) => [
            'id'         => $e->id,
            'task_id'    => $e->task_id,
            'task_title' => $titleMap[$e->task_id] ?? null,
            'event_type' => $e->event_type,
            'from_value' => $e->from_value,
            'to_value'   => $e->to_value,
            'author'     => $e->author,
            'note'       => $e->note,
            'created_at' => optional($e->created_at)->toIso8601String(),
        ])->values()->all();

        // closure D-14: listas pros dropdowns (janela fixa 30d), não mudam com filtro
        // — pulam no partial reload do aplicar() (only: project/events/kpis/filters).
        $authors = fn () => McpTaskEvent::query()
            ->where('created_at', '>=', now()->subDays(30))
            ->whereNotNull('author')->distinct()->orderBy('author')->pluck('author')->all();

        $eventTypes = fn () => McpTaskEvent::query()
            ->where('created_at', '>=', now()->subDays(30))
            ->distinct()->orderBy('event_type')->pluck('event_type')->all();

        $kpis = [
            'last_24h'  => McpTaskEvent::where('created_at', '>=', now()->subDay())->count(),
            'last_7d'   => McpTaskEvent::where('created_at', '>=', now()->subDays(7))->count(),
            'created'   => $events->where('event_type', 'created')->count(),
            'completed' => $events->where('event_type', 'status_changed')->where('to_value', 'done')->count(),
        ];

        return Inertia::render('ProjectMgmt/Activity/Index', [
            'project'     => $project ? ['id' => $project->id, 'key' => $project->key, 'name' => $project->name] : null,
            'events'      => $serialized,
            'kpis'        => $kpis,
            'authors'     => $authors,
            'event_types' => $eventTypes,
            'filters'     => [
                'type' => $type, 'author' => $author, 'task' => $taskId, 'days' => $days,
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
