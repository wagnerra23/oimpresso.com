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
 * Tool MCP handoff-ack — PR-2 Loop de Handoff Zero-Paste (Fase 0 · ADR 0283).
 *
 * Fecha o loop (anti feedback-void): o Code reporta o desfecho de um handoff.
 *
 * Defesas do adversário [AH]:
 *   - **A7 authz:** mutação — exige scope fino `jana.mcp.handoff.ack` (só o
 *     ator-Code), via {@see AuthorizesMcpMutation} como 1º statement.
 *   - **A3 gate honesto:** `applied` SÓ é aceito com `pr_url` + `gate_status`
 *     verde (conformance && critique_score≥80 && a11y). Sem isso → erro (o "422"
 *     do mundo HTTP; numa Tool MCP é Response::error, que marca isError).
 *   - **idempotência:** ack em handoff não-pendente → erro (o "409"): 1 desfecho
 *     por versão.
 *   - **A2 cache:** este handler NÃO usa cache (logo NÃO há `Cache::flush()`). O
 *     handoff-pending não cacheia a lista — o ack reflete na hora, sem o
 *     flush-global que derrubava o ERP inteiro.
 *
 * @see Modules\TeamMcp\Mcp\Tools\HandoffPendingTool
 * @see Modules\Jana\Mcp\Tools\Concerns\AuthorizesMcpMutation
 * @see memory/decisions/0283-handoff-loop-zero-paste.md
 */
class HandoffAckTool extends Tool
{
    use AuthorizesMcpMutation;

    protected string $name = 'handoff-ack';

    protected string $title = 'Fechar um handoff de design (applied/rejected)';

    protected string $description = 'Fecha o loop do handoff (anti feedback-void). applied exige pr_url + gate_status verde (conformance && critique_score>=80 && a11y) senão é recusado; rejected exige note. Idempotente: ack em handoff não-pendente é recusado. Exige scope jana.mcp.handoff.ack (só o ator-Code).';

    public function schema(JsonSchema $schema): array
    {
        return [
            'slug' => $schema->string()
                ->description('slug do handoff a fechar')
                ->required(),
            'outcome' => $schema->string()
                ->description("'applied' (PR aberto + gates verdes) ou 'rejected' (com note)")
                ->required(),
            'version' => $schema->integer()
                ->description('Versão aplicada (default: maior pending do slug).'),
            'pr_url' => $schema->string()
                ->description('URL do PR — obrigatório quando applied.'),
            'gate_status' => $schema->object()
                ->description('A3: {conformance:bool, critique_score:int, a11y:bool}. applied SÓ com os 3 verdes.'),
            'note' => $schema->string()
                ->description('Motivo — obrigatório quando rejected.'),
            'audited_against' => $schema->string()
                ->description('SHA do main em que o Code aplicou (drift guard).'),
        ];
    }

    public function handle(Request $request): Response
    {
        // A7: mutação — gate de escopo como PRIMEIRO statement.
        if ($deny = $this->authorizeMcpMutation($request, 'jana.mcp.handoff.ack')) {
            return $deny;
        }

        $slug = trim((string) $request->get('slug', ''));
        if ($slug === '') {
            return Response::error('❌ slug é obrigatório.');
        }

        $outcome = (string) $request->get('outcome', '');
        if (! in_array($outcome, ['applied', 'rejected'], true)) {
            return Response::error("❌ outcome deve ser 'applied' ou 'rejected'.");
        }

        $version = $request->get('version');
        $prUrl = $request->get('pr_url');
        $note = $request->get('note');
        $gate = $request->get('gate_status');
        $auditedAgainst = $request->get('audited_against');

        // Acha a versão pendente (default: a maior). Idempotência: só 'pending' fecha.
        $query = CoworkHandoff::query()->where('slug', $slug)->where('status', 'pending');
        if ($version !== null && $version !== '') {
            $query->where('version', (int) $version);
        }
        $row = $query->orderByDesc('version')->first();

        if ($row === null) {
            // "409": não-pendente ou inexistente — 1 desfecho por versão.
            return Response::error("⚠️ handoff '{$slug}' não está pendente (ou não existe). ack é idempotente: 1 desfecho por versão.");
        }

        if ($outcome === 'applied') {
            if (! is_string($prUrl) || filter_var($prUrl, FILTER_VALIDATE_URL) === false) {
                return Response::error('❌ applied exige pr_url válido.');
            }
            // A3: sem os 3 gates verdes, applied é recusado (o "422").
            $g = is_array($gate) ? $gate : [];
            $green = (bool) ($g['conformance'] ?? false)
                && ((int) ($g['critique_score'] ?? 0) >= 80)
                && (bool) ($g['a11y'] ?? false);
            if (! $green) {
                return Response::error(
                    '⛔ gates não verdes — applied recusado (A3). Exige conformance=true && critique_score>=80 && a11y=true. '
                    . 'Recebido: ' . json_encode($g, JSON_UNESCAPED_UNICODE)
                );
            }
        } else { // rejected
            if (! is_string($note) || trim($note) === '') {
                return Response::error('❌ rejected exige note (por quê).');
            }
        }

        $drift = is_string($auditedAgainst) && $auditedAgainst !== ''
            && is_string($row->audited_against) && $row->audited_against !== ''
            && $auditedAgainst !== $row->audited_against;

        $agentId = $this->agentId($request);

        $row->update([
            'status'      => $outcome,
            'applied_at'  => now(),
            'applied_by'  => $agentId,
            'pr_url'      => is_string($prUrl) ? $prUrl : null,
            'gate_status' => is_array($gate) ? $gate : null,
        ]);

        $this->audit($request, $slug, (int) $row->version, $outcome, $prUrl, $drift, $note);

        $payload = [
            'ok'            => true,
            'slug'          => $slug,
            'version'       => (int) $row->version,
            'outcome'       => $outcome,
            'drift_warning' => $drift
                ? 'main mudou desde a auditoria do [CC] — reconferir antes do merge.'
                : null,
        ];

        return Response::text((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function agentId(Request $request): string
    {
        try {
            $id = $request->header('X-MCP-Agent-Id');

            return is_string($id) && $id !== '' ? $id : 'unknown';
        } catch (Throwable) {
            return 'unknown';
        }
    }

    /** Audit best-effort rico (slug/outcome/drift) — não trava a resposta. */
    private function audit(Request $request, string $slug, int $version, string $outcome, mixed $prUrl, bool $drift, mixed $note): void
    {
        try {
            $user = $request->user();
            McpAuditLog::registrar([
                'user_id'          => $user !== null ? (int) $user->getAuthIdentifier() : 0,
                'endpoint'         => 'tools/call',
                'tool_or_resource' => 'handoff-ack',
                'status'           => 'ok',
                'payload_summary'  => [
                    'slug'    => $slug,
                    'version' => $version,
                    'outcome' => $outcome,
                    'pr_url'  => is_string($prUrl) ? $prUrl : null,
                    'drift'   => $drift,
                    'note'    => is_string($note) ? mb_substr($note, 0, 200) : null,
                ],
            ]);
        } catch (Throwable) {
            // best-effort
        }
    }
}
