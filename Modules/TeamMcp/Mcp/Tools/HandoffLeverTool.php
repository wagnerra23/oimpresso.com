<?php

declare(strict_types=1);

namespace Modules\TeamMcp\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Modules\Jana\Entities\Mcp\McpAuditLog;
use Modules\Jana\Mcp\Tools\Concerns\AuthorizesMcpMutation;
use Modules\TeamMcp\Services\HandoffLeverService;
use Throwable;

/**
 * Tool MCP handoff-lever — PR-7 Loop de Handoff Zero-Paste (Fase 2 · ADR 0283).
 *
 * Liga as 3 levers que o front da Forja só PINTAVA (`ForjaMcp.tsx`): o gerenciamento
 * da fila de design-handoffs sai do "[W] mexe no banco à mão" e passa por uma
 * mutação auditada, scopeada e idempotente. NÃO há auto-merge — o merge do PR
 * continua sendo o 1-clique do [W] no GitHub (ADR 0283). As levers só roteiam
 * ESTADO da fila:
 *
 *   - **re-disparar** (handoff `stale` = `pending` velho > 3d): re-arma o handoff
 *     na fila ativa. `status` segue `pending`; só a freshness reseta.
 *   - **devolver** (handoff `rejected`): reabre pro [CC] retrabalhar — volta a
 *     `pending` e limpa os artefatos do ack (pr_url/gate_status/applied_*).
 *   - **supersede** (handoff `pending`|`applied`): marca obsoleto — sai da lista
 *     ativa da Forja (que já exclui `superseded`). Append-only: a linha FICA.
 *
 * **Fonte única (PR-7b):** a mutação vive em {@see HandoffLeverService} — MESMO
 * núcleo que o endpoint web do cockpit
 * ({@see \Modules\TeamMcp\Http\Controllers\ForjaController::handoffLever}) chama
 * pros botões do front. Nada de duplicar a regra (espelha o par
 * `handoff:ingest`/`handoff-submit` sobre {@see \Modules\TeamMcp\Services\HandoffIngestService}).
 *
 * Defesas do adversário [AH] (espelha {@see HandoffAckTool}):
 *   - **A7 authz:** mutação — exige scope fino `jana.mcp.handoff.lever`, via
 *     {@see AuthorizesMcpMutation} como 1º statement.
 *   - **idempotência:** a lever só morde no `status` de origem certo. Lever num
 *     handoff fora do estado-origem → erro (o "409"): não muta em dobro.
 *   - **sem cache:** este handler NÃO cacheia (logo NÃO há `Cache::flush()` que
 *     derrubava o ERP inteiro — A2).
 *
 * @see Modules\TeamMcp\Services\HandoffLeverService
 * @see Modules\TeamMcp\Mcp\Tools\HandoffAckTool
 * @see memory/decisions/0283-handoff-loop-zero-paste.md
 */
class HandoffLeverTool extends Tool
{
    use AuthorizesMcpMutation;

    protected string $name = 'handoff-lever';

    protected string $title = 'Rotear estado de um handoff na fila (re-disparar/devolver/supersede)';

    protected string $description = 'Liga as levers da fila de design-handoffs (Fase 2 · ADR 0283), auditadas e idempotentes. re-disparar re-arma um handoff parado (pending velho). devolver reabre um rejected pro [CC] (volta a pending, limpa o ack). supersede marca pending/applied como obsoleto (sai da lista ativa, append-only: a linha fica). SEM auto-merge — o merge segue sendo o 1-clique do [W]. Exige scope jana.mcp.handoff.lever.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'slug' => $schema->string()
                ->description('slug do handoff alvo')
                ->required(),
            'action' => $schema->string()
                ->description("Lever: 're-disparar' (pending parado → re-arma), 'devolver' (rejected → reabre pending), 'supersede' (pending|applied → obsoleto).")
                ->required(),
            'version' => $schema->integer()
                ->description('Versão alvo (default: a maior no status de origem da lever).'),
            'note' => $schema->string()
                ->description('Motivo da lever — só auditado (não muda o body do handoff).'),
        ];
    }

    public function handle(Request $request): Response
    {
        // A7: mutação — gate de escopo como PRIMEIRO statement.
        if ($deny = $this->authorizeMcpMutation($request, 'jana.mcp.handoff.lever')) {
            return $deny;
        }

        $slug = trim((string) $request->get('slug', ''));
        if ($slug === '') {
            return Response::error('❌ slug é obrigatório.');
        }

        $action = (string) $request->get('action', '');
        if (! array_key_exists($action, HandoffLeverService::ORIGINS)) {
            return Response::error("❌ action deve ser 're-disparar', 'devolver' ou 'supersede'.");
        }

        $version = $request->get('version');
        $expected = ($version !== null && $version !== '') ? (int) $version : null;

        // Mutação governada — núcleo compartilhado com o endpoint web da Forja.
        $result = app(HandoffLeverService::class)->apply($slug, $action, $expected);

        if ($result === null) {
            // "409": não há versão no estado de origem — lever é idempotente.
            $estados = implode('/', HandoffLeverService::ORIGINS[$action]);

            return Response::error(
                "⚠️ '{$slug}' não tem versão em '{$estados}' pra '{$action}'. As levers são idempotentes: só mordem no estado de origem."
            );
        }

        $this->audit($request, $slug, $result['version'], $action, $result['from'], $result['to'], $request->get('note'));

        $payload = [
            'ok'          => true,
            'slug'        => $slug,
            'version'     => $result['version'],
            'action'      => $action,
            'from_status' => $result['from'],
            'to_status'   => $result['to'],
        ];

        return Response::text((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function agentId(): string
    {
        // Headers HTTP (X-MCP-Agent-Id, povoados pelo McpAuthMiddleware) vêm pelo
        // helper global request() — o Laravel\Mcp\Request não tem header(). Mesmo
        // padrão de HandoffAckTool/TasksClaimTool.
        $id = request()->header('X-MCP-Agent-Id');

        return is_string($id) && $id !== '' ? $id : 'unknown';
    }

    /** Audit best-effort (slug/action/transição) — não trava a resposta. */
    private function audit(Request $request, string $slug, int $version, string $action, string $from, string $to, mixed $note): void
    {
        try {
            $user = $request->user();
            McpAuditLog::registrar([
                'user_id'          => $user !== null ? (int) $user->getAuthIdentifier() : 0,
                'endpoint'         => 'tools/call',
                'tool_or_resource' => 'handoff-lever',
                'status'           => 'ok',
                'payload_summary'  => [
                    'slug'    => $slug,
                    'version' => $version,
                    'action'  => $action,
                    'from'    => $from,
                    'to'      => $to,
                    'actor'   => $this->agentId(),
                    'note'    => is_string($note) ? mb_substr($note, 0, 200) : null,
                ],
            ]);
        } catch (Throwable) {
            // best-effort
        }
    }
}
