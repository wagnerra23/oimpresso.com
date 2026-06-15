<?php

declare(strict_types=1);

namespace Modules\Jana\Services\TaskRegistry;

use App\Util\OtelHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Entities\Mcp\McpTask;
use Modules\Jana\Entities\Mcp\McpTaskComment;
use Modules\Jana\Entities\Mcp\McpTaskEvent;

/**
 * ADR 0070 — Bidirectional git sync.
 *
 * Detecta refs a tasks em commit messages, branch names e PR titles/bodies,
 * cria mcp_git_links rows e (opcionalmente) atualiza status auto das tasks.
 *
 * Padrões detectados (case-insensitive):
 *
 *   "refs <KEY>-<N>"        → action=refs, sem mudança de status
 *   "fixes/closes/resolves
 *    <KEY>-<N>"             → action=fixes, status auto pra review (ou done se branch=main)
 *   branch "<KEY>-<N>-slug" → action=branch, sem mudança de status (mas linka)
 *   PR opened mencionando   → action=pr_opened, status auto pra review
 *   PR merged em default    → action=pr_merged, status auto pra done
 *
 * KEY = identifier humano Linear-style (ex: COPI, NFSE, FIN). Case-sensitive.
 *
 * Idempotente: chama com mesmo commit_sha + task_id + action = no-op.
 */
class GitTaskLinkerService
{
    /** Pattern: aceita 2 convenções de ref a tasks em commit messages:
     *   1. VERB explícito (GitHub padrão): "Closes COPI-1", "Refs: COPI-2", "fixes COPI-42",
     *      "resolves: INFRA-7", "closes US-NFE-061" — prefix `US-` é opcional.
     *   2. BRACKET parentético/colchete (convenção real oimpresso ~99% dos commits):
     *      "(US-WA-042)", "[US-NFE-061]", "(COPI-42)" — sem verb, inferido pelo contexto
     *      (branch=main → closes/done; branch≠main → refs).
     *
     *  Captura: $1=verb (null quando bracket), $2=KEY (ex: WA, NFE, COPI), $3=NUM.
     *  Prefix `US-` é stripado opcional (formato canônico oimpresso é `US-<KEY>-<NUM>`).
     *  PR numbers como `(#707)` NÃO casam (exigem letras+hífen+num). */
    public const REF_PATTERN = '/(?:(refs|fixes|closes|resolves|fix|close|resolve):?\s+|[\(\[])(?:US-)?([A-Z]{2,8})-(\d+)(?:[\)\]])?/i';

    /** Pattern em branch name: <KEY>-<N>-anything */
    public const BRANCH_PATTERN = '/^([A-Z]{2,8})-(\d+)/i';

    /**
     * Processa o payload de um push event do GitHub e linka commits a tasks.
     *
     * @param  array{ref?:string,commits?:array,head_commit?:array,repository?:array} $payload
     * @return array{links_created:int,tasks_updated:int,errors:list<string>}
     */
    public function handlePushEvent(array $payload): array
    {
        // D9.a (Wave 18 SATURATION) — span webhook push event; ref+repo essenciais.
        return OtelHelper::span('jana.git_linker.push_event', [
            'repo' => $payload['repository']['full_name'] ?? null,
            'ref' => $payload['ref'] ?? null,
            'commits_count' => count((array) ($payload['commits'] ?? [])),
        ], fn () => $this->handlePushEventInternal($payload));
    }

