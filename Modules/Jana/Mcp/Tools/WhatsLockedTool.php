<?php

declare(strict_types=1);

namespace Modules\Jana\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Modules\Jana\Services\WorkLease\WorkLeaseService;

/**
 * D1 (ADR 0278 / proposal #2766) — Tool whats-locked.
 *
 * "Quais tasks estão RESERVADAS (lease ativo) agora e por quem?" Complementa o
 * whats-active (ADR 0119 Tier 1, paths tocados / alerta passivo): este é o estado
 * canônico de quem-faz-o-quê (R4), derivado do compare-and-set real. Faz sweep de
 * expirados antes de listar, então só mostra leases dentro do TTL.
 */
class WhatsLockedTool extends Tool
{
    protected string $name = 'whats-locked';

    protected string $title = 'Tasks reservadas (leases ativos) + por quem';

    protected string $description = 'Lista as US-* com lease ATIVO (reservadas por uma sessão/IA agora) + dono + expiração. Use no início de sessão (depois de whats-active) pra não pegar task que já tem dono. ADR 0278 (Tier 2 do whats-active).';

    public function schema(JsonSchema $schema): array
    {
        return [
            'limit' => $schema->integer()
                ->min(1)
                ->max(50)
                ->default(30)
                ->description('Máx leases retornados (default 30)'),
        ];
    }

    public function handle(Request $request): Response
    {
        if ($request->user() === null) {
            return Response::error('Autenticação requerida.');
        }

        $limit = max(1, min(50, (int) $request->get('limit', 30)));
        $leases = app(WorkLeaseService::class)->activeLeases($limit);

        if ($leases->isEmpty()) {
            return Response::text("✅ Nenhuma task com lease ativo agora.\n_Pode dar `tasks-claim` em qualquer uma sem corrida._");
        }

        $linhas = $leases->map(function ($l) {
            $agent = $l->agent_id ? " · `{$l->agent_id}`" : '';

            return "- `{$l->task_id}` → **{$l->human_principal}**{$agent} (expira {$l->expires_at})";
        })->implode("\n");

        return Response::text(
            "🔒 **{$leases->count()} task(s) reservada(s)** (lease ativo):\n\n{$linhas}\n\n" .
            '_Lease estoura sozinho no TTL (30min sem heartbeat) → volta ao pool._'
        );
    }
}
