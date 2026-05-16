<?php

namespace Modules\TeamMcp\Http\Controllers\Mcp;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Modules\Jana\Services\Mcp\IndexarMemoryGitParaDb;
use Modules\Jana\Services\Privacy\PiiRedactor;
use Modules\Jana\Services\TaskRegistry\GitTaskLinkerService;
use Modules\Jana\Services\TaskRegistry\TaskParserService;

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

        // Roteamento por evento GitHub: push (default), pull_request (PR sync)
        $githubEvent = (string) $request->header('X-GitHub-Event', 'push');

        // ADR 0070 — pull_request event: linka task ↔ PR + status auto
        if ($githubEvent === 'pull_request') {
            return $this->handlePullRequest($request);
        }

        // GitHub envia ref + commits no body — só processa push em main
        $ref = $request->input('ref');
        if ($ref !== null && ! in_array($ref, ['refs/heads/main', 'refs/heads/master'], true)) {
            // ADR 0070: ainda processamos linkagem de tasks de branches feature
            $gitLinks = $this->processarGitLinks($request);
            return response()->json([
                'ok'        => true,
                'skipped'   => 'sync_memory',
                'reason'    => "ref=$ref (só main pra sync memory)",
                'git_links' => $gitLinks,
            ]);
        }

        // Sincroniza filesystem com origin/main antes de indexar.
        // Sem isso, IndexarMemoryGitParaDb indexa estado parado do disco.
        $gitInfo = $this->sincronizarComOrigin($request);

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
            // D7 LGPD Wave 15 — redact PII em mensagens de erro (paths podem conter
            // emails/CPFs de arquivos memory/* indexados). Resposta JSON também redacted.
            $redactor = app(PiiRedactor::class);
            Log::channel('copiloto-ai')->error('SyncMemoryWebhook: sync falhou', [
                'error' => $redactor->redact($e->getMessage()),
            ]);
            return response()->json(['error' => 'Sync failed', 'message' => $redactor->redact($e->getMessage())], 500);
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
                // D7 LGPD Wave 15 — redact PII (SPEC.md pode citar contatos).
                $redactor = app(PiiRedactor::class);
                Log::channel('copiloto-ai')->error('SyncMemoryWebhook: tasks sync falhou', [
                    'error' => $redactor->redact($e->getMessage()),
                ]);
                $tasksStats = ['synced' => false, 'error' => $redactor->redact($e->getMessage())];
            }
        }

        // ADR 0070 — bidirectional git sync (todo push)
        $gitLinks = $this->processarGitLinks($request);

        return response()->json([
            'ok'         => true,
            'git'        => $gitInfo,
            'stats'      => $stats,
            'tasks_sync' => $tasksStats,
            'git_links'  => $gitLinks,
        ]);
    }

    /**
     * ADR 0070 — handler de pull_request event (opened/synchronize/closed/merged).
     */
    protected function handlePullRequest(Request $request): JsonResponse
    {
        try {
            $stats = app(GitTaskLinkerService::class)->handlePullRequestEvent($request->all());
            return response()->json([
                'ok'        => true,
                'event'     => 'pull_request',
                'git_links' => $stats,
            ]);
        } catch (\Throwable $e) {
            // D7 LGPD Wave 15 — redact PII (PR body pode citar emails commit authors).
            $redactor = app(PiiRedactor::class);
            Log::channel('copiloto-ai')->error('SyncMemoryWebhook PR handler falhou', [
                'error' => $redactor->redact($e->getMessage()),
            ]);
            return response()->json(['error' => 'PR handler failed', 'message' => $redactor->redact($e->getMessage())], 500);
        }
    }

    /**
     * ADR 0070 — extrai refs de tasks dos commits e cria mcp_git_links.
     */
    protected function processarGitLinks(Request $request): ?array
    {
        try {
            return app(GitTaskLinkerService::class)->handlePushEvent($request->all());
        } catch (\Throwable $e) {
            // D7 LGPD Wave 15 — redact PII em git error messages.
            $redactor = app(PiiRedactor::class);
            Log::channel('copiloto-ai')->error('SyncMemoryWebhook git links falhou', [
                'error' => $redactor->redact($e->getMessage()),
            ]);
            return ['error' => $redactor->redact($e->getMessage())];
        }
    }

    /**
     * Faz `git fetch + reset --hard origin/main` no filesystem antes de indexar.
     *
     * Pula o reset se o push tocou arquivos que exigem deploy manual
     * (composer.lock, migrations, package.json, build assets) — nesse caso
     * retorna `pulled: false, reason: needs_manual_deploy` e ainda assim
     * deixa a indexação rodar sobre o filesystem atual.
     */
    private function sincronizarComOrigin(Request $request): array
    {
        if ($this->pushExigeDeployManual($request)) {
            return [
                'pulled' => false,
                'reason' => 'needs_manual_deploy',
                'head'   => $this->gitHead(),
            ];
        }

        $repo = base_path();

        $fetch = Process::path($repo)->timeout(30)->run('git fetch origin main');
        if (! $fetch->successful()) {
            // D7 LGPD Wave 15 — git stderr pode conter caminhos com nomes de autores.
            $redactor = app(PiiRedactor::class);
            Log::channel('copiloto-ai')->error('SyncMemoryWebhook: git fetch falhou', [
                'stderr' => $redactor->redact($fetch->errorOutput()),
            ]);
            return [
                'pulled' => false,
                'reason' => 'git_fetch_failed',
                'head'   => $this->gitHead(),
            ];
        }

        $reset = Process::path($repo)->timeout(15)->run('git reset --hard origin/main');
        if (! $reset->successful()) {
            // D7 LGPD Wave 15 — git stderr pode conter PII via paths/author.
            $redactor = app(PiiRedactor::class);
            Log::channel('copiloto-ai')->error('SyncMemoryWebhook: git reset falhou', [
                'stderr' => $redactor->redact($reset->errorOutput()),
            ]);
            return [
                'pulled' => false,
                'reason' => 'git_reset_failed',
                'head'   => $this->gitHead(),
            ];
        }

        return [
            'pulled' => true,
            'head'   => $this->gitHead(),
        ];
    }

    private function gitHead(): ?string
    {
        $r = Process::path(base_path())->timeout(5)->run('git rev-parse --short HEAD');

        return $r->successful() ? trim($r->output()) : null;
    }

    /**
     * Detecta paths que precisam de composer install / migrate / build pra
     * não desnudar produção. Se algum push tem esses paths, mantemos o
     * filesystem na versão anterior até alguém deployar manualmente.
     */
    private function pushExigeDeployManual(Request $request): bool
    {
        $padroes = [
            '#^composer\.lock$#',
            '#^composer\.json$#',
            '#^package\.json$#',
            '#^package-lock\.json$#',
            '#^bun\.lockb?$#',
            '#^vite\.config\.(js|ts)$#',
            '#^database/migrations/#',
            '#/Database/Migrations/#',
            '#^public/build/#',
        ];

        foreach ($this->pathsTocadosNoPush($request) as $path) {
            foreach ($padroes as $regex) {
                if (preg_match($regex, $path)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return iterable<string>
     */
    private function pathsTocadosNoPush(Request $request): iterable
    {
        $commits = $request->input('commits', []);
        $headCommit = $request->input('head_commit');
        if ($headCommit) {
            $commits[] = $headCommit;
        }

        foreach ($commits as $commit) {
            foreach (['added', 'modified', 'removed'] as $chave) {
                foreach ((array) ($commit[$chave] ?? []) as $path) {
                    yield $path;
                }
            }
        }
    }

    /**
     * Verifica se algum commit do push tocou em memory/requisitos/ * /SPEC.md.
     */
    private function specMdModificada(Request $request): bool
    {
        foreach ($this->pathsTocadosNoPush($request) as $path) {
            if (preg_match('#^memory/requisitos/[^/]+/SPEC\.md$#', $path)) {
                return true;
            }
        }

        return false;
    }
}
