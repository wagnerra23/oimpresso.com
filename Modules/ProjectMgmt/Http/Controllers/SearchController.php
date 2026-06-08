<?php

declare(strict_types=1);

namespace Modules\ProjectMgmt\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Jana\Entities\Mcp\McpCycle;
use Modules\Jana\Entities\Mcp\McpEpic;
use Modules\Jana\Entities\Mcp\McpProject;
use Modules\Jana\Entities\Mcp\McpTask;

/**
 * SearchController — Cmd+K command palette (PMG-002, ADR 0100).
 *
 * GET /project-mgmt/search?q={query}
 *
 * Busca cross-resource em tasks/epics/cycles/projects (mcp_*),
 * limitada a `copiloto.mcp.usage.all`. Resultados agrupados por tipo
 * pra renderização em cmdk/CommandPalette.
 *
 * Query LIKE simples (não Meilisearch — escopo MVP). Multi-tenant não
 * aplica em mcp_* (tasks são governance, sem business_id).
 */
class SearchController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('can:copiloto.mcp.usage.all');
    }

    public function index(Request $request): JsonResponse
    {
        $q = trim((string) $request->get('q', ''));

        if ($q === '' || mb_strlen($q) < 2) {
            return response()->json([
                'query' => $q,
                'results' => [
                    'tasks' => [],
                    'epics' => [],
                    'cycles' => [],
                    'projects' => [],
                ],
                'total' => 0,
            ]);
        }

        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';

        // Tasks: title, identifier, task_id, owner — limita 10
        $tasks = McpTask::query()
            ->where(function ($qb) use ($like) {
                $qb->where('title', 'like', $like)
                    ->orWhere('identifier', 'like', $like)
                    ->orWhere('task_id', 'like', $like)
                    ->orWhere('owner', 'like', $like)
                    ->orWhere('module', 'like', $like);
            })
            ->whereNotIn('status', ['cancelled'])
            ->orderByRaw("FIELD(status,'doing','review','todo','blocked','backlog','done','cancelled')")
            ->orderByRaw("FIELD(priority,'p0','p1','p2','p3','')")
            ->limit(10)
            ->get()
            ->map(fn (McpTask $t) => [
                'task_id' => $t->task_id,
                'identifier' => $t->identifier,
                'display_id' => $t->getDisplayIdAttribute(),
                'title' => $t->title,
                'status' => $t->status,
                'priority' => $t->priority ?? 'p2',
                'owner' => $t->owner,
                'module' => $t->module,
                'project_key' => optional($t->project)->key,
                'url' => '/project-mgmt/board?project=' . optional($t->project)->key,
            ])
            ->all();

        // Epics: key, title — limita 5
        $epics = McpEpic::query()
            ->where(function ($qb) use ($like) {
                $qb->where('title', 'like', $like)
                    ->orWhere('key', 'like', $like);
            })
            ->whereIn('status', ['planning', 'active'])
            ->orderBy('key')
            ->limit(5)
            ->get()
            ->map(fn (McpEpic $e) => [
                'id' => $e->id,
                'key' => $e->key,
                'title' => $e->title,
                'status' => $e->status,
                'project_key' => optional($e->project)->key,
                'url' => '/project-mgmt/roadmap?project=' . optional($e->project)->key,
            ])
            ->all();

        // Cycles: key, name, goal — limita 5
        $cycles = McpCycle::query()
            ->where(function ($qb) use ($like) {
                $qb->where('key', 'like', $like)
                    ->orWhere('name', 'like', $like)
                    ->orWhere('goal', 'like', $like);
            })
            ->whereIn('status', ['planning', 'active'])
            ->orderBy('start_date', 'desc')
            ->limit(5)
            ->get()
            ->map(fn (McpCycle $c) => [
                'id' => $c->id,
                'key' => $c->key,
                'name' => $c->name,
                'status' => $c->status,
                'project_key' => optional($c->project)->key,
                'url' => '/project-mgmt/board?project=' . optional($c->project)->key . '&cycle=' . $c->id,
            ])
            ->all();

        // Projects: key, name — limita 5
        $projects = McpProject::query()
            ->where(function ($qb) use ($like) {
                $qb->where('key', 'like', $like)
                    ->orWhere('name', 'like', $like);
            })
            ->orderBy('key')
            ->limit(5)
            ->get()
            ->map(fn (McpProject $p) => [
                'id' => $p->id,
                'key' => $p->key,
                'name' => $p->name,
                'status' => $p->status ?? 'active',
                'url' => '/project-mgmt/board?project=' . $p->key,
            ])
            ->all();

        return response()->json([
            'query' => $q,
            'results' => [
                'tasks' => $tasks,
                'epics' => $epics,
                'cycles' => $cycles,
                'projects' => $projects,
            ],
            'total' => count($tasks) + count($epics) + count($cycles) + count($projects),
        ]);
    }
}
