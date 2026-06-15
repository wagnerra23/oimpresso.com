<?php

declare(strict_types=1);

namespace Modules\Jana\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Modules\Jana\Services\WorkLease\WorkLeaseService;

/**
 * D1 (ADR 0278 / proposal #2766) — Tool tasks-claim.
 *
 * Compare-and-set: pega um lease ATIVO numa task (no máx 1 por task). É o Tier 2
 * (lock real) do ADR 0119 — o whats-active (Tier 1) só alerta; este reserva. Sessão
 * que vai trabalhar uma US dá claim antes; se outro principal segura, recebe quem é
 * o dono em vez de colidir. Estado vive no Postgres (R1: trabalho-em-voo deixa de ser
 * invisível). TTL 30min + heartbeat (tasks-heartbeat) renova.
 *
 * human_principal = dono do token MCP (não-spoofável). agent_id é hint de correlação.
 */
class TasksClaimTool extends Tool
{
    protected string $name = 'tasks-claim';

    protected string $title = 'Reservar (claim) uma task — lease de coordenação';

    protected string $description = 'Pega um lease ATIVO numa US-* (no máx 1 por task, TTL 30min). Use ANTES de começar a trabalhar uma task pra evitar que outra sessão/IA pegue a mesma. Se já houver dono ativo, retorna quem é (não bloqueia o resto). Renove com tasks-heartbeat; veja todos com whats-locked. ADR 0278 (Tier 2 do whats-active).';

    public function schema(JsonSchema $schema): array
    {
        return [
            'task_id' => $schema->string()
                ->description('ID da task, ex: US-GOV-022')
                ->required(),
            'agent_id' => $schema->string()
                ->description('Hint de qual agente (claude-code/cursor/...). Não-confiável (correlação só).'),
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
        $agentId = trim((string) $request->get('agent_id', '')) ?: null;

        $svc = app(WorkLeaseService::class);

        if (! $svc->taskExists($taskId)) {
            return Response::text("❌ Task `{$taskId}` não existe no cache mcp_tasks. Crie/sincronize a US antes de dar claim.");
        }

        $res = $svc->claim($taskId, $principal, $agentId);

        if (! $res['ok']) {
            $h = $res['holder'];
            $agent = $h->agent_id ? " · agente `{$h->agent_id}`" : '';

            return Response::text(
                "🔒 Task `{$taskId}` já tem lease ATIVO de **{$h->human_principal}**{$agent} (expira {$h->expires_at}).\n" .
                '_Coordene antes de pegar — ou veja `whats-locked`. Lease estoura sozinho no TTL se o dono sumir._'
            );
        }

        $lease = $res['lease'];
        $verbo = ($res['renewed'] ?? false) ? 'renovado' : 'adquirido';

        return Response::text(
            "✅ Lease {$verbo} em `{$taskId}` por **{$principal}** — expira {$lease->expires_at} (TTL 30min).\n" .
            '_Renove com `tasks-heartbeat task_id:' . $taskId . '` enquanto trabalha; libere ao terminar (release automático no TTL)._'
        );
    }
}
