<?php

namespace Modules\Copiloto\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Copiloto\Entities\Mcp\McpCcMessage;
use Modules\Copiloto\Entities\Mcp\McpCcSession;

/**
 * MEM-CC-UI-1 (SPEC memory/requisitos/Copiloto/SPEC-cc-sessions.md) —
 * Tela /copiloto/admin/cc-sessions — KB sessões Claude Code do time.
 *
 * Schema mcp_cc_* já em prod desde 29-abr (3 tabelas).
 * Tool MCP cc-search consulta as mesmas tabelas.
 *
 * Permissão default: copiloto.cc.read.team (ver time) ou .all (admin).
 */
class CcSessionsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('can:copiloto.cc.read.team');
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $query = McpCcSession::query()
            ->acessivelPara($user)
            ->orderByDesc('started_at');

        // Filtros
        if ($userId = (int) $request->get('user_id')) {
            $query->where('user_id', $userId);
        }
        if ($from = $request->get('from')) {
            $query->where('started_at', '>=', $from);
        }
        if ($to = $request->get('to')) {
            $query->where('started_at', '<=', $to . ' 23:59:59');
        }
        if ($search = trim((string) $request->get('q', ''))) {
            $query->whereRaw('MATCH(summary_auto) AGAINST(? IN NATURAL LANGUAGE MODE)', [$search]);
        }
        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }
        if ($project = $request->get('project_path')) {
            $query->where('project_path', $project);
        }

        $page = (int) max(1, $request->get('page', 1));
        $perPage = 25;

        $paginator = $query
            ->select([
                'id', 'session_uuid', 'user_id', 'business_id',
                'project_path', 'git_branch', 'cc_version', 'entrypoint',
                'started_at', 'ended_at',
                'total_messages', 'total_tokens', 'total_cost_brl',
                'status', 'summary_auto', 'metadata',
            ])
            ->paginate($perPage, ['*'], 'page', $page)
            ->withQueryString();

        // Hidrata user_name pra cada session (1 query agregada)
        $userIds = $paginator->pluck('user_id')->unique()->values();
        $usersMap = User::whereIn('id', $userIds)
            ->get(['id', 'first_name', 'last_name', 'email'])
            ->keyBy('id');
        $paginator->getCollection()->transform(function ($s) use ($usersMap) {
            $u = $usersMap->get($s->user_id);
            $s->user_nome = $u
                ? trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')) ?: $u->email
                : "user#{$s->user_id}";
            return $s;
        });

        // KPIs globais (não filtrados pra dar visão total) — só pra users autorizados ver tudo
        $hoje = Carbon::today();
        $kpiQuery = $user->can('copiloto.cc.read.all')
            ? McpCcSession::query()
            : McpCcSession::query()->where('user_id', $user->id);

        $kpis = [
            'sessions_hoje'    => (clone $kpiQuery)->whereDate('started_at', $hoje)->count(),
            'sessions_total'   => (clone $kpiQuery)->count(),
            'custo_hoje_brl'   => (float) (clone $kpiQuery)->whereDate('started_at', $hoje)->sum('total_cost_brl'),
            'custo_30d_brl'    => (float) (clone $kpiQuery)->where('started_at', '>=', $hoje->copy()->subDays(30))->sum('total_cost_brl'),
            'devs_ativos_hoje' => (clone $kpiQuery)->whereDate('started_at', $hoje)->distinct('user_id')->count('user_id'),
            'tools_top'        => $this->topTools($user, $hoje),
        ];

        // Lista de devs no time (pro dropdown filtro)
        $devs = $user->can('copiloto.cc.read.all')
            ? User::query()->whereIn('id', McpCcSession::query()->select('user_id')->distinct())->get(['id', 'first_name', 'last_name', 'email'])
            : collect([$user]);

        // Project paths distintos pra dropdown
        $projects = $kpiQuery->select('project_path')->distinct()->orderBy('project_path')->pluck('project_path');

        return Inertia::render('Copiloto/Admin/CcSessions/Index', [
            'sessions' => $paginator,
            'filters'  => [
                'user_id'      => $userId ?: null,
                'from'         => $from,
                'to'           => $to,
                'q'            => $search,
                'status'       => $status,
                'project_path' => $project,
            ],
            'kpis' => $kpis,
            'devs' => $devs->map(fn ($d) => [
                'id' => $d->id,
                'nome' => trim(($d->first_name ?? '') . ' ' . ($d->last_name ?? '')) ?: $d->email,
            ])->values(),
            'projects' => $projects,
            'permissions' => [
                'read_all' => $user->can('copiloto.cc.read.all'),
                'curate'   => $user->can('copiloto.cc.curate'),
            ],
        ]);
    }

    /**
     * Detalhe da sessão — JSON pra preview lateral.
     * Inclui thread reconstruída + metadata expandido.
     */
    public function show(Request $request, string $sessionUuid): JsonResponse
    {
        $user = $request->user();
        $session = McpCcSession::query()
            ->acessivelPara($user)
            ->where('session_uuid', $sessionUuid)
            ->firstOrFail();

        // Hidrata user
        $userObj = User::find($session->user_id);
        $userNome = $userObj
            ? trim(($userObj->first_name ?? '') . ' ' . ($userObj->last_name ?? '')) ?: $userObj->email
            : "user#{$session->user_id}";

        // Carrega mensagens — paginado leve por enquanto
        $msgs = McpCcMessage::query()
            ->where('session_id', $session->id)
            ->orderBy('ts')
            ->limit(500)
            ->get([
                'id', 'msg_uuid', 'parent_uuid', 'msg_type', 'role', 'tool_name',
                'content_text', 'tokens_in', 'tokens_out', 'cache_read', 'cost_usd',
                'ts',
            ]);

        return response()->json([
            'session' => [
                'session_uuid'   => $session->session_uuid,
                'user_id'        => $session->user_id,
                'user_nome'      => $userNome,
                'project_path'   => $session->project_path,
                'git_branch'     => $session->git_branch,
                'cc_version'     => $session->cc_version,
                'entrypoint'     => $session->entrypoint,
                'started_at'     => optional($session->started_at)->toIso8601String(),
                'ended_at'       => optional($session->ended_at)->toIso8601String(),
                'total_messages' => $session->total_messages,
                'total_tokens'   => $session->total_tokens,
                'total_cost_brl' => (float) $session->total_cost_brl,
                'status'         => $session->status,
                'summary_auto'   => $session->summary_auto,
                'metadata'       => $session->metadata,
            ],
            'messages' => $msgs->map(fn ($m) => [
                'id'           => $m->id,
                'msg_uuid'     => $m->msg_uuid,
                'parent_uuid'  => $m->parent_uuid,
                'msg_type'     => $m->msg_type,
                'role'         => $m->role,
                'tool_name'    => $m->tool_name,
                'content_text' => $m->content_text,
                'tokens_in'    => $m->tokens_in,
                'tokens_out'   => $m->tokens_out,
                'cache_read'   => $m->cache_read,
                'cost_usd'     => (float) ($m->cost_usd ?? 0),
                'ts'           => optional($m->ts)->toIso8601String(),
            ]),
            'truncated' => $session->total_messages > 500,
        ]);
    }

    /**
     * Search FULLTEXT cross-dev em mcp_cc_messages.content_text.
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q'         => 'required|string|min:3|max:200',
            'tool'      => 'nullable|string|max:100',
            'user_id'   => 'nullable|integer',
            'days_ago'  => 'nullable|integer|min:1|max:365',
            'limit'     => 'nullable|integer|min:1|max:50',
        ]);

        $user = $request->user();
        $term = $request->input('q');
        $tool = $request->input('tool');
        $userId = $request->input('user_id');
        $days = (int) $request->input('days_ago', 30);
        $limit = (int) $request->input('limit', 15);

        $query = DB::table('mcp_cc_messages as m')
            ->join('mcp_cc_sessions as s', 's.id', '=', 'm.session_id')
            ->whereRaw('MATCH(m.content_text) AGAINST(? IN NATURAL LANGUAGE MODE)', [$term])
            ->where('m.ts', '>=', now()->subDays($days));

        // RBAC: se não pode read.all, só vê próprias
        if (!$user->can('copiloto.cc.read.all')) {
            $query->where('s.user_id', $user->id);
        }

        if ($tool) {
            $query->where('m.tool_name', $tool);
        }
        if ($userId) {
            $query->where('s.user_id', (int) $userId);
        }

        $hits = $query
            ->select([
                'm.id as msg_id',
                'm.msg_type',
                'm.tool_name',
                DB::raw('SUBSTRING(m.content_text, 1, 400) as snippet'),
                'm.ts',
                's.session_uuid',
                's.user_id',
                's.project_path',
                's.summary_auto',
            ])
            ->orderByDesc('m.ts')
            ->limit($limit)
            ->get();

        return response()->json([
            'q'    => $term,
            'hits' => $hits,
            'total' => $hits->count(),
        ]);
    }

    /**
     * Top N tools usadas hoje (pra KPI).
     */
    protected function topTools(?\App\User $user, Carbon $hoje): array
    {
        $q = DB::table('mcp_cc_messages as m')
            ->join('mcp_cc_sessions as s', 's.id', '=', 'm.session_id')
            ->whereDate('m.ts', $hoje)
            ->whereNotNull('m.tool_name');

        if (!$user || !$user->can('copiloto.cc.read.all')) {
            $q->where('s.user_id', optional($user)->id ?? -1);
        }

        return $q
            ->selectRaw('m.tool_name as tool, COUNT(*) as count')
            ->groupBy('m.tool_name')
            ->orderByDesc('count')
            ->limit(5)
            ->get()
            ->map(fn ($r) => ['tool' => $r->tool, 'count' => (int) $r->count])
            ->all();
    }
}
