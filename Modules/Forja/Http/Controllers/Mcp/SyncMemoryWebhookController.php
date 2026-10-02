<?php

namespace Modules\Forja\Http\Controllers\Mcp;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Modules\Jana\Services\Mcp\IndexarMemoryGitParaDb;
use App\Support\Privacy\PiiRedactor;
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

        // DeployDriftChecker (ADR 0216): grava o SHA de main a cada push pro
        // governance:audit detectar deploy atrasado (o "1302-commits cego").
        // ARQUIVO (não cache): container roda CACHE_DRIVER=array (per-process) — cache
        // do webhook não cruzaria pro processo do audit. Storage é compartilhado.
        $after = (string) $request->input('after', '');
        if (preg_match('/^[0-9a-f]{7,40}$/i', $after)) {
            $shaFile = \Modules\Governance\Services\Checkers\DeployDriftChecker::shaFilePath();
            @mkdir(dirname($shaFile), 0775, true);
            @file_put_contents($shaFile, $after);
        }

        // Atualiza SÓ memory/ com o origin/main antes de indexar — o código não se
        // move aqui (vem pelo deploy.yml). Sem isso, IndexarMemoryGitParaDb indexa
        // estado parado do disco.
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
     * Atualiza SÓ a pasta `memory/` com o `origin/main` antes de indexar.
     *
     * Até 2026-10-02 este método fazia reset do working tree inteiro para o topo
     * do main, e isso publicava código PHP em produção ~3 s depois de cada merge,
     * sem autoload, OPcache nem bundles — o caminho que derrubou produção por
     * 2h31 em 2026-05-28 (ADR 0216). Código agora chega só pelo deploy.yml
     * (ADR 0269); o webhook continua sendo o único caminho de doc de memória,
     * porque o deploy.yml ignora pushes em `memory/**`. Decisão [W] 2026-10-02,
     * opção A de memory/decisions/proposals/webhook-sync-memory-sem-reset-do-codigo.md.
     *
     * `git restore --source --staged --worktree` deixa `memory/` igual ao
     * origin/main, inclusive APAGANDO doc que saiu do main — o que um checkout
     * simples não faz. O HEAD não se move: o próximo deploy.yml (reset no SHA
     * do run) volta a alinhar índice e working tree.
     */
    private function sincronizarComOrigin(Request $request): array
    {
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
                'scope'  => 'memory',
                'reason' => 'git_fetch_failed',
                'head'   => $this->gitHead(),
            ];
        }

        $restore = Process::path($repo)->timeout(30)
            ->run(['git', 'restore', '--source=origin/main', '--staged', '--worktree', '--', 'memory']);
        if (! $restore->successful()) {
            // D7 LGPD Wave 15 — git stderr pode conter PII via paths/author.
            $redactor = app(PiiRedactor::class);
            Log::channel('copiloto-ai')->error('SyncMemoryWebhook: git restore de memory/ falhou', [
                'stderr' => $redactor->redact($restore->errorOutput()),
            ]);
            return [
                'pulled' => false,
                'scope'  => 'memory',
                'reason' => 'git_restore_failed',
                'head'   => $this->gitHead(),
            ];
        }

        return [
            'pulled' => true,
            'scope'  => 'memory',
            'head'   => $this->gitHead(),
        ];
    }

    private function gitHead(): ?string
    {
        $r = Process::path(base_path())->timeout(5)->run('git rev-parse --short HEAD');

        return $r->successful() ? trim($r->output()) : null;
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
