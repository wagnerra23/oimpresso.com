<?php

declare(strict_types=1);

namespace Modules\Governance\Services\Checkers;

use Illuminate\Support\Facades\Http;
use Modules\Governance\Contracts\DriftChecker;
use Modules\Governance\Services\DriftCheckResult;
use Modules\Governance\Services\DriftFinding;

/**
 * McpServedDriftChecker — commit SERVIDO por cada env != GitHub main (Onda 1).
 *
 * Fecha o gap de transporte CT100→main que o {@see DeployDriftChecker} (ADR 0216)
 * marcou como follow-up: "Multi-env (Hostinger via /health) = follow-up; v1 cobre
 * o ambiente onde roda (CT 100)". Incidente real que motivou: o container MCP ficou
 * ~19 dias stale vs main e ninguém viu — handoff-pending/-ack inalcançáveis o tempo todo.
 *
 * Diferença do DeployDriftChecker:
 *  - DeployDriftChecker: lê `.git/HEAD` LOCAL (o env ONDE o audit roda — CT 100).
 *  - McpServedDriftChecker: CONSOME `GET /api/mcp/version` (ADR 0256) de uma lista de
 *    envs REMOTOS (mcp.oimpresso.com etc.) e compara o commit servido por CADA UM
 *    com main. Pega o stale de QUALQUER env, não só o local.
 *
 * Fonte de "main": REUTILIZA a mesma do DeployDriftChecker (arquivo gravado pelo
 * webhook GitHub→MCP + fallback origin/main ref) — instancia o DeployDriftChecker e
 * chama seus métodos públicos `latestMainSha`/`mesmoSha`. Zero duplicação da lógica
 * de leitura de SHA (single source of truth — se a fonte de main mudar, muda nos dois).
 *
 * Auth: Bearer `config('copiloto.mcp.drift_token')` (env MCP_DRIFT_TOKEN) — o MESMO
 * token dedicado que o endpoint /api/mcp/version espera (sem user, sem RBAC).
 *
 * Tolerância a falha: rede/HTTP fora do ar = finding low/info (NÃO derruba o audit,
 * NUNCA throw). Determinístico, sem efeitos colaterais.
 *
 * Severity high (env stale = bug/feature ausente em prod, silencioso) · enforcement
 * warn · cadence daily. System-level (sem business_id — ADR 0093 §Exceção repo-wide).
 */
final class McpServedDriftChecker implements DriftChecker
{
    /** Timeout por env (s). Curto de propósito — sentinela não pode pendurar o audit. */
    private const HTTP_TIMEOUT = 8;

    public function name(): string
    {
        return 'mcp_served_drift';
    }

    public function description(): string
    {
        return 'Commit servido por cada env (/api/mcp/version) != GitHub main (env stale silencioso)';
    }

    public function tags(): array
    {
        return ['tier_1', 'deploy', 'infra', 'transporte'];
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

        $deployChecker = new DeployDriftChecker();
        $main = $deployChecker->latestMainSha(base_path());

        $envs = $this->envs();
        $token = (string) config('copiloto.mcp.drift_token', '');

        $findings = [];
        $metaEnvs = [];

        foreach ($envs as $env) {
            [$finding, $meta] = $this->verificarEnv($env, $main, $token, $deployChecker);
            $metaEnvs[] = $meta;
            if ($finding !== null) {
                $findings[] = $finding;
            }
        }

        $duration = (int) round((microtime(true) - $start) * 1000);
        $meta = ['main' => $main, 'envs' => $metaEnvs];

        if ($findings === []) {
            return DriftCheckResult::clean($this->name(), $duration, $meta);
        }

        return DriftCheckResult::drifted($this->name(), $findings, $duration, $meta);
    }

    /**
     * Lista de envs a checar — config NOVO `governance.deploy_drift_envs`.
     * Cada item: ['nome' => string, 'url' => string] (sem trailing slash).
     * Default cobre só o MCP público; overridable por env GOVERNANCE_DEPLOY_DRIFT_ENVS (JSON).
     *
     * @return array<int, array{nome: string, url: string}>
     */
    private function envs(): array
    {
        $raw = config('governance.deploy_drift_envs', []);
        $out = [];
        foreach ((array) $raw as $item) {
            if (! is_array($item)) {
                continue;
            }
            $nome = trim((string) ($item['nome'] ?? ''));
            $url = rtrim(trim((string) ($item['url'] ?? '')), '/');
            if ($nome === '' || $url === '') {
                continue;
            }
            $out[] = ['nome' => $nome, 'url' => $url];
        }

        return $out;
    }

