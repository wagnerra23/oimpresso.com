<?php

namespace Modules\Copiloto\Http\Controllers\Mcp;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Copiloto\Entities\Mcp\McpCcBlob;
use Modules\Copiloto\Entities\Mcp\McpCcMessage;
use Modules\Copiloto\Entities\Mcp\McpCcSession;

/**
 * MEM-CC-1 — Endpoint POST /api/cc/ingest pra watcher local de cada dev
 * empurrar messages do `~/.claude/projects/<encoded>/<sessionId>.jsonl`.
 *
 * Auth: middleware mcp.auth (Bearer mcp_<token>) + RBAC `copiloto.cc.ingest.self`.
 *
 * Payload:
 *   POST /api/cc/ingest
 *   {
 *     "session": {
 *       "uuid": "abc-123",
 *       "project_path": "D:\\oimpresso.com",
 *       "git_branch": "main",
 *       "cc_version": "2.1.119",
 *       "entrypoint": "claude-desktop",
 *       "started_at": "2026-04-29T10:00:00Z",
 *       "ended_at": null
 *     },
 *     "messages": [
 *       {
 *         "uuid": "...",
 *         "parent_uuid": "...",
 *         "type": "user|assistant|tool_use|tool_result|hook|attachment",
 *         "role": "user|assistant|system",
 *         "tool_name": "Bash|Edit|...",
 *         "content_text": "texto plano (truncado a 4KB; resto vai blob)",
 *         "content_json": {...},
 *         "tokens_in": 100, "tokens_out": 200, ...
 *         "ts": "2026-04-29T10:00:00Z"
 *       }
 *     ]
 *   }
 *
 * Idempotência: msg_uuid UNIQUE — re-envio ignora mensagens já gravadas.
 * Watcher mantém `offset.json` local pra retry, mas dedup é a real garantia.
 */
class CcIngestController extends Controller
{
    public function ingest(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // RBAC — gate `copiloto.cc.ingest.self` (concedido por padrão a todos com mcp.use)
        if (method_exists($user, 'can') && ! $user->can('copiloto.cc.ingest.self')
            && ! $user->can('copiloto.mcp.use')) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'User não tem permission copiloto.cc.ingest.self',
            ], 403);
        }

        $payload = $request->validate([
            'session' => 'required|array',
            'session.uuid' => 'required|string|max:36',
            'session.project_path' => 'nullable|string|max:500',
            'session.git_branch' => 'nullable|string|max:150',
            'session.cc_version' => 'nullable|string|max:20',
            'session.entrypoint' => 'nullable|string|max:50',
            'session.started_at' => 'nullable|string',
            'session.ended_at' => 'nullable|string',
            'messages' => 'required|array',
            'messages.*.uuid' => 'required|string|max:36',
            'messages.*.type' => 'required|string|max:20',
        ]);

        try {
            $session = $this->upsertSession($payload['session'], (int) $user->id, $user->business_id ?? null);

            [$inserted, $duplicated] = DB::transaction(function () use ($payload, $session, $user) {
                $ins = 0; $dup = 0;
                foreach ($payload['messages'] as $msg) {
                    if ($this->upsertMessage($msg, $session, (int) $user->id)) {
                        $ins++;
                    } else {
                        $dup++;
                    }
                }
                return [$ins, $dup];
            });

            // Atualiza counters da session
            $session->update([
                'total_messages' => $session->messages()->count(),
                'total_tokens' => (int) $session->messages()->sum('tokens_in')
                    + (int) $session->messages()->sum('tokens_out'),
                'total_cost_usd' => (float) $session->messages()->sum('cost_usd'),
            ]);

            return response()->json([
                'ok' => true,
                'session_id' => $session->id,
                'session_uuid' => $session->session_uuid,
                'messages_inserted' => $inserted,
                'messages_duplicated' => $duplicated,
                'total_messages' => $session->total_messages,
            ]);
        } catch (\Throwable $e) {
            Log::channel('copiloto-ai')->error('CcIngest erro', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'session_uuid' => $payload['session']['uuid'] ?? '?',
            ]);
            return response()->json([
                'error' => 'Internal',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    protected function upsertSession(array $s, int $userId, ?int $businessId): McpCcSession
    {
        return McpCcSession::updateOrCreate(
            ['session_uuid' => $s['uuid']],
            [
                'user_id' => $userId,
                'business_id' => $businessId,
                'project_path' => $s['project_path'] ?? '',
                'git_branch' => $s['git_branch'] ?? null,
                'cc_version' => $s['cc_version'] ?? null,
                'entrypoint' => $s['entrypoint'] ?? null,
                'started_at' => isset($s['started_at']) ? Carbon::parse($s['started_at']) : null,
                'ended_at' => isset($s['ended_at']) ? Carbon::parse($s['ended_at']) : null,
            ]
        );
    }

    /**
     * Insert ou ignore (msg_uuid UNIQUE).
     * Retorna true se inseriu, false se já existia.
     */
    protected function upsertMessage(array $m, McpCcSession $session, int $userId): bool
    {
        if (McpCcMessage::where('msg_uuid', $m['uuid'])->exists()) {
            return false;
        }

        $contentText = $m['content_text'] ?? null;
        $blobId = null;

        // Conteúdo grande → blob deduplicado
        if ($contentText !== null && strlen($contentText) > 4096) {
            [$blob, ] = McpCcBlob::deduplicar(
                $contentText,
                $m['type'] === 'tool_result' ? 'stdout' : 'json',
            );
            $blobId = $blob->id;
            $contentText = mb_substr($contentText, 0, 4000) . '... [→blob #' . $blob->id . ']';
        }

        McpCcMessage::create([
            'session_id' => $session->id,
            'msg_uuid' => $m['uuid'],
            'parent_uuid' => $m['parent_uuid'] ?? null,
            'user_id' => $userId,
            'business_id' => $session->business_id,
            'msg_type' => $m['type'],
            'role' => $m['role'] ?? null,
            'tool_name' => $m['tool_name'] ?? null,
            'content_text' => $contentText,
            'content_json' => $m['content_json'] ?? null,
            'blob_id' => $blobId,
            'tokens_in' => $m['tokens_in'] ?? null,
            'tokens_out' => $m['tokens_out'] ?? null,
            'cache_read' => $m['cache_read'] ?? null,
            'cache_write' => $m['cache_write'] ?? null,
            'cost_usd' => $m['cost_usd'] ?? null,
            'ts' => isset($m['ts']) ? Carbon::parse($m['ts']) : now(),
        ]);

        return true;
    }
}
