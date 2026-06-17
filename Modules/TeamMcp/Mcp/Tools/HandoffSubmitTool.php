<?php

declare(strict_types=1);

namespace Modules\TeamMcp\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Modules\Jana\Entities\Mcp\McpAuditLog;
use Modules\Jana\Mcp\Tools\Concerns\AuthorizesMcpMutation;
use Modules\TeamMcp\Entities\McpIngestHeartbeat;
use Modules\TeamMcp\Services\HandoffIngestService;
use Throwable;

/**
 * Tool MCP handoff-submit — PR-6a Loop de Handoff Zero-Paste (Fase 0 · ADR 0283).
 *
 * **Landing-pad ASSINADO por HTTP:** recebe um handoff de design (Cowork→Code) e
 * cria 'pending' em `cowork_handoffs`, SEM depender de SSH no CT 100 nem de commit
 * no repo. Fecha o "primeiro hop" do loop zero-paste — o gatilho é uma GitHub
 * Action on-push ({@see .github/workflows/handoff-sign-submit.yml}) que assina e
 * chama este tool, sem o [W] colar nada nem computar HMAC.
 *
 * Reusa {@see HandoffIngestService} — MESMA validação HMAC, MESMO `source_hash`,
 * MESMO append-only do `handoff:ingest` (PR-1). Nada de recriar ingest (NÃO-FAZER
 * do handoff). O runner de CI não alcança o DB de prod (docblock do
 * {@see \Modules\TeamMcp\Console\Commands\HandoffIngestCommand}) — por isso o
 * transporte é HTTP pra ESTE tool, não `artisan handoff:ingest` no runner.
 *
 * Defesas do adversário [AH]:
 *   - **A7 authz:** mutação — exige scope fino `jana.mcp.handoff.submit` (só o
 *     ator-transporte), via {@see AuthorizesMcpMutation} como 1º statement.
 *   - **A1 proveniência:** `sig` inválida → erro (o "401"), timing-safe no service.
 *   - **idempotência:** mesmo `source_hash` → no-op (o "200" — não duplica).
 *   - **append-only:** revisão de slug 'applied' → nova version + anterior superseded.
 *   - **sem auto-merge** (ADR 0283): só INSERE pending. O merge é o 1-clique do [W].
 *
 * Pulsa o heartbeat (`mcp_ingest_heartbeat`, host `handoff-submit`) no caminho de
 * sucesso → a Forja sai de "transporte sem sinal" sozinha.
 *
 * @see Modules\TeamMcp\Services\HandoffIngestService
 * @see Modules\TeamMcp\Mcp\Tools\HandoffAckTool
 * @see memory/decisions/0283-handoff-loop-zero-paste.md
 */
class HandoffSubmitTool extends Tool
{
    use AuthorizesMcpMutation;

    protected string $name = 'handoff-submit';

    protected string $title = 'Submeter um handoff de design assinado (Cowork→Code)';

    protected string $description = 'Landing-pad HTTP do loop zero-paste (ADR 0283): recebe um handoff de design ASSINADO (HMAC) e cria pending em cowork_handoffs, sem SSH/commit. sig inválida é recusada (A1); conteúdo idêntico (source_hash) é no-op; revisão de slug aplicado vira nova versão + anterior superseded. Só INSERE pending — sem auto-merge (o merge é o 1-clique do [W]). Exige scope jana.mcp.handoff.submit (só o ator-transporte).';

    public function schema(JsonSchema $schema): array
    {
        return [
            'slug' => $schema->string()
                ->description('Identificador do handoff (ex: caixa-mobile-flutuante).')
                ->required(),
            'body_md' => $schema->string()
                ->description('Corpo do handoff (DESIGN — dado, não comando). O sig cobre ESTE corpo (CRLF→LF).')
                ->required(),
            'sig' => $schema->string()
                ->description('HMAC-SHA256(body_md, HANDOFF_SECRET). Inválida → recusado (A1).')
                ->required(),
            'files_json' => $schema->array()->items($schema->string())
                ->description('Arquivos que o handoff autoriza tocar (escopo do PR).'),
            'tela' => $schema->string()
                ->description('Tela alvo (ex: Atendimento/CaixaUnificada).'),
            'version' => $schema->integer()
                ->description('Aceito por compat; a versão é DERIVADA append-only (input ignorado).'),
            'created_by' => $schema->string()
                ->description('Autor do handoff (default CC).'),
            'audited_against' => $schema->string()
                ->description('SHA do main lido na auditoria (R1 ADR 0283).'),
        ];
    }