    private function handlePushEventInternal(array $payload): array
    {
        $linksCreated = 0;
        $tasksUpdated = 0;
        $errors = [];

        $ref = (string) ($payload['ref'] ?? '');
        $isMain = in_array($ref, ['refs/heads/main', 'refs/heads/master'], true);
        $branch = ltrim(preg_replace('#^refs/heads/#', '', $ref) ?? '', '/');
        $repoFullName = $payload['repository']['full_name'] ?? null;

        $commits = (array) ($payload['commits'] ?? []);
        if (! empty($payload['head_commit']) && ! in_array($payload['head_commit'], $commits, true)) {
            $commits[] = $payload['head_commit'];
        }

        foreach ($commits as $commit) {
            $sha = (string) ($commit['id'] ?? '');
            $msg = (string) ($commit['message'] ?? '');
            $author = (string) ($commit['author']['username'] ?? $commit['author']['name'] ?? '');
            $occurredAt = isset($commit['timestamp']) ? Carbon::parse($commit['timestamp']) : now();

            $refs = $this->extractRefsFromMessage($msg);
            foreach ($refs as $ref) {
                try {
                    $task = $this->findTaskByKey($ref['key'], $ref['number']);
                    if (! $task) {
                        continue;
                    }

                    $linkAction = $this->inferAction($ref['verb'], $isMain);
                    $created = $this->createGitLink([
                        'task_id'        => $task->task_id,
                        'action'         => $linkAction,
                        'repo_full_name' => $repoFullName,
                        'commit_sha'     => $sha,
                        'pr_number'      => null,
                        'branch'         => $branch ?: null,
                        'author_username'=> $author ?: null,
                        'message'        => mb_substr($msg, 0, 2000),
                        'occurred_at'    => $occurredAt,
                    ]);

                    if ($created) {
                        $linksCreated++;

                        // Comment auto
                        McpTaskComment::create([
                            'task_id' => $task->task_id,
                            'author'  => $author ?: 'github-bot',
                            'body'    => sprintf(
                                "🔗 commit `%s` (%s) — %s",
                                substr($sha, 0, 7),
                                $branch ?: 'unknown',
                                mb_substr(strtok($msg, "\n"), 0, 200)
                            ),
                        ]);

                        // Status auto se for fixes/closes/resolves
                        // NOTA FSM (ADR 0070): este write DIRETO é um caminho de transição
                        // de SISTEMA (PR-merge → done/review) intencionalmente FORA do guard
                        // FSM de applyLockedUpdate — pode forçar done a partir de não-review e
                        // passar pelo guard quebraria o git linking. Follow-up: modelar como
                        // transição de sistema explícita em vez de bypass.
                        if ($linkAction === 'fixes') {
                            $newStatus = $isMain ? 'done' : 'review';
                            if (in_array($task->status, ['todo', 'doing', 'review', 'blocked'], true) && $task->status !== $newStatus) {
                                $oldStatus = $task->status;
                                $task->status = $newStatus;
                                if ($newStatus === 'done' && ! $task->completed_at) {
                                    $task->completed_at = $occurredAt;
                                }
                                if ($newStatus === 'review' && ! $task->started_at) {
                                    $task->started_at = $occurredAt;
                                }
                                $task->save();

                                McpTaskEvent::log(
                                    taskId: $task->task_id,
                                    eventType: 'status_changed',
                                    from: $oldStatus,
                                    to: $newStatus,
                                    author: $author ?: 'github-bot',
                                    note: "Auto via git: {$ref['verb']} {$ref['key']}-{$ref['number']} (commit " . substr($sha, 0, 7) . ')',
                                );
                                $tasksUpdated++;
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    $errors[] = "{$ref['key']}-{$ref['number']}: " . $e->getMessage();
                    Log::channel('copiloto-ai')->error('GitTaskLinker push commit error', [
                        'ref' => $ref,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Linka pelo nome da branch (1× por commit)
            if ($branch && ! $isMain) {
                if (preg_match(self::BRANCH_PATTERN, $branch, $bm)) {
                    $task = $this->findTaskByKey($bm[1], (int) $bm[2]);
                    if ($task) {
                        $this->createGitLink([
                            'task_id'         => $task->task_id,
                            'action'          => 'branch',
                            'repo_full_name'  => $repoFullName,
                            'commit_sha'      => $sha,
                            'pr_number'       => null,
                            'branch'          => $branch,
                            'author_username' => $author ?: null,
                            'message'         => null,
                            'occurred_at'     => $occurredAt,
                        ]);
                    }
                }
            }
        }

        return [
            'links_created' => $linksCreated,
            'tasks_updated' => $tasksUpdated,
            'errors'        => $errors,
        ];
    }

    /**
     * Processa pull_request event (opened/synchronize/closed).
     *
     * @param  array $payload
     * @return array{links_created:int,tasks_updated:int}
     */
    public function handlePullRequestEvent(array $payload): array
    {
        $action = (string) ($payload['action'] ?? '');
        $pr = $payload['pull_request'] ?? [];
        $repoFullName = $payload['repository']['full_name'] ?? null;
        $prNumber = (int) ($pr['number'] ?? 0);
        $merged = (bool) ($pr['merged'] ?? false);
        $branch = (string) ($pr['head']['ref'] ?? '');
        $title = (string) ($pr['title'] ?? '');
        $body = (string) ($pr['body'] ?? '');
        $author = (string) ($pr['user']['login'] ?? '');
        $occurredAt = isset($pr['updated_at']) ? Carbon::parse($pr['updated_at']) : now();

        $linksCreated = 0;
        $tasksUpdated = 0;

        // Extrai refs do título + body + branch
        $refs = array_merge(
            $this->extractRefsFromMessage($title),
            $this->extractRefsFromMessage($body),
        );
        if (preg_match(self::BRANCH_PATTERN, $branch, $bm)) {
            $refs[] = ['verb' => 'refs', 'key' => $bm[1], 'number' => (int) $bm[2]];
        }
        $refs = array_values(array_unique(
            array_map(fn ($r) => $r['key'] . '-' . $r['number'], $refs)
        ));

        $linkAction = match ($action) {
            'opened', 'reopened', 'synchronize' => 'pr_opened',
            'closed' => $merged ? 'pr_merged' : 'pr_closed',
            'review_requested' => 'pr_reviewed',
            default => 'pr_opened',
        };

        foreach ($refs as $key) {
            [$projKey, $num] = explode('-', $key);
            $task = $this->findTaskByKey($projKey, (int) $num);
            if (! $task) continue;

            $created = $this->createGitLink([
                'task_id'         => $task->task_id,
                'action'          => $linkAction,
                'repo_full_name'  => $repoFullName,
                'commit_sha'      => null,
                'pr_number'       => $prNumber,
                'branch'          => $branch,
                'author_username' => $author,
                'message'         => mb_substr($title, 0, 2000),
                'occurred_at'     => $occurredAt,
            ]);
            if ($created) $linksCreated++;

            // Status auto:
            //   pr_opened/synchronize → review (se task estava em todo/doing)
            //   pr_merged             → done   (se task ainda aberta)
            if ($linkAction === 'pr_opened' && in_array($task->status, ['todo', 'doing'], true)) {
                $task->status = 'review';
                if (! $task->started_at) $task->started_at = $occurredAt;
                $task->save();
                McpTaskEvent::log(
                    taskId: $task->task_id,
                    eventType: 'status_changed',
                    from: 'todo|doing',
                    to: 'review',
                    author: $author ?: 'github-bot',
                    note: "Auto via PR #{$prNumber} {$action}",
                );
                $tasksUpdated++;
            } elseif ($linkAction === 'pr_merged' && ! $task->isClosed()) {
                $oldStatus = $task->status;
                $task->status = 'done';
                $task->completed_at = $occurredAt;
                $task->save();
                McpTaskEvent::log(
                    taskId: $task->task_id,
                    eventType: 'status_changed',
                    from: $oldStatus,
                    to: 'done',
                    author: $author ?: 'github-bot',
                    note: "Auto via PR #{$prNumber} merged",
                );
                $tasksUpdated++;
            }
        }

        return ['links_created' => $linksCreated, 'tasks_updated' => $tasksUpdated];
    }

    /**
     * @return list<array{verb:string,key:string,number:int}>
     */
    public function extractRefsFromMessage(string $msg): array
    {
        if ($msg === '') return [];

        preg_match_all(self::REF_PATTERN, $msg, $matches, PREG_SET_ORDER);
        $refs = [];
        foreach ($matches as $m) {
            // $m[1] vazio quando match veio do padrão bracket parentético/colchete
            // (convenção real oimpresso "(US-WA-042)") — verb inferido pelo contexto em inferAction()
            $verb = strtolower($m[1] ?? '');
            $verb = match ($verb) {
                'fix' => 'fixes',
                'close' => 'closes',
                'resolve' => 'resolves',
                '' => 'bracket', // padrão parentético/colchete
                default => $verb,
            };
            $refs[] = [
                'verb'   => $verb,
                'key'    => strtoupper($m[2]),
                'number' => (int) $m[3],
            ];
        }
        return $refs;
    }

    /**
     * Decide action canônica a partir do verb extraído.
     *
     * - `fixes`/`closes`/`resolves` (verb explícito) → 'fixes' (override forte do dev)
     * - `bracket` (convenção real oimpresso "(US-XXX)") → 'fixes' também,
     *   delegando pro caller decidir status final via branch (main=done, feature=review).
     *   Razão: ~99% dos commits oimpresso usam parentético; sem isso, auto-close NUNCA dispara.
     * - `refs` puro (verb explícito leve) → 'refs' (apenas linka, sem mudar status)
     */
    protected function inferAction(string $verb, bool $isMain): string
    {
        if (in_array($verb, ['fixes', 'closes', 'resolves', 'bracket'], true)) {
            return 'fixes';
        }
        return 'refs';
    }

    protected function findTaskByKey(string $projectKey, int $number): ?McpTask
    {
        $identifier = strtoupper($projectKey) . '-' . $number;

        // Prioridade 1: identifier humano
        $task = McpTask::where('identifier', $identifier)->first();
        if ($task) return $task;

        // Prioridade 2: task_id legacy (US-XXX-NNN onde XXX === projectKey)
        $taskIdGuess = 'US-' . strtoupper($projectKey) . '-' . str_pad((string) $number, 3, '0', STR_PAD_LEFT);
        return McpTask::where('task_id', $taskIdGuess)->first();
    }

    /**
     * Insere row em mcp_git_links com idempotência (mesmo commit_sha + task + action = no-op).
     */
    protected function createGitLink(array $data): bool
    {
        $exists = DB::table('mcp_git_links')
            ->where('task_id', $data['task_id'])
            ->where('action', $data['action'])
            ->when($data['commit_sha'], fn ($q) => $q->where('commit_sha', $data['commit_sha']))
            ->when($data['pr_number'], fn ($q) => $q->where('pr_number', $data['pr_number']))
            ->exists();

        if ($exists) return false;

        DB::table('mcp_git_links')->insert(array_merge($data, [
            'created_at' => now(),
            'updated_at' => now(),
        ]));
        return true;
    }
}
