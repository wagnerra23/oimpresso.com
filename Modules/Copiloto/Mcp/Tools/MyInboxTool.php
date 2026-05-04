<?php

declare(strict_types=1);

namespace Modules\Copiloto\Mcp\Tools;

use Illuminate\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Modules\Copiloto\Entities\Mcp\McpInboxNotification;

/**
 * ADR 0070 — inbox unificado (mentions/assignments/reviews/comments/due_soon).
 */
class MyInboxTool extends Tool
{
    protected string $name = 'my-inbox';

    protected string $title = 'Caixa de entrada';

    protected string $description = 'Retorna notificações na minha caixa de entrada (mentions, assignments, review requests, comments). Default: unread, últimos 30 dias.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'include_read' => $schema->boolean()->description('Incluir já lidas (default false)'),
            'limit' => $schema->integer()->description('Máx (default 50)'),
            'mark_read' => $schema->boolean()->description('Se true, marca todas as retornadas como lidas'),
        ];
    }

    public function handle(Request $request): Response
    {
        $user = Auth::user();
        if (! $user) {
            return Response::text("Sem user autenticado — token MCP precisa ser bound a um user.");
        }

        $includeRead = (bool) $request->get('include_read', false);
        $limit = min((int) $request->get('limit', 50), 200);
        $markRead = (bool) $request->get('mark_read', false);

        $q = McpInboxNotification::forUser((int) $user->id)
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
        $md = "# Inbox — {$user->first_name}\n\n";
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
            $count = McpInboxNotification::forUser((int) $user->id)
                ->whereIn('id', $items->pluck('id')->all())
                ->whereNull('read_at')
                ->update(['read_at' => now()]);
            $md .= "\n---\n_✅ {$count} marcadas como lidas._";
        }

        return Response::text($md);
    }
}