    public function handle(Request $request): Response
    {
        // A7: mutação — gate de escopo como PRIMEIRO statement.
        if ($deny = $this->authorizeMcpMutation($request, 'jana.mcp.handoff.submit')) {
            return $deny;
        }

        $secret = (string) config('teammcp.handoff_secret', '');
        if ($secret === '') {
            return Response::error('⛔ HANDOFF_SECRET não configurado no servidor — submit indisponível.');
        }

        $slug = trim((string) $request->get('slug', ''));
        $sig = (string) $request->get('sig', '');
        $body = (string) $request->get('body_md', '');
        if ($slug === '' || $sig === '' || $body === '') {
            return Response::error('❌ slug, body_md e sig são obrigatórios.');
        }

        $files = $request->get('files_json');

        $result = app(HandoffIngestService::class)->ingest([
            'slug'            => $slug,
            'body_md'         => $body,
            'sig'             => $sig,
            'files'           => is_array($files) ? $files : [],
            'tela'            => (string) $request->get('tela', ''),
            'created_by'      => (string) $request->get('created_by', 'CC'),
            'audited_against' => $request->get('audited_against'),
        ], $secret);

        if ($result['outcome'] === 'rejected') {
            // A1: sig inválida (ou slug vazio) — "401". Não insere, não pulsa heartbeat.
            $this->audit($request, $slug, 0, 'rejected');

            return Response::error($result['reason'] === 'slug'
                ? '❌ slug ausente — handoff recusado.'
                : '⛔ assinatura inválida — handoff recusado (A1). sig precisa bater HMAC-SHA256(body_md, HANDOFF_SECRET).');
        }

        // Sucesso (created/revised/no_op): transporte vivo → pulsa o heartbeat.
        $this->bumpHeartbeat();
        $this->audit($request, $slug, $result['version'], $result['outcome']);

        $payload = [
            'ok'      => true,
            'slug'    => $slug,
            'version' => $result['version'],
            'outcome' => $result['outcome'], // created | revised | no_op
            'hint'    => $result['outcome'] === 'no_op'
                ? 'idempotente: conteúdo idêntico já estava pending — nada duplicado.'
                : 'pending criado — aparece na Forja e no handoff-pending. O merge é o 1-clique do [W] (sem auto-merge).',
        ];

        return Response::text((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** Pulsa o heartbeat do ingest (host marker) — best-effort, não trava a resposta. */
    private function bumpHeartbeat(): void
    {
        try {
            $hb = McpIngestHeartbeat::firstOrNew(['host' => 'handoff-submit']);
            $hb->last_ingest_at = now();
            $hb->msgs_acc = (int) ($hb->msgs_acc ?? 0) + 1;
            $hb->save();
        } catch (Throwable) {
            // best-effort — o heartbeat é sinal, não bloqueio.
        }
    }

    /** Audit best-effort (slug/outcome) — espelha HandoffAckTool::audit. */
    private function audit(Request $request, string $slug, int $version, string $outcome): void
    {
        try {
            $user = $request->user();
            McpAuditLog::registrar([
                'user_id'          => $user !== null ? (int) $user->getAuthIdentifier() : 0,
                'endpoint'         => 'tools/call',
                'tool_or_resource' => 'handoff-submit',
                'status'           => $outcome === 'rejected' ? 'denied' : 'ok',
                'payload_summary'  => [
                    'slug'    => $slug,
                    'version' => $version,
                    'outcome' => $outcome,
                ],
            ]);
        } catch (Throwable) {
            // best-effort
        }
    }
}
