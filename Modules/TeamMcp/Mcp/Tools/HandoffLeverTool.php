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
 * As 3 levers do loop (re-disparar/devolver/supersede) que estavam `disabled`
 * ("em breve") na Forja. Tira o [W] da operação manual do banco: a lever roteia
 * por uma mutação GOVERNADA (scope fino + audit + idempotência + append-only),
 * sem auto-merge (o merge segue o 1-clique do [W] no GitHub).
 *
 * Reusa {@see HandoffLeverService} — MESMA mutação que o endpoint web do cockpit
 * ({@see \Modules\TeamMcp\Http\Controllers\ForjaController::handoffLever}) chama.
 * Nada de duplicar a regra append-only (espelha o par
 * `handoff:ingest`/`handoff-submit` sobre {@see \Modules\TeamMcp\Services\HandoffIngestService}).
 *
 * Defesas do adversário [AH]:
 *   - **A7 authz:** mutação — exige scope fino `jana.mcp.handoff.lever`, via
 *     {@see AuthorizesMcpMutation} como 1º statement.
 *   - **append-only (A6):** supersede = lápide (`superseded`); re-disparar/devolver
 *     criam NOVA versão pending. NUNCA delete.
 *   - **idempotência/estado (o "409"):** lever fora do estado esperado é recusada
 *     (re-disparar só em pending; devolver só em rejected; supersede só em
 *     pending|applied) — não muta.
 *   - **A4 drift:** `version` opcional = a versão que o operador viu; se a fila
 *     andou, recusa ("recarregue") em vez de operar na versão errada.
 *   - **A2 cache:** NÃO usa cache (logo NÃO há `Cache::flush()`) — a Forja lê
 *     `cowork_handoffs` direto, sem o flush-global que derrubava o ERP.
 *
 * @see Modules\TeamMcp\Services\HandoffLeverService
 * @see Modules\TeamMcp\Mcp\Tools\HandoffAckTool
 * @see memory/decisions/0283-handoff-loop-zero-paste.md
 */
class HandoffLeverTool extends Tool
{
    use AuthorizesMcpMutation;

    protected string $name = 'handoff-lever';

    protected string $title = 'Operar uma lever de handoff (re-disparar/devolver/supersede)';

    protected string $description = 'Opera uma lever do loop de handoff (ADR 0283) sobre cowork_handoffs, append-only e sem auto-merge. action: re-disparar (pending parado/stale → lápide + novo pending), devolver (rejected → novo pending pro [CC] retrabalhar), supersede (pending|applied → lápide, sem substituta). Recusa lever fora do estado esperado (o "409") e drift de versão (A4). Exige scope jana.mcp.handoff.lever (mutação, A7).';

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()
                ->description("Lever: 're-disparar' (re-arma um pending parado), 'devolver' (manda um rejected de volta pro [CC]) ou 'supersede' (lápide num pending|applied).")
                ->required(),
            'slug' => $schema->string()
                ->description('slug do handoff a operar.')
                ->required(),
            'version' => $schema->integer()
                ->description('A4 drift guard: a versão que você viu na fila. Omitir = opera na maior versão atual.'),
        ];
    }

    public function handle(Request $request): Response
    {
        // A7: mutação — gate de escopo como PRIMEIRO statement.
        if ($deny = $this->authorizeMcpMutation($request, 'jana.mcp.handoff.lever')) {
            return $deny;
        }

        $action = (string) $request->get('action', '');
        if (! in_array($action, HandoffLeverService::ACTIONS, true)) {
            return Response::error('❌ action inválida — use: ' . implode(' | ', HandoffLeverService::ACTIONS) . '.');
        }

        $slug = trim((string) $request->get('slug', ''));
        if ($slug === '') {
            return Response::error('❌ slug é obrigatório.');
        }

        $versionInput = $request->get('version');
        $expected = ($versionInput !== null && $versionInput !== '') ? (int) $versionInput : null;

        $result = app(HandoffLeverService::class)->apply($action, $slug, $expected);

        if ($result['outcome'] === 'rejected') {
            $this->audit($request, $action, $slug, (int) $result['version'], 'rejected', $result['reason']);

            return Response::error($this->rejectMessage($action, (string) $result['reason'], (int) $result['version']));
        }

        $this->audit($request, $action, $slug, (int) $result['version'], $result['outcome'], null);

        $payload = [
            'ok'                 => true,
            'action'             => $action,
            'slug'               => $slug,
            'version'            => (int) $result['version'],
            'outcome'            => $result['outcome'], // redisparado | devolvido | superseded
            'superseded_version' => $result['superseded_version'],
            'hint'               => 'append-only aplicado; sem auto-merge (o merge é o 1-clique do [W]). Reflete na Forja na hora.',
        ];

        return Response::text((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** Mensagem de recusa legível por motivo (o "409"/"422"/"404" do mundo HTTP). */
    private function rejectMessage(string $action, string $reason, int $version): string
    {
        return match ($reason) {
            'not_found'  => "⚠️ handoff não encontrado (slug inexistente).",
            'stale_view' => "⚠️ a fila mudou — a versão atual é v{$version}. Recarregue a Forja antes de operar (A4 drift).",
            'state'      => match ($action) {
                're-disparar' => '⚠️ re-disparar só vale pra um handoff pendente (parado). Estado atual não permite.',
                'devolver'    => '⚠️ devolver só vale pra um handoff rejeitado.',
                'supersede'   => '⚠️ supersede só vale pra um handoff pendente ou aplicado (o atual já é lápide/rejeitado).',
                default       => '⚠️ lever fora do estado esperado.',
            },
            default      => '❌ action inválida.',
        };
    }

    /** Audit best-effort (action/slug/outcome) — espelha HandoffAckTool::audit, não trava a resposta. */
    private function audit(Request $request, string $action, string $slug, int $version, string $outcome, ?string $reason): void
    {
        try {
            $user = $request->user();
            McpAuditLog::registrar([
                'user_id'          => $user !== null ? (int) $user->getAuthIdentifier() : 0,
                'endpoint'         => 'tools/call',
                'tool_or_resource' => 'handoff-lever',
                // Convenção do mcp_audit_log: status = ok|denied (espelha HandoffSubmitTool).
                'status'           => $outcome === 'rejected' ? 'denied' : 'ok',
                'payload_summary'  => [
                    'action'  => $action,
                    'slug'    => $slug,
                    'version' => $version,
                    'outcome' => $outcome,
                    'reason'  => $reason,
                ],
            ]);
        } catch (Throwable) {
            // best-effort
        }
    }
}
