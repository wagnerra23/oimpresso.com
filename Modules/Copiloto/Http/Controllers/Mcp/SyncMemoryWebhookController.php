<?php

namespace Modules\Copiloto\Http\Controllers\Mcp;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Copiloto\Services\Mcp\IndexarMemoryGitParaDb;
use Modules\Copiloto\Services\TaskRegistry\TaskParserService;

/**
 * MEM-MCP-1.a (ADR 0053) — Webhook GitHub que sincroniza memory/ → DB.
 * US-TR-004 — também dispara mcp:tasks:sync quando SPEC.md é modificada.
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
        // Auth via dois mecanismos:
        //  1) X-Hub-Signature-256 (GitHub padrão): HMAC-SHA256 do body com o token como secret
        //  2) X-MCP-Sync-Token (header direto): para testes manuais e chamadas não-GitHub
        $secret = (string) config('copiloto.mcp.sync_webhook_token', env('COPILOTO_MCP_SYNC_TOKEN'));

        if ($secret === '') {
            Log::channel('copiloto-ai')->warning('SyncMemoryWebhook: COPILOTO_MCP_SYNC_TOKEN não configurado');
            return response()->json(['error' => 'Misconfigured'], 500);
        }

        $githubSig = (string) $request->header('X-Hub-Signature-256', '');
        $directToken = (string) $request->header('X-MCP-Sync-Token', '');
        $authorized = false;

        if ($githubSig !== '') {
            $expected = 'sha256=' . hash_hmac('sha256', $request->getContent(), $secret);
            $authorized = hash_equals($expected, $githubSig);
        } elseif ($directToken !== '') {
            $authorized = hash_equals($secret, $directToken);
        }

        if (! $authorized) {
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

        // US-TR-004: dispara tasks sync se algum SPEC.md foi tocado no push
        $tasksStats = null;
        if ($this->specMdModificada($request)) {
            try {
                $relatorio = app(TaskParserService::class)->syncAll();
                $tasksStats = [
                    'synced'     => true,
                    'processadas' => $relatorio['tasks_processadas'],
                    'inseridas'  => $relatorio['inseridas'],
                    'atualizadas' => $relatorio['atualizadas'],
                    'canceladas' => $relatorio['canceladas'],
                ];
            } catch (\Throwable $e) {
                Log::channel('copiloto-ai')->error('SyncMemoryWebhook: tasks sync falhou', [
                    'error' => $e->getMessage(),
                ]);
                $tasksStats = ['synced' => false, 'error' => $e->getMessage()];
            }
        }

        return response()->json([
            'ok'         => true,
            'stats'      => $stats,
            'tasks_sync' => $tasksStats,
        ]);
    }

    /**
     * Verifica se algum commit do push tocou em memory/requisitos/ * /SPEC.md.
     */
    private function specMdModificada(Request $request): bool
    {
        $commits = $request->input('commits', []);
        if (empty($commits)) {
            // Push sem payload de commits detalhado — roda sync preventivo
            $headCommit = $request->input('head_commit');
            if ($headCommit) {
                $commits = [$headCommit];
            }
        }

        foreach ($commits as $commit) {
            foreach (['added', 'modified', 'removed'] as $chave) {
                foreach ((array) ($commit[$chave] ?? []) as $path) {
                    if (preg_match('#^memory/requisitos/[^/]+/SPEC\.md$#', $path)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
