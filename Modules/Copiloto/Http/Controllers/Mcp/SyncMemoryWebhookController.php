<?php

namespace Modules\Copiloto\Http\Controllers\Mcp;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Copiloto\Services\Mcp\IndexarMemoryGitParaDb;

/**
 * MEM-MCP-1.a (ADR 0053) — Webhook GitHub que sincroniza memory/ → DB.
 *
 * Endpoint: POST /api/mcp/sync-memory
 * Auth: header X-MCP-Sync-Token (env COPILOTO_MCP_SYNC_TOKEN)
 *
 * GitHub Settings → Webhooks → Add webhook
 *   URL: https://oimpresso.com/api/mcp/sync-memory
 *   Content type: application/json
 *   Custom header: X-MCP-Sync-Token: <token>
 *   Events: push (apenas main)
 *
 * Fallback: cron `mcp:sync-memory --reason=cron` 5min se webhook falhar.
 */
class SyncMemoryWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        // Auth via shared secret no header
        $expected = (string) config('copiloto.mcp.sync_webhook_token', env('COPILOTO_MCP_SYNC_TOKEN'));
        $received = (string) $request->header('X-MCP-Sync-Token', '');

        if ($expected === '' || ! hash_equals($expected, $received)) {
            Log::channel('copiloto-ai')->warning('SyncMemoryWebhook: token inválido', [
                'ip' => $request->ip(),
            ]);
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // GitHub envia ref + commits no body — só processa push em main
        $ref = $request->input('ref');
        if ($ref !== null && $ref !== 'refs/heads/main') {
            return response()->json(['skipped' => true, 'reason' => "ref=$ref (só main)"]);
        }

        // Roda em foreground (job não-async pra retornar 200 rápido com stats)
        // Se ficar lento, pode virar dispatch em queue
        $service = new IndexarMemoryGitParaDb(
            repoBasePath: base_path(),
            reason: 'webhook',
            userId: null,
        );

        try {
            $stats = $service->run();
        } catch (\Throwable $e) {
            Log::channel('copiloto-ai')->error('SyncMemoryWebhook: sync falhou', [
                'error' => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Sync failed', 'message' => $e->getMessage()], 500);
        }

        return response()->json([
            'ok'    => true,
            'stats' => $stats,
        ]);
    }
}
