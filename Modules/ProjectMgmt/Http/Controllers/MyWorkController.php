<?php

namespace Modules\ProjectMgmt\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Jana\Entities\Mcp\McpCycle;
use Modules\Jana\Entities\Mcp\McpInboxNotification;
use Modules\Jana\Entities\Mcp\McpProject;
use Modules\Jana\Entities\Mcp\McpTask;
use Modules\Jana\Services\TaskRegistry\TaskCrudService;

/**
 * MyWorkController — /project-mgmt/my-work (US-TR-204).
 *
 * Homepage do operador: tasks ativas (todo/doing/review/blocked) agrupadas
 * por Cycle + Inbox `mcp_inbox_notifications` (mention/assigned/review_requested/
 * status_changed/commented/due_soon/blocked_resolved).
 *
 * Permissão: copiloto.mcp.usage.all (mesmo padrão do Board).
 */
class MyWorkController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('can:copiloto.mcp.usage.all');
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $userId = (int) $user->id;
        $username = $this->resolveUsername($user);
        $project = $this->resolveProject($request);
        $projectId = $project?->id;
        $showRead = $request->boolean('show_read', false);

        // RUNBOOK-inertia-defer-pattern.md (Wave 11 D6.a) — defer queries pesadas.
        // `username`/`project`/`filters` cheap eager.
        // my_work/inbox/inbox_stats/kpis compartilham contagens — agrupados em
        // 2 closures (work payload + inbox payload). kpis depende dos 2 → 3ª closure.
        return Inertia::render('ProjectMgmt/MyWork/Index', [
            'project'     => $project ? ['id' => $project->id, 'key' => $project->key, 'name' => $project->name] : null,
            'username'    => $username,
            'my_work'     => Inertia::defer(fn () => $this->buildMyWorkPayload($projectId, $username)['my_work']),
            'inbox'       => Inertia::defer(fn () => $this->buildInboxPayload($userId, $showRead)['inbox']),
            'inbox_stats' => Inertia::defer(fn () => $this->buildInboxPayload($userId, $showRead)['inbox_stats']),
            'kpis'        => Inertia::defer(fn () => $this->buildKpisPayload($projectId, $username, $userId, $showRead)),
            'filters'     => ['show_read' => $showRead],
        ]);
    }

    /**
     * Constrói my_work (tasks ativas do owner agrupadas por cycle).
     * Memoiza por (projectId, username) na request.
     *
     * @return array{my_work: array<int,array<string,mixed>>, tasks: \Illuminate\Support\Collection}
     */
    protected function buildMyWorkPayload(?int $projectId, string $username): array
    {
        static $cache = [];
        $key = "{$projectId}::{$username}";
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $tasksQ = McpTask::query()
            ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
            ->where('owner', $username)
            ->whereIn('status', ['todo', 'doing', 'review', 'blocked'])
            ->orderByRaw("FIELD(status,'doing','review','todo','blocked')")
            ->orderByRaw("FIELD(priority,'p0','p1','p2','p3','')")
            ->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_date')
            ->orderBy('task_id');

        $tasks = $tasksQ->get()->map(fn (McpTask $t) => $this->serializeTask($t));

        $cycleIds = $tasks->pluck('cycle_id')->filter()->unique()->all();
        $cycleMap = McpCycle::whereIn('id', $cycleIds)->get()->keyBy('id');

        $myWork = [];
        $project = $projectId ? McpProject::find($projectId) : null;
        $cycleAtivo = $project ? $project->activeCycle() : null;
        $cycleAtivoId = $cycleAtivo?->id;

        $bucketsOrder = [];
        if ($cycleAtivoId) {
            $bucketsOrder[$cycleAtivoId] = $this->cycleHeader($cycleMap->get($cycleAtivoId) ?? $cycleAtivo, true);
        }
        foreach ($cycleMap as $cid => $c) {
            if ($cid === $cycleAtivoId) continue;
            $bucketsOrder[$cid] = $this->cycleHeader($c, false);
        }
        $bucketsOrder['none'] = ['key' => 'Sem cycle', 'label' => 'Sem cycle', 'is_active' => false, 'days_remaining' => null, 'goal' => null];

        foreach ($bucketsOrder as $cid => $header) {
            $bucketTasks = $tasks->filter(fn ($t) => ($t['cycle_id'] ?? null) == ($cid === 'none' ? null : $cid))->values()->all();
            if (! $bucketTasks && $cid === 'none') continue;
            if (! $bucketTasks && $cid !== $cycleAtivoId) continue;
            $myWork[] = ['header' => $header, 'tasks' => $bucketTasks];
        }

        $cache[$key] = ['my_work' => $myWork, 'tasks' => $tasks];
        return $cache[$key];
    }

    /**
     * Constrói inbox + inbox_stats (compartilham mesma user_id query).
     *
     * @return array{inbox: array<int,array<string,mixed>>, inbox_stats: array<string,int>}
     */
    protected function buildInboxPayload(int $userId, bool $showRead): array
    {
        static $cache = [];
        $key = "{$userId}::" . ($showRead ? '1' : '0');
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $inboxQ = McpInboxNotification::query()
            ->where('user_id', $userId)
            ->orderBy('read_at', 'asc')
            ->orderBy('created_at', 'desc')
            ->limit(60);

        if (! $showRead) {
            $inboxQ->whereNull('read_at');
        }

        $rawInbox = $inboxQ->get();

        $actorIds = $rawInbox->pluck('actor_id')->filter()->unique()->all();
        $actorMap = $actorIds
            ? DB::table('users')->whereIn('id', $actorIds)->pluck('first_name', 'id')->toArray()
            : [];

        $inbox = $rawInbox->map(fn (McpInboxNotification $n) => [
            'id'         => $n->id,
            'type'       => $n->type,
            'task_id'    => $n->task_id,
            'actor_id'   => $n->actor_id,
            'actor_name' => $n->actor_id ? ($actorMap[$n->actor_id] ?? "user#{$n->actor_id}") : 'sistema',
            'body'       => $n->body,
            'created_at' => optional($n->created_at)->toIso8601String(),
            'read_at'    => optional($n->read_at)->toIso8601String(),
            'is_read'    => $n->read_at !== null,
        ])->values()->all();

        $inboxStats = [
            'unread'    => McpInboxNotification::where('user_id', $userId)->whereNull('read_at')->count(),
            'total_30d' => McpInboxNotification::where('user_id', $userId)
                ->where('created_at', '>=', now()->subDays(30))
                ->count(),
        ];

        $cache[$key] = ['inbox' => $inbox, 'inbox_stats' => $inboxStats];
        return $cache[$key];
    }

    /**
     * KPIs combinam tasks (do my_work) + unread (do inbox).
     * @return array<string,int>
     */
    protected function buildKpisPayload(?int $projectId, string $username, int $userId, bool $showRead): array
    {
        $work = $this->buildMyWorkPayload($projectId, $username);
        $inbox = $this->buildInboxPayload($userId, $showRead);
        $tasks = $work['tasks'];

        return [
            'doing'   => $tasks->where('status', 'doing')->count(),
            'review'  => $tasks->where('status', 'review')->count(),
            'blocked' => $tasks->where('status', 'blocked')->count(),
            'p0'      => $tasks->where('priority', 'p0')->count(),
            'overdue' => $tasks->where('is_overdue', true)->count(),
            'unread'  => $inbox['inbox_stats']['unread'],
        ];
    }

    public function markRead(Request $request, int $id): JsonResponse
    {
        $notif = McpInboxNotification::where('id', $id)
            ->where('user_id', (int) $request->user()->id)
            ->first();

        if (! $notif) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $notif->markRead();

        return response()->json([
            'ok'      => true,
            'id'      => $notif->id,
            'read_at' => optional($notif->read_at)->toIso8601String(),
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $count = McpInboxNotification::where('user_id', (int) $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['ok' => true, 'marked' => $count]);
    }

    public function bumpStatus(Request $request, string $taskId): JsonResponse
    {
        $status = (string) $request->input('status');
        if (! in_array($status, McpTask::STATUSES, true)) {
            return response()->json(['error' => "Status '{$status}' inválido."], 422);
        }

        try {
            $result = app(TaskCrudService::class)->update(
                $taskId,
                ['status' => $status],
                $this->resolveUsername($request->user()),
            );
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }

        return response()->json([
            'ok'      => true,
            'task_id' => $result['task']->task_id,
            'status'  => $status,
        ]);
    }

    // ---------- helpers ----------

    protected function resolveProject(Request $request): ?McpProject
    {
        $key = strtoupper((string) $request->get('project', config('projectmgmt.default_project_key', 'COPI')));
        if ($key === '') return null;
        return McpProject::where('key', $key)->first();
    }

    protected function resolveUsername(\Illuminate\Contracts\Auth\Authenticatable $user): string
    {
        if (! empty($user->username)) return strtolower($user->username);
        if (! empty($user->first_name)) return strtolower($user->first_name);
        return 'system';
    }

    protected function cycleHeader(?McpCycle $c, bool $isActiveDefault): array
    {
        if (! $c) {
            return ['key' => '—', 'label' => '—', 'is_active' => false, 'days_remaining' => null, 'goal' => null];
        }
        return [
            'id'             => $c->id,
            'key'            => $c->key,
            'label'          => $c->key . ($c->name ? ' — ' . $c->name : ''),
            'goal'           => $c->goal,
            'is_active'      => $c->status === 'active',
            'days_remaining' => $c->status === 'active' ? $c->daysRemaining() : null,
        ];
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
