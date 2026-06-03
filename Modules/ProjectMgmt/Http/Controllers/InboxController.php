<?php

namespace Modules\ProjectMgmt\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Jana\Entities\Mcp\McpInboxNotification;

/**
 * InboxController — /project-mgmt/inbox (US-TR-304..306, SPEC-UI-FASE7 Onda 2).
 *
 * Caixa de entrada dedicada: lê mcp_inbox_notifications do usuário autenticado
 * (mention/assigned/review_requested/status_changed/commented/due_soon/
 * blocked_resolved), agrupa por tipo, marca lido (individual + todas) e
 * deep-linka pra task/DetailSheet no Board.
 *
 * Lista = paridade com a tool MCP `my-inbox` (WHERE user_id=me [AND read_at IS
 * NULL por default]). Mesma query base do MyWorkController::buildInboxPayload.
 *
 * Multi-tenant (Tier 0 — ADR 0093): inbox é POR-PESSOA (isolamento via user_id,
 * NÃO via business_id — ADR 0070 marca mcp_inbox_notifications repo-wide). Toda
 * leitura/escrita escopa por auth()->id(); markRead/markAllRead nunca tocam
 * notificação de outro usuário (where user_id = auth). Não vaza entre usuários.
 *
 * Permissão: copiloto.mcp.usage.all (mesmo padrão do Board/MyWork).
 */
class InboxController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('can:copiloto.mcp.usage.all');
    }

    public function index(Request $request): Response
    {
        $userId   = (int) $request->user()->id;
        $showRead = $request->boolean('show_read', false);

        // RUNBOOK-inertia-defer-pattern.md — `filters` cheap eager.
        // inbox/inbox_stats compartilham a mesma user_id query → 1 closure.
        return Inertia::render('ProjectMgmt/Inbox/Index', [
            'inbox'       => Inertia::defer(fn () => $this->buildInboxPayload($userId, $showRead)['inbox']),
            'inbox_stats' => Inertia::defer(fn () => $this->buildInboxPayload($userId, $showRead)['inbox_stats']),
            'filters'     => ['show_read' => $showRead],
        ]);
    }

    /**
     * Constrói inbox + inbox_stats (compartilham mesma user_id query).
     * Memoiza por (userId, showRead) na request.
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
            ->limit(100);

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
     * PATCH /project-mgmt/inbox/{id}/read — US-TR-305.
     *
     * Marca 1 notificação como lida. Scoped por user_id: abort_unless garante
     * que o usuário só marca a PRÓPRIA notificação (Tier 0, nunca de outro).
     */
    public function markRead(Request $request, int $id): JsonResponse
    {
        $notif = McpInboxNotification::where('id', $id)
            ->where('user_id', (int) $request->user()->id)
            ->first();

        if (! $notif) {
            return response()->json(['error' => 'Notificação não encontrada.'], 404);
        }

        $notif->markRead();

        return response()->json([
            'ok'      => true,
            'id'      => $notif->id,
            'read_at' => optional($notif->read_at)->toIso8601String(),
        ]);
    }

    /**
     * PATCH /project-mgmt/inbox/read-all — US-TR-305.
     *
     * Marca TODAS as não-lidas do usuário autenticado. Escopo user_id no update.
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $count = McpInboxNotification::where('user_id', (int) $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['ok' => true, 'marked' => $count]);
    }
}
