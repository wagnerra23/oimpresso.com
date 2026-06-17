<?php

declare(strict_types=1);

namespace Modules\TeamMcp\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Modules\Jana\Entities\Mcp\McpAuditLog;
use Modules\Jana\Mcp\Tools\Concerns\AuthorizesMcpMutation;
use Modules\TeamMcp\Entities\CoworkHandoff;
use Throwable;

/**
 * Tool MCP handoff-lever — PR-7 Loop de Handoff Zero-Paste (Fase 2 · ADR 0283).
 *
 * Liga as 3 levers que o front da Forja só PINTA hoje (`ForjaMcp.tsx` —
 * `disabled "em breve"`): o gerenciamento da fila de design-handoffs sai do
 * "[W] mexe no banco à mão" e passa por uma tool MCP auditada, scopeada e
 * idempotente. NÃO há auto-merge — o merge do PR continua sendo o 1-clique do
 * [W] no GitHub (ADR 0283). As levers só roteiam ESTADO da fila:
 *
 *   - **re-disparar** (handoff `stale` = `pending` velho > 3d): re-arma o handoff
 *     na fila ativa. `status` segue `pending`; só a freshness reseta.
 *   - **devolver** (handoff `rejected`): reabre pro [CC] retrabalhar — volta a
 *     `pending` e limpa os artefatos do ack (pr_url/gate_status/applied_*).
 *   - **supersede** (handoff `pending`|`applied`): marca obsoleto — sai da lista
 *     ativa da Forja (que já exclui `superseded`). Append-only: a linha FICA.
 *
 * **Freshness sem coluna nova:** a Forja deriva `stale` de `created_at < now-3d`
 * ({@see \Modules\TeamMcp\Services\Forja\ForjaMcpService::displayStatus}). re-disparar
 * e devolver re-armam tocando `created_at = now()` — ele passa a valer "entrou na
 * fila ativa" (o ingest original sobrevive em `mcp_audit_log` + git). Escolha
 * deliberada pra manter o PR cirúrgico (zero migration, zero toque em ForjaMcpService).
 *
 * Defesas do adversário [AH] (espelha {@see HandoffAckTool}):
 *   - **A7 authz:** mutação — exige scope fino `jana.mcp.handoff.lever`, via
 *     {@see AuthorizesMcpMutation} como 1º statement.
 *   - **idempotência:** a lever só morde no `status` de origem certo. Lever num
 *     handoff fora do estado-origem → erro (o "409"): não muta em dobro.
 *   - **sem cache:** este handler NÃO cacheia (logo NÃO há `Cache::flush()` que
 *     derrubava o ERP inteiro — A2).
 *
 * @see Modules\TeamMcp\Mcp\Tools\HandoffAckTool
 * @see Modules\TeamMcp\Services\Forja\ForjaMcpService
 * @see memory/decisions/0283-handoff-loop-zero-paste.md
 */
class HandoffLeverTool extends Tool
{
    use AuthorizesMcpMutation;

    protected string $name = 'handoff-lever';

    protected string $title = 'Rotear estado de um handoff na fila (re-disparar/devolver/supersede)';

    protected string $description = 'Liga as levers da fila de design-handoffs (Fase 2 · ADR 0283), auditadas e idempotentes. re-disparar re-arma um handoff parado (pending velho). devolver reabre um rejected pro [CC] (volta a pending, limpa o ack). supersede marca pending/applied como obsoleto (sai da lista ativa, append-only: a linha fica). SEM auto-merge — o merge segue sendo o 1-clique do [W]. Exige scope jana.mcp.handoff.lever.';

    /** action → status(es) de origem em que a lever morde. */
    private const ORIGINS = [
        're-disparar' => ['pending'],
        'devolver'    => ['rejected'],
        'supersede'   => ['pending', 'applied'],
    ];

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
        if (! array_key_exists($action, self::ORIGINS)) {
            return Response::error("❌ action deve ser 're-disparar', 'devolver' ou 'supersede'.");
        }

        $origins = self::ORIGINS[$action];

        // Acha a versão no status de origem (default: a maior). Idempotência: só
        // morde no estado-origem certo — lever fora dele é "409".
        $query = CoworkHandoff::query()->where('slug', $slug)->whereIn('status', $origins);
        $version = $request->get('version');
        if ($version !== null && $version !== '') {
            $query->where('version', (int) $version);
        }
        $row = $query->orderByDesc('version')->first();

        if ($row === null) {
            $estados = implode('/', $origins);
            return Response::error(
                "⚠️ '{$slug}' não tem versão em '{$estados}' pra '{$action}'. As levers são idempotentes: só mordem no estado de origem."
            );
        }

        $from = (string) $row->status;
        $changes = $this->changesFor($action);
        $row->update($changes);
        $to = (string) $changes['status'];

        $this->audit($request, $slug, (int) $row->version, $action, $from, $to, $request->get('note'));

        $payload = [
            'ok'          => true,
            'slug'        => $slug,
            'version'     => (int) $row->version,
            'action'      => $action,
            'from_status' => $from,
            'to_status'   => $to,
        ];

        return Response::text((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * O patch de cada lever. re-disparar/devolver re-armam a freshness via
     * `created_at = now()` (sem coluna nova — ver doc da classe); supersede é
     * terminal e não mexe na freshness.
     *
     * @return array<string,mixed>
     */
    private function changesFor(string $action): array
    {
        return match ($action) {
            // Re-arma na fila ativa: status segue pending, freshness reseta.
            're-disparar' => ['status' => 'pending', 'created_at' => now()],
            // Reabre pro [CC]: volta a pending fresco e limpa os artefatos do ack.
            'devolver' => [
                'status'      => 'pending',
                'created_at'  => now(),
                'applied_at'  => null,
                'applied_by'  => null,
                'pr_url'      => null,
                'gate_status' => null,
            ],
            // Obsoleto: sai da lista ativa (ForjaMcpService exclui superseded).
            'supersede' => ['status' => 'superseded'],
            // Inalcançável: handle() já validou action contra ORIGINS antes de
            // chamar. Arm exigido pelo PHPStan (match exaustivo).
            default => throw new \InvalidArgumentException("action inválida: {$action}"),
        };
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
