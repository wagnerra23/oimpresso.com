<?php

declare(strict_types=1);

namespace Modules\TeamMcp\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * GitMainResolver — PR-2 Loop de Handoff Zero-Paste (Fase 0 · ADR 0283).
 *
 * Resolve o HEAD do `main` + arquivos mudados entre 2 SHAs via **GitHub API**.
 * O servidor MCP roda no CT 100 SEM checkout git do repo (ADR 0062) — por isso
 * API, não `git` local. Usado pelo HandoffPendingTool pro drift/stale guard (A4
 * do adversário [AH]): detecta que o `main` andou nos arquivos do handoff ANTES
 * do Code começar a trabalhar.
 *
 * Token/repo via `config('services.github.*')` (env GITHUB_API_TOKEN /
 * COPILOTO_PUBLISH_REPO — mesmo PAT de {@see \Modules\Jana\Services\Skills\PublicarSkillNoGitService},
 * ADR 0076). **Degrada gracioso:** sem token / API fora / SHA inválido → null/[]
 * (sem `stale_warning` falso). Nunca lança — o handoff-pending não pode quebrar
 * por causa de um drift-check best-effort.
 */
class GitMainResolver
{
    private function repo(): string
    {
        return (string) config('services.github.repo', 'wagnerra23/oimpresso.com');
    }

    private function token(): ?string
    {
        $t = config('services.github.token');

        return is_string($t) && $t !== '' ? $t : null;
    }

    /**
     * SHA atual do branch (default `main`). Cache 60s. Null se não der pra resolver.
     */
    public function headSha(string $branch = 'main'): ?string
    {
        $token = $this->token();
        if ($token === null) {
            return null;
        }

        return Cache::remember("gitmain.head.{$branch}", now()->addSeconds(60), function () use ($branch, $token) {
            try {
                $r = Http::withToken($token)
                    ->withHeaders(['Accept' => 'application/vnd.github+json'])
                    ->timeout(8)
                    ->get("https://api.github.com/repos/{$this->repo()}/commits/{$branch}");

                if (! $r->successful()) {
                    return null;
                }

                $sha = $r->json('sha');

                return is_string($sha) && $sha !== '' ? $sha : null;
            } catch (Throwable) {
                return null;
            }
        });
    }

    /**
     * Quais dos $files mudaram no `main` entre $base e $head. Retorna a interseção
     * (arquivos do handoff que o main mexeu). `[]` = nada mudou OU não deu pra
     * determinar (degrada sem warning falso).
     *
     * @param  list<string>  $files
     * @return list<string>
     */
    public function filesChangedBetween(string $base, string $head, array $files): array
    {
        if ($files === [] || $base === '' || $head === '' || $base === $head) {
            return [];
        }

        $token = $this->token();
        if ($token === null) {
            return [];
        }

        try {
            $r = Http::withToken($token)
                ->withHeaders(['Accept' => 'application/vnd.github+json'])
                ->timeout(8)
                ->get("https://api.github.com/repos/{$this->repo()}/compare/{$base}...{$head}");

            if (! $r->successful()) {
                return [];
            }

            $changed = array_filter(array_map(
                static fn ($f) => is_array($f) ? ($f['filename'] ?? '') : '',
                $r->json('files') ?? [],
            ));

            return array_values(array_intersect($files, $changed));
        } catch (Throwable) {
            return [];
        }
    }
}
