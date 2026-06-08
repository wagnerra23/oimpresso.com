<?php

declare(strict_types=1);

namespace Modules\Jana\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Modules\Jana\Entities\Mcp\McpTask;

/**
 * ADR 0070 — minhas tasks ativas (Jira/Linear "My Issues").
 *
 * Filtra por owner = username derivado do user autenticado (token MCP),
 * ou owner explícito via param. Status default = ativo (doing/review/blocked/todo).
 */
class MyWorkTool extends Tool
{
    protected string $name = 'my-work';

    protected string $title = 'Minhas tasks ativas';

    protected string $description = 'Retorna minhas tasks ativas (status doing/review/blocked/todo). Owner derivado do token MCP autenticado, ou passe owner explícito.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'owner' => $schema->string()->description('Username (default: usuário autenticado)'),
            'include_backlog' => $schema->boolean()->description('Incluir status=backlog (default false)'),
            'limit' => $schema->integer()->description('Máx tasks (default 30)'),
        ];
    }

    public function handle(Request $request): Response
    {
        $owner = (string) $request->get('owner', $this->resolveCurrentOwner());
        if ($owner === '') {
            return Response::text("Owner não pôde ser resolvido. Passe explicitamente: `my-work owner:wagner`.");
        }

        $includeBacklog = (bool) $request->get('include_backlog', false);
        $limit = (int) $request->get('limit', 30);

        $statuses = $includeBacklog
            ? ['backlog', 'todo', 'doing', 'review', 'blocked']
            : ['todo', 'doing', 'review', 'blocked'];

        $tasks = McpTask::where('owner', strtolower($owner))
            ->whereIn('status', $statuses)
            ->orderByRaw("FIELD(status, 'doing','review','blocked','todo','backlog')")
            ->orderBy('priority')
            ->orderByRaw('due_date IS NULL, due_date ASC')
            ->limit($limit)
            ->get();

        if ($tasks->isEmpty()) {
            return Response::text("✨ Sem tasks ativas pra @{$owner}. Use `tasks-list owner:{$owner} status:done` pra ver fechadas.");
        }

        $md = "# Tasks ativas — @{$owner}\n\n";
        $md .= "Total: **{$tasks->count()}**\n\n";

        $byStatus = $tasks->groupBy('status');
        foreach (['doing', 'review', 'blocked', 'todo', 'backlog'] as $st) {
            $items = $byStatus->get($st);
            if (! $items || $items->isEmpty()) continue;

            $emoji = match ($st) {
                'doing' => '🔥',
                'review' => '👀',
                'blocked' => '⛔',
                'todo' => '📋',
                'backlog' => '🗂️',
                default => '·',
            };

            $md .= "## {$emoji} " . strtoupper($st) . " ({$items->count()})\n\n";
            foreach ($items as $t) {
                $id = $t->getDisplayIdAttribute();
                $due = $t->due_date ? ' · 📅 ' . $t->due_date->toDateString() : '';
                $prio = $t->priority ? " `{$t->priority}`" : '';
                $points = $t->story_points ? " · {$t->story_points}pt" : '';
                $md .= "- **{$id}**{$prio} {$t->title}{$points}{$due}\n";
            }
            $md .= "\n";
        }

        return Response::text($md);
    }

    protected function resolveCurrentOwner(): string
    {
        // ADR 0081 Identity Mesh: token.actor_id → mcp_actors.slug
        // IA-pareada (ai_agent com parent_actor) usa slug do humano que representa.
        $token = request()->attributes->get('mcp_token');
        if ($token && !empty($token->actor_id)) {
            $actor = \DB::table('mcp_actors')
                ->where('id', $token->actor_id)
                ->whereNull('revoked_at')
                ->first();
            if ($actor) {
                if ($actor->type === 'ai_agent' && $actor->parent_actor_id) {
                    $parent = \DB::table('mcp_actors')
                        ->where('id', $actor->parent_actor_id)
                        ->whereNull('revoked_at')
                        ->first();
                    if ($parent) return $parent->slug;
                }
                return $actor->slug;
            }
        }

        // Fallback legacy (pré-Identity Mesh)
        $user = Auth::user();
        if (! $user) return '';
        $u = strtolower($user->username ?? $user->first_name ?? '');
        if ($u !== '') return $u;
        if (! empty($user->email)) {
            return strtolower(explode('@', $user->email)[0]);
        }
        return '';
    }
}
