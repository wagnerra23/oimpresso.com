<?php

declare(strict_types=1);

namespace Modules\Governance\Services\Checkers;

use Modules\Governance\Contracts\DriftChecker;
use Modules\Governance\Services\DriftCheckResult;
use Modules\Governance\Services\DriftFinding;

/**
 * DeployDriftChecker — código deployado != GitHub main (ADR 0216).
 *
 * Fecha a pior dimensão do reconcile (estado-da-arte 2026-05-29, nota 20): o container
 * MCP ficou 1302 commits atrás de main e era CEGO até quebrar. Generaliza o padrão do
 * `Whatsapp\...\DaemonSourceDriftCheckCommand` (deploy-source drift) pro app inteiro,
 * plugado no framework DriftChecker (NÃO comando bespoke).
 *
 * Fontes de verdade (sem git binary — container não tem; sem token — repo privado):
 *  - deployado: lê `.git/HEAD` + refs (arquivo) → SHA do código que está rodando.
 *  - main: `Cache deploy:latest_main_sha` gravado pelo webhook GitHub→MCP a cada push
 *    em main (SyncMemoryWebhookController). Fallback: `.git/refs/remotes/origin/main`
 *    (fetchado no último deploy — pode ser stale, mas melhor que nada).
 *
 * Severity high (drift de deploy = bug oculto) · enforcement warn · cadence daily.
 * Multi-env (Hostinger via /health) = follow-up; v1 cobre o ambiente onde roda (CT 100).
 */
final class DeployDriftChecker implements DriftChecker
{
    /**
     * Arquivo onde o webhook GitHub→MCP grava o SHA do último push em main.
     * ARQUIVO (não cache) de propósito: o container roda CACHE_DRIVER=array
     * (per-process) — cache do webhook (octane) NÃO cruza pro audit (CLI). O
     * storage é bind-mounted e compartilhado entre processos. (verificado 2026-05-29)
     */
    public static function shaFilePath(): string
    {
        return storage_path('app/deploy-latest-main-sha.txt');
    }

    public function name(): string
    {
        return 'deploy_drift';
    }

    public function description(): string
    {
        return 'Código deployado != GitHub main (o "N commits atrás" silencioso)';
    }

    public function tags(): array
    {
        return ['tier_1', 'deploy', 'infra'];
    }

    public function severity(): string
    {
        return 'high';
    }

    public function enforcement(): string
    {
        return 'warn';
    }

    public function cadence(): string
    {
        return 'daily';
    }

    public function check(array $opts = []): DriftCheckResult
    {
        $start = microtime(true);
        $base  = base_path();

        $deployed = $this->deployedSha($base);
        $main     = $this->latestMainSha($base);

        $duration = (int) round((microtime(true) - $start) * 1000);

        $findings = $this->analisar($deployed, $main);

        if ($findings === []) {
            return DriftCheckResult::clean($this->name(), $duration, [
                'deployed' => $deployed,
                'main'     => $main,
            ]);
        }

        return DriftCheckResult::drifted($this->name(), $findings, $duration, [
            'deployed' => $deployed,
            'main'     => $main,
        ]);
    }

    /**
     * Decide o finding a partir do SHA deployado × main. Puro/testável.
     *
     * @return DriftFinding[]
     */
    public function analisar(?string $deployed, ?string $main): array
    {
        // Sem como saber o deployado → finding de baixa severidade (config/.git ausente).
        if ($deployed === null) {
            return [new DriftFinding(
                target: 'deploy',
                target_type: 'deploy',
                severity: 'low',
                message: 'Não consegui ler o SHA deployado (.git/HEAD). Deploy drift não verificável.',
                evidence: ['deployed' => null],
            )];
        }

        // Main desconhecido (webhook ainda não gravou + sem origin/main ref) → info, não trava.
        if ($main === null) {
            return [new DriftFinding(
                target: 'deploy',
                target_type: 'deploy',
                severity: 'info',
                message: 'SHA de main desconhecido ainda (sem push registrado pelo webhook). Reverifica no próximo push.',
                evidence: ['deployed' => $deployed, 'main' => null],
            )];
        }

        if (! $this->mesmoSha($deployed, $main)) {
            return [new DriftFinding(
                target: 'deploy',
                target_type: 'deploy',
                severity: 'high',
                message: "Código deployado ({$deployed}) != GitHub main ({$main}). "
                    .'Deploy atrasado — `git reset --hard origin/main` + composer + octane:reload (ver INFRA-ACESSO-CANON).',
                evidence: ['deployed' => $deployed, 'main' => $main],
            )];
        }

        return [];
    }

    /**
     * SHA do código deployado — lê .git/HEAD + refs/packed-refs (sem git binary).
     */
    public function deployedSha(string $base): ?string
    {
        $headFile = $base.'/.git/HEAD';
        if (! is_file($headFile)) {
            return null;
        }
        $head = trim((string) file_get_contents($headFile));

        if (str_starts_with($head, 'ref: ')) {
            return $this->resolverRef($base, trim(substr($head, 5)));
        }

        // HEAD destacado (SHA direto)
        return $this->ehSha($head) ? $head : null;
    }

    /**
     * SHA de main: arquivo gravado pelo webhook a cada push (fresco, cross-process)
     * OU origin/main ref (stale fallback). $shaFile injetável pra teste.
     */
    public function latestMainSha(string $base, ?string $shaFile = null): ?string
    {
        $shaFile ??= self::shaFilePath();
        if (is_file($shaFile)) {
            $sha = trim((string) file_get_contents($shaFile));
            if ($this->ehSha($sha)) {
                return $sha;
            }
        }

        return $this->resolverRef($base, 'refs/remotes/origin/main');
    }

    /**
     * Resolve um ref Git lendo arquivo OU packed-refs.
     */
    private function resolverRef(string $base, string $ref): ?string
    {
        $refFile = $base.'/.git/'.$ref;
        if (is_file($refFile)) {
            $sha = trim((string) file_get_contents($refFile));

            return $this->ehSha($sha) ? $sha : null;
        }

        $packed = $base.'/.git/packed-refs';
        if (is_file($packed)) {
            foreach (preg_split('/\R/', (string) file_get_contents($packed)) ?: [] as $linha) {
                $linha = trim($linha);
                if ($linha === '' || $linha[0] === '#' || $linha[0] === '^') {
                    continue;
                }
                [$sha, $r] = array_pad(explode(' ', $linha, 2), 2, '');
                if ($r === $ref && $this->ehSha($sha)) {
                    return $sha;
                }
            }
        }

        return null;
    }

    /** Compara SHAs tolerando short vs full (prefix match, mínimo 7). */
    public function mesmoSha(string $a, string $b): bool
    {
        $a = strtolower($a);
        $b = strtolower($b);
        $n = min(strlen($a), strlen($b));

        return $n >= 7 && str_starts_with($a, substr($b, 0, $n)) && str_starts_with($b, substr($a, 0, $n));
    }

    private function ehSha(string $s): bool
    {
        return (bool) preg_match('/^[0-9a-f]{7,40}$/i', $s);
    }
}
