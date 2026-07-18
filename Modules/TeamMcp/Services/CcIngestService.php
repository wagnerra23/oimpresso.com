<?php

declare(strict_types=1);

namespace Modules\TeamMcp\Services;

use App\Util\OtelHelper;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Jana\Entities\Mcp\McpCcBlob;
use Modules\Jana\Entities\Mcp\McpCcMessage;
use Modules\Jana\Entities\Mcp\McpCcSession;

/**
 * CcIngestService — Wave 18 D4 SATURATION (2026-05-16).
 *
 * Extrai lógica de upsert sessions+messages antes embutida em
 * `Modules\TeamMcp\Http\Controllers\Mcp\CcIngestController`.
 *
 * Service pura (sem Request/Response) — recebe payload já validado e devolve
 * contadores. Controller fica thin (auth + validate + chamar service + log
 * estruturado com PiiRedactor).
 *
 * **Idempotência:** msg_uuid UNIQUE — re-envio ignora mensagens já gravadas.
 * Watcher local mantém `offset.json`, mas dedup DB é a real garantia.
 *
 * **Tier 0 segredo / LGPD ({@see ADR 0081}):** dados de mensagem podem conter
 * PII (email, CPF). Service NÃO loga conteúdo — quem precisa logar redactaria
 * via `PiiRedactor` antes (Controller responsabilidade).
 *
 * **OTel ({@see ADR 0155}):** spans `teammcp.cc.ingest_session`
 * + `teammcp.cc.ingest_messages` separam latência session-upsert vs
 * batch-insert (debug performance ingest com payload grande).
 *
 * @see Modules\TeamMcp\Http\Controllers\Mcp\CcIngestController (caller)
 * @see Modules\Jana\Entities\Mcp\McpCcSession
 */
class CcIngestService
{
    /**
     * Upsert session a partir de payload validado. Idempotente em `session_uuid`.
     *
     * @param  array<string, mixed>  $sessionData  Payload validado em CcIngestRequest.
     */
    public function upsertSession(array $sessionData, int $userId, ?int $businessId): McpCcSession
    {
        return OtelHelper::spanBiz('teammcp.cc.ingest_session', function () use ($sessionData, $userId, $businessId) {
            return McpCcSession::updateOrCreate(
                ['session_uuid' => $sessionData['uuid']],
                [
                    'user_id'      => $userId,
                    'business_id'  => $businessId,
                    'project_path' => $sessionData['project_path'] ?? '',
                    'git_branch'   => $sessionData['git_branch'] ?? null,
                    'cc_version'   => $sessionData['cc_version'] ?? null,
                    'entrypoint'   => $sessionData['entrypoint'] ?? null,
                    'started_at'   => isset($sessionData['started_at']) ? Carbon::parse($sessionData['started_at']) : null,
                    'ended_at'     => isset($sessionData['ended_at']) ? Carbon::parse($sessionData['ended_at']) : null,
                ]
            );
        }, ['module' => 'TeamMcp', 'session_uuid' => $sessionData['uuid'] ?? '?']);
    }

    /**
     * Insere batch de mensagens dentro de transação. Retorna [inserted, duplicated].
     *
     * @param  array<int, array<string, mixed>>  $messages
     * @return array{0:int, 1:int}
     */
    public function ingestMessages(array $messages, McpCcSession $session, int $userId): array
    {
        return OtelHelper::spanBiz('teammcp.cc.ingest_messages', function () use ($messages, $session, $userId) {
            return DB::transaction(function () use ($messages, $session, $userId) {
                $ins = 0;
                $dup = 0;
                foreach ($messages as $msg) {
                    if ($this->upsertMessage($msg, $session, $userId)) {
                        $ins++;
                    } else {
                        $dup++;
                    }
                }
                return [$ins, $dup];
            });
        }, ['module' => 'TeamMcp', 'batch_size' => count($messages), 'session_id' => $session->id]);
    }

    /**
     * Atualiza counters agregados da session após batch ingest.
     */
    public function recalcSessionCounters(McpCcSession $session): McpCcSession
    {
        $session->update([
            'total_messages' => $session->messages()->count(),
            'total_tokens'   => (int) $session->messages()->sum('tokens_in')
                + (int) $session->messages()->sum('tokens_out'),
            'total_cost_usd' => (float) $session->messages()->sum('cost_usd'),
        ]);

        return $session->refresh();
    }

    /**
     * Insert ou ignore (msg_uuid UNIQUE). Retorna true se inseriu, false se já existia.
     *
     * @param  array<string, mixed>  $msg
     */
    protected function upsertMessage(array $msg, McpCcSession $session, int $userId): bool
    {
        if (McpCcMessage::where('msg_uuid', $msg['uuid'])->exists()) {
            return false;
        }

        $contentText = $msg['content_text'] ?? null;
        $blobId = null;

        if ($contentText !== null && strlen($contentText) > 4096) {
            [$blob, ] = McpCcBlob::deduplicar(
                $contentText,
                ($msg['type'] ?? '') === 'tool_result' ? 'stdout' : 'json',
            );
            $blobId = $blob->id;
            $contentText = mb_substr($contentText, 0, 4000) . '... [→blob #' . $blob->id . ']';
        }

        McpCcMessage::create([
            'session_id'   => $session->id,
            'msg_uuid'     => $msg['uuid'],
            'parent_uuid'  => $msg['parent_uuid'] ?? null,
            'user_id'      => $userId,
            'business_id'  => $session->business_id,
            'msg_type'     => $msg['type'],
            'role'         => $msg['role'] ?? null,
            'tool_name'    => $msg['tool_name'] ?? null,
            'model'        => $msg['model'] ?? null,
            'content_text' => $contentText,
            'content_json' => $msg['content_json'] ?? null,
            'blob_id'      => $blobId,
            'tokens_in'    => $msg['tokens_in'] ?? null,
            'tokens_out'   => $msg['tokens_out'] ?? null,
            'cache_read'   => $msg['cache_read'] ?? null,
            'cache_write'  => $msg['cache_write'] ?? null,
            'cost_usd'     => $msg['cost_usd'] ?? null,
            'ts'           => isset($msg['ts']) ? Carbon::parse($msg['ts']) : now(),
        ]);

        return true;
    }
}
