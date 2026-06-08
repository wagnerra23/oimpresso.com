<?php

declare(strict_types=1);

namespace Modules\Jana\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Modules\Jana\Entities\Mcp\McpInboxNotification;

/**
 * ADR 0070 — inbox unificado (mentions/assignments/reviews/comments/due_soon).
 */
class MyInboxTool extends Tool
{
    protected string $name = 'my-inbox';

    protected string $title = 'Caixa de entrada';

    protected string $description = 'Retorna notificações na minha caixa de entrada (mentions, assignments, review requests, comments). Default: unread, últimos 30 dias, CONSOME (mark_read=true igual email inbox). Use keep_unread:true pra apenas espiar.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'include_read' => $schema->boolean()->description('Incluir já lidas (default false)'),
            'limit' => $schema->integer()->description('Máx (default 50)'),
            'mark_read' => $schema->boolean()->description('Se true (DEFAULT), marca todas as retornadas como lidas (consome). Bug #3 fix 2026-05-13 — UX inbox precisa ser consumível.'),
            'keep_unread' => $schema->boolean()->description('Se true, preserva unread mesmo com mark_read default (modo "espiar"). Override explícito.'),
        ];
    }

    public function handle(Request $request): Response
    {
        // ADR 0081 Identity Mesh: pega user_id do token diretamente (Auth::user()
        // pode ser null em alguns contextos do Laravel-MCP — token é fonte canônica)
        $token = request()->attributes->get('mcp_token');
        $userId = $token?->user_id ?? Auth::user()?->id;
        $displayName = Auth::user()?->first_name ?? 'você';

        // Para IA-pareada (ai_agent com parent_actor humano), usa user_id do parent
        if ($token && !empty($token->actor_id)) {
            $actor = \DB::table('mcp_actors')
                ->where('id', $token->actor_id)
                ->whereNull('revoked_at')
                ->first();
            if ($actor && $actor->type === 'ai_agent' && $actor->parent_actor_id) {
                $parent = \DB::table('mcp_actors')
                    ->where('id', $actor->parent_actor_id)
                    ->whereNull('revoked_at')
                    ->first();
                if ($parent && $parent->user_id) {
                    $userId = (int) $parent->user_id;
                    $displayName = explode(' ', $parent->display_name)[0] ?? $parent->slug;
                }
            }
        }

        if (! $userId) {
            return Response::text("Sem user autenticado — token MCP precisa ser bound a um user (ou actor com parent humano).");
        }

        $includeRead = (bool) $request->get('include_read', false);
        $limit = min((int) $request->get('limit', 50), 200);

        // Bug #3 fix (2026-05-13) — UX inbox: default consume-on-read.
        // Antes era `mark_read=false` default → 33+ notifications acumulavam unread
        // poluindo my-inbox. Agora consome igual email; keep_unread:true escapa.
        $keepUnread = (bool) $request->get('keep_unread', false);
        $markRead = (bool) $request->get('mark_read', true) && ! $keepUnread;

        $q = McpInboxNotification::forUser($userId)
            ->where('created_at', '>', now()->subDays(30))
            ->orderByDesc('created_at');

        if (! $includeRead) {
            $q->unread();
        }

        $items = $q->limit($limit)->get();

        if ($items->isEmpty()) {
            return Response::text("📭 Inbox vazia (últimos 30 dias).");
        }

        $byType = $items->groupBy('type');
        $md = "# Inbox — {$displayName}\n\n";
        $md .= "Total: **" . $items->count() . "** (" . $items->where('read_at', null)->count() . " unread)\n\n";

        foreach ($byType as $type => $list) {
            $emoji = match ($type) {
                'mention' => '💬',
                'assigned' => '👤',
                'review_requested' => '👀',
                'status_changed' => '🔄',
                'commented' => '🗨️',
                'due_soon' => '⏰',
                'blocked_resolved' => '✅',
                default => '·',
            };
            $md .= "## {$emoji} " . strtoupper($type) . " ({$list->count()})\n\n";
            foreach ($list as $n) {
                $time = $n->created_at?->diffForHumans() ?? '';
                $unread = $n->read_at ? '' : ' **·new**';
                $taskRef = $n->task_id ? " [{$n->task_id}]" : '';
                $md .= "- {$n->body}{$taskRef}{$unread} _({$time})_\n";
            }
            $md .= "\n";
        }

        if ($markRead) {
            $count = McpInboxNotification::forUser($userId)
                ->whereIn('id', $items->pluck('id')->all())
                ->whereNull('read_at')
                ->update(['read_at' => now()]);
            $md .= "\n---\n_✅ {$count} marcadas como lidas._";
        }

        return Response::text($md);
    }
}
