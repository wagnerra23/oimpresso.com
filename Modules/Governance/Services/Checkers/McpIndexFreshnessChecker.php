<?php

declare(strict_types=1);

namespace Modules\Governance\Services\Checkers;

use Illuminate\Support\Facades\Process;
use Modules\Governance\Contracts\DriftChecker;
use Modules\Governance\Services\DriftCheckResult;
use Modules\Governance\Services\DriftFinding;

/**
 * McpIndexFreshnessChecker — índice MCP (mcp_memory_documents) defasado vs git memory/ (Onda 1).
 *
 * Terceira perna da sentinela de transporte CT100→main: mesmo que o código deployado
 * esteja em main (DeployDrift/McpServedDrift verdes), o ÍNDICE da memória pode ter
 * parado de atualizar — o job IndexarMemoryGitParaDb (webhook GitHub OU cron 5min)
 * pode falhar CALADO. Resultado: docs novos em memory/ existem no git mas as tools
 * MCP (decisions-search/kb-answer) servem versão velha. Drift silencioso clássico.
 *
 * Compara:
 *  - índice: `McpMemoryDocument::max('updated_at')` (doc mais recentemente indexado).
 *  - git: timestamp do último commit que TOCOU `memory/` (`git log -1 --format=%cI -- memory/`).
 *
 * Se o índice está mais velho que o último commit de memory/ por > X horas (default 6,
 * configurável via `governance.mcp_index_freshness_max_lag_hours`), finding high:
 * "índice MCP defasado — IndexarMemoryGitParaDb pode ter falhado calado".
 *
 * Tolerância a falha: DB indisponível OU git indisponível (container sem binário) =
 * finding info, sem throw. Determinístico, sem efeitos colaterais.
 *
 * Severity high · enforcement warn · cadence daily. System-level (sem business_id —
 * tabela é repo-wide por ADR 0053; ADR 0093 §Exceção).
 */
final class McpIndexFreshnessChecker implements DriftChecker
{
    /** Lag default tolerado (h) entre commit de memory/ e índice. Override por config. */
    private const DEFAULT_MAX_LAG_HOURS = 6;

    /** Timeout do `git log` (s). Curto — sentinela não pendura o audit. */
    private const GIT_TIMEOUT = 10;

    public function name(): string
    {
        return 'mcp_index_freshness';
    }

    public function description(): string
    {
        return 'Índice MCP (mcp_memory_documents) defasado vs último commit em git memory/ (sync calado?)';
    }

    public function tags(): array
    {
        return ['tier_1', 'mcp', 'infra', 'transporte'];
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

        $maxLagHours = (int) config('governance.mcp_index_freshness_max_lag_hours', self::DEFAULT_MAX_LAG_HOURS);

        $indexTs = $this->ultimoDocIndexado();
        $gitTs = $this->ultimoCommitMemory();

        $duration = (int) round((microtime(true) - $start) * 1000);

        $findings = $this->analisar($indexTs, $gitTs, $maxLagHours);
        $meta = [
            'index_updated_at' => $indexTs?->toIso8601String(),
            'git_memory_committed_at' => $gitTs?->toIso8601String(),
            'max_lag_hours' => $maxLagHours,
        ];

        if ($findings === []) {
            return DriftCheckResult::clean($this->name(), $duration, $meta);
        }

        return DriftCheckResult::drifted($this->name(), $findings, $duration, $meta);
    }

    /**
     * Decide o finding a partir dos dois timestamps. Puro/testável (sem DB/git).
     *
     * @return DriftFinding[]
     */
    public function analisar(?\Carbon\CarbonInterface $indexTs, ?\Carbon\CarbonInterface $gitTs, int $maxLagHours): array
    {
        // Sem timestamp de git (binário ausente, repo raso) → info, não verificável.
        if ($gitTs === null) {
            return [new DriftFinding(
                target: 'mcp_index',
                target_type: 'index',
                severity: 'info',
                message: 'Não consegui ler o último commit de memory/ (git indisponível). Frescor do índice não verificável.',
                evidence: ['git_memory_committed_at' => null],
            )];
        }

        // Sem doc indexado (DB vazio ou indisponível) → info.
        if ($indexTs === null) {
            return [new DriftFinding(
                target: 'mcp_index',
                target_type: 'index',
                severity: 'info',
                message: 'Índice MCP vazio ou DB indisponível (mcp_memory_documents.max(updated_at) nulo). Frescor não verificável.',
                evidence: ['index_updated_at' => null, 'git_memory_committed_at' => $gitTs->toIso8601String()],
            )];
        }

        // Índice mais velho que o último commit de memory/ por mais que o lag tolerado.
        $lagHoras = $gitTs->diffInHours($indexTs, false) * -1; // git - index, positivo se índice atrás
        if ($indexTs->lt($gitTs) && $lagHoras > $maxLagHours) {
            return [new DriftFinding(
                target: 'mcp_index',
                target_type: 'index',
                severity: 'high',
                message: sprintf(
                    'Índice MCP defasado: último doc indexado em %s, mas memory/ tem commit em %s (%dh atrás do git, limite %dh). '
                        .'IndexarMemoryGitParaDb pode ter falhado calado — checar webhook GitHub→MCP + cron 5min.',
                    $indexTs->toIso8601String(),
                    $gitTs->toIso8601String(),
                    (int) round($lagHoras),
                    $maxLagHours,
                ),
                evidence: [
                    'index_updated_at' => $indexTs->toIso8601String(),
                    'git_memory_committed_at' => $gitTs->toIso8601String(),
                    'lag_hours' => (int) round($lagHoras),
                    'max_lag_hours' => $maxLagHours,
                ],
            )];
        }

        return [];
    }

    /**
     * `McpMemoryDocument::max('updated_at')` tolerando DB indisponível/tabela ausente.
     * Retorna null em qualquer falha (NUNCA throw).
     */
    private function ultimoDocIndexado(): ?\Carbon\CarbonInterface
    {
        try {
            $max = \Modules\Jana\Entities\Mcp\McpMemoryDocument::max('updated_at');
            if ($max === null || $max === '') {
                return null;
            }

            return \Carbon\Carbon::parse($max);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Timestamp ISO-8601 do último commit que tocou `memory/` via `git log -1 --format=%cI`.
     * null se git indisponível / sem commit / falha (NUNCA throw).
     */
    private function ultimoCommitMemory(): ?\Carbon\CarbonInterface
    {
        try {
            $process = Process::path(base_path())
                ->timeout(self::GIT_TIMEOUT)
                ->run(['git', 'log', '-1', '--format=%cI', '--', 'memory/']);

            if (! $process->successful()) {
                return null;
            }
            $out = trim($process->output());
            if ($out === '') {
                return null;
            }

            return \Carbon\Carbon::parse($out);
        } catch (\Throwable) {
            return null;
        }
    }
}
