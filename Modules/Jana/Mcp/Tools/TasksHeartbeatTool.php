<?php

declare(strict_types=1);

namespace Modules\Jana\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Modules\Jana\Services\WorkLease\WorkLeaseService;

/**
 * D1 (ADR 0278 / proposal #2766) — Tool tasks-heartbeat.
 *
 * Renova o TTL do lease que VOCÊ segura (estende +30min). Sem heartbeat o lease
 * estoura sozinho (R2: auto-limpa, sem cron) e a task volta ao pool — o que protege
 * contra lease órfão de sessão que crashou. Só age se o principal autenticado é o
 * dono do lease ativo.
 */
class TasksHeartbeatTool extends Tool
{
    protected string $name = 'tasks-heartbeat';

    protected string $title = 'Renovar (heartbeat) o lease de uma task';

    protected string $description = 'Estende o TTL (+30min) do lease que você segura numa US-*. Use periodicamente enquanto trabalha pra não perder a reserva. Se você não é o dono do lease ativo, é no-op. ADR 0278.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'task_id' => $schema->string()
                ->description('ID da task cujo lease renovar, ex: US-GOV-022')
                ->required(),
        ];
    }

    public function handle(Request $request): Response
    {
        $user = $request->user();
        if ($user === null) {
            return Response::error('Autenticação requerida.');
        }

        $taskId = trim((string) $request->get('task_id', ''));
        if ($taskId === '') {
            return Response::text('❌ task_id é obrigatório.');
        }

        $principal = (string) ($user->username ?? $user->email ?? ('user#' . $user->getAuthIdentifier()));
        $svc = app(WorkLeaseService::class);

        if (! $svc->heartbeat($taskId, $principal)) {
            return Response::text(
                "⚠️ Você (**{$principal}**) não segura um lease ATIVO de `{$taskId}` — nada a renovar.\n" .
                '_Talvez expirou ou é de outro. Veja `whats-locked` / dê `tasks-claim` de novo._'
            );
        }

        $lease = $svc->activeLeaseFor($taskId);

        return Response::text("✅ Lease de `{$taskId}` renovado — novo TTL expira {$lease->expires_at}.");
    }
}