    /**
     * Bate em UM env, compara commit servido × main, decide o finding.
     * Puro o suficiente pra testar com Http::fake. Nunca lança.
     *
     * @return array{0: ?DriftFinding, 1: array<string, mixed>}
     */
    private function verificarEnv(array $env, ?string $main, string $token, DeployDriftChecker $deployChecker): array
    {
        $nome = $env['nome'];
        $url = $env['url'].'/api/mcp/version';

        try {
            $resp = Http::withToken($token)
                ->timeout(self::HTTP_TIMEOUT)
                ->acceptJson()
                ->get($url);
        } catch (\Throwable $e) {
            // Rede caiu (DNS, conexão recusada, timeout transport) — info, não derruba.
            return [
                new DriftFinding(
                    target: $nome,
                    target_type: 'env',
                    severity: 'info',
                    message: "Não consegui contatar {$nome} ({$url}): {$e->getMessage()}. Drift do env não verificável agora.",
                    evidence: ['env' => $nome, 'url' => $url, 'error' => $e->getMessage()],
                ),
                ['nome' => $nome, 'status' => 'unreachable', 'error' => $e->getMessage()],
            ];
        }

        if (! $resp->successful()) {
            // 401 (token errado), 500 (misconfigured), 5xx (env doente) — low, não derruba.
            return [
                new DriftFinding(
                    target: $nome,
                    target_type: 'env',
                    severity: 'low',
                    message: "{$nome} ({$url}) respondeu HTTP {$resp->status()} — commit servido não verificável "
                        .'(token MCP_DRIFT_TOKEN errado? env doente?).',
                    evidence: ['env' => $nome, 'url' => $url, 'http_status' => $resp->status()],
                ),
                ['nome' => $nome, 'status' => 'http_'.$resp->status()],
            ];
        }

        $served = $this->extrairCommit($resp->json('commit'));
        $deployedAt = $resp->json('deployed_at');

        // Env respondeu mas sem commit (deployed_commit.txt = "unknown" no boot) → info.
        if ($served === null) {
            return [
                new DriftFinding(
                    target: $nome,
                    target_type: 'env',
                    severity: 'info',
                    message: "{$nome} respondeu mas sem commit servido (deployed_commit.txt 'unknown'?). Reverifica no próximo boot.",
                    evidence: ['env' => $nome, 'url' => $url, 'served' => null, 'deployed_at' => $deployedAt],
                ),
                ['nome' => $nome, 'status' => 'no_commit', 'deployed_at' => $deployedAt],
            ];
        }

        // Main desconhecido (webhook ainda não gravou + sem origin/main ref) → info, não trava.
        if ($main === null) {
            return [
                new DriftFinding(
                    target: $nome,
                    target_type: 'env',
                    severity: 'info',
                    message: "SHA de main desconhecido ainda — não dá pra comparar {$nome} ({$served}). Reverifica no próximo push.",
                    evidence: ['env' => $nome, 'served' => $served, 'main' => null, 'deployed_at' => $deployedAt],
                ),
                ['nome' => $nome, 'status' => 'main_unknown', 'served' => $served, 'deployed_at' => $deployedAt],
            ];
        }

        if (! $deployChecker->mesmoSha($served, $main)) {
            $idade = $this->idadeHumana($deployedAt);
            $sufixoIdade = $idade !== null ? " Servido há {$idade}." : '';

            return [
                new DriftFinding(
                    target: $nome,
                    target_type: 'env',
                    severity: 'high',
                    message: "{$nome} serve commit {$served} != GitHub main ({$main}).{$sufixoIdade} "
                        .'Env stale — fazer deploy (ver RUNBOOK-transporte-sentinel + INFRA-ACESSO-CANON).',
                    evidence: [
                        'env' => $nome,
                        'url' => $url,
                        'served' => $served,
                        'main' => $main,
                        'deployed_at' => $deployedAt,
                        'idade' => $idade,
                    ],
                ),
                ['nome' => $nome, 'status' => 'drifted', 'served' => $served, 'deployed_at' => $deployedAt],
            ];
        }

        return [null, ['nome' => $nome, 'status' => 'fresh', 'served' => $served, 'deployed_at' => $deployedAt]];
    }

    /** Normaliza/valida o commit vindo do JSON. null se ausente/inválido. */
    private function extrairCommit(mixed $raw): ?string
    {
        if (! is_string($raw)) {
            return null;
        }
        $sha = strtolower(trim($raw));

        return preg_match('/^[0-9a-f]{7,40}$/', $sha) === 1 ? $sha : null;
    }

    /**
     * Quanto tempo faz que o env deployou (string legível PT-BR), a partir do
     * `deployed_at` ISO-8601 do /api/mcp/version. null se ausente/ilegível.
     */
    private function idadeHumana(mixed $deployedAt): ?string
    {
        if (! is_string($deployedAt) || trim($deployedAt) === '') {
            return null;
        }
        try {
            return \Carbon\Carbon::parse($deployedAt)->diffForHumans(now(), \Carbon\CarbonInterface::DIFF_ABSOLUTE);
        } catch (\Throwable) {
            return null;
        }
    }
}
