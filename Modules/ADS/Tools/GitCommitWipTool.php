<?php

namespace Modules\ADS\Tools;

use Symfony\Component\Process\Process;
use Modules\ADS\Contracts\Tool;

/**
 * Tool de COMMIT em branch WIP isolada — NUNCA toca main, NUNCA push.
 *
 * Workflow:
 *   1. git checkout -b wip-decision-{N}-{slug} (ou usa existente)
 *   2. git add <paths> (whitelist)
 *   3. git commit -m "wip(decision-N): ..."
 *   4. STOP — Wagner manualmente revisa, faz push, merge se quiser
 *
 * Defesas:
 *   - Branch nome OBRIGATORIAMENTE começa com 'wip-' (regex)
 *   - SEM git push (ferramenta nem executa o comando)
 *   - SEM git reset --hard, --force, etc — whitelist hard de subcomandos
 *   - paths em git add passam por mesma whitelist do WriteFileTool
 */
class GitCommitWipTool implements Tool
{
    public function name(): string { return 'git_commit_wip'; }
    public function category(): string { return 'escrita'; }
    public function isReadOnly(): bool { return false; }

    /** Whitelist herdada do WriteFileTool — só commitamos arquivos nesses prefixos */
    private const ALLOWED_PATH_PREFIXES = [
        'Modules/',
        'resources/js/Pages/',
        'resources/js/Components/',
        'memory/',
        'lang/',
    ];

    public function description(): string
    {
        return 'Cria/usa branch WIP (wip-decision-N-slug), faz git add nos paths permitidos, '
             . 'git commit. NUNCA push, NUNCA reset, NUNCA toca main. Wagner aprova antes; '
             . 'depois revisa branch manualmente.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'decision_id' => ['type' => 'integer', 'description' => 'ID da decision (vai compor branch name)'],
                'slug'        => ['type' => 'string', 'description' => 'Slug curto pra branch (ex: lang-fix-pt-br)'],
                'paths'       => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Paths a serem adicionados (whitelist Modules/, resources/js/Pages/, memory/, lang/)'],
                'message'     => ['type' => 'string', 'description' => 'Mensagem do commit em PT-BR'],
            ],
            'required' => ['decision_id', 'slug', 'paths', 'message'],
        ];
    }

    public function execute(array $input): array
    {
        $decisionId = (int) ($input['decision_id'] ?? 0);
        $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower($input['slug'] ?? ''));
        $paths = $input['paths'] ?? [];
        $message = $input['message'] ?? '';

        if ($decisionId <= 0) return ['ok' => false, 'output' => null, 'error' => 'decision_id_required'];
        if (empty($slug))     return ['ok' => false, 'output' => null, 'error' => 'slug_required_alphanumeric_dash'];
        if (empty($paths))    return ['ok' => false, 'output' => null, 'error' => 'paths_required'];
        if (empty($message))  return ['ok' => false, 'output' => null, 'error' => 'message_required'];

        $branch = "wip-decision-{$decisionId}-{$slug}";
        if (! preg_match('/^wip-[a-z0-9\-]+$/', $branch)) {
            return ['ok' => false, 'output' => null, 'error' => 'invalid_branch_name'];
        }

        // Valida paths
        foreach ($paths as $path) {
            if (str_contains($path, '..')) {
                return ['ok' => false, 'output' => null, 'error' => "path_traversal_blocked: {$path}"];
            }
            if (str_starts_with($path, '/') || preg_match('/^[A-Z]:/i', $path)) {
                return ['ok' => false, 'output' => null, 'error' => "absolute_path_blocked: {$path}"];
            }
            $path = str_replace('\\', '/', $path);
            $ok = false;
            foreach (self::ALLOWED_PATH_PREFIXES as $prefix) {
                if (str_starts_with($path, $prefix)) { $ok = true; break; }
            }
            if (! $ok) {
                return ['ok' => false, 'output' => null, 'error' => "path_not_in_whitelist: {$path}"];
            }
        }

        try {
            // 1. Verifica se branch existe; senão cria
            $branchExists = $this->git(['rev-parse', '--verify', $branch], false);
            if (! $branchExists['ok']) {
                $r = $this->git(['checkout', '-b', $branch]);
                if (! $r['ok']) return ['ok' => false, 'output' => null, 'error' => 'cannot_create_branch: ' . $r['err']];
            } else {
                $r = $this->git(['checkout', $branch]);
                if (! $r['ok']) return ['ok' => false, 'output' => null, 'error' => 'cannot_checkout_branch: ' . $r['err']];
            }

            // 2. git add nos paths
            foreach ($paths as $path) {
                $r = $this->git(['add', '--', $path]);
                if (! $r['ok']) {
                    return ['ok' => false, 'output' => null, 'error' => "git_add_failed: {$path} " . $r['err']];
                }
            }

            // 3. Verifica se há mudanças staged
            $diffStaged = $this->git(['diff', '--cached', '--name-only']);
            if (empty(trim($diffStaged['out']))) {
                return ['ok' => false, 'output' => null, 'error' => 'nothing_to_commit'];
            }

            // 4. Commit (com email/name fixos pra distinguir)
            $r = $this->git([
                '-c', 'user.email=ads@oimpresso.com',
                '-c', 'user.name=ADS Wagner',
                'commit',
                '-m', $message . "\n\n[ADS-decision-{$decisionId}]",
            ]);
            if (! $r['ok']) return ['ok' => false, 'output' => null, 'error' => 'commit_failed: ' . $r['err']];

            // 5. Pega SHA do commit criado
            $sha = trim($this->git(['rev-parse', 'HEAD'])['out']);

            return [
                'ok' => true,
                'output' => [
                    'branch'        => $branch,
                    'commit_sha'    => $sha,
                    'commit_short'  => substr($sha, 0, 8),
                    'paths_committed' => array_filter(explode("\n", trim($diffStaged['out']))),
                    'next_steps'    => "Wagner: revisar branch {$branch}, fazer git push origin {$branch} se OK, ou git checkout main se descartar.",
                ],
                'error' => null,
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'output' => null, 'error' => 'unexpected: ' . $e->getMessage()];
        }
    }

    /**
     * Wrapper git seguro — whitelist subcomandos.
     * @return array{ok:bool, out:string, err:string}
     */
    private function git(array $args, bool $throwOnFail = true): array
    {
        // Whitelist hard de subcomandos
        $allowed = ['rev-parse', 'checkout', 'add', 'diff', 'commit', '-c'];
        $first = $args[0] ?? '';
        $cmdToken = str_starts_with($first, '-') ? ($args[2] ?? '') : $first;

        if (! in_array($first, $allowed, true) && ! in_array($cmdToken, $allowed, true)) {
            return ['ok' => false, 'out' => '', 'err' => "git_subcommand_blocked: {$first}"];
        }

        $process = new Process(array_merge(['git'], $args), base_path());
        $process->setTimeout(15);
        $process->run();

        return [
            'ok'  => $process->isSuccessful(),
            'out' => $process->getOutput(),
            'err' => $process->getErrorOutput(),
        ];
    }
}
