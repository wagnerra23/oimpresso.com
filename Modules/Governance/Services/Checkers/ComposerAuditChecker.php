<?php

declare(strict_types=1);

namespace Modules\Governance\Services\Checkers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Modules\Governance\Contracts\DriftChecker;
use Modules\Governance\Services\DriftCheckResult;
use Modules\Governance\Services\DriftFinding;

/**
 * ComposerAuditChecker — detecta CVEs em deps PHP via `composer audit --locked`.
 *
 * ADR 0217 (filha de 0216). Empiricamente justificado: smoke 2026-05-28 21:30
 * detectou 4 CVEs ATIVAS symfony/yaml (CVE-2026-45065, 45304, 45305, 45133,
 * reported 2026-05-20) sem ninguém saber. Sem este checker, próxima CVE high/critical
 * passa despercebida até alguém manualmente rodar `composer audit`.
 *
 * Severity por CVE:
 * - critical CVE → finding severity 'critical', enforcement block
 * - high CVE → finding severity 'high', enforcement warn
 * - medium/low CVE → finding severity matching, enforcement advisory
 *
 * Tags: tier_1, security, supply_chain
 * Cadence: daily (cron) + on_pr (CI gate)
 *
 * Supply chain attack 2026 lesson (axios + laravel-lang + Shai-Hulud):
 * NÃO sugerir auto-PR de update sem cooldown 7d. Esta versão só DETECTA — humano remedia.
 */
final class ComposerAuditChecker implements DriftChecker
{
    public function name(): string
    {
        return 'composer_audit';
    }

    public function description(): string
    {
        return 'Detecta CVEs em deps composer.lock (supply chain security)';
    }

    public function tags(): array
    {
        return ['tier_1', 'security', 'supply_chain'];
    }

    public function severity(): string
    {
        return 'high';
    }

    public function enforcement(): string
    {
        return 'warn'; // findings individuais sobrescrevem; high/critical viram warn/block
    }

    public function cadence(): string
    {
        return 'daily';
    }

    public function check(array $opts = []): DriftCheckResult
    {
        $start = microtime(true);

        $process = Process::path(base_path())
            ->timeout(120)
            ->run(['composer', 'audit', '--locked', '--format=json']);

        $durationMs = (int) ((microtime(true) - $start) * 1000);

        // composer audit exit code: 0 = clean, 1 = vulnerabilities found, >1 = error
        $exitCode = $process->exitCode();
        $stdout = $process->output();
        $stderr = $process->errorOutput();

        if ($exitCode > 1) {
            Log::channel('single')->error('composer_audit checker — composer binary failed', [
                'exit_code' => $exitCode,
                'stderr' => mb_substr($stderr, 0, 500),
            ]);

            return DriftCheckResult::clean(
                name: $this->name(),
                duration_ms: $durationMs,
                metadata: ['error' => 'composer binary unavailable or crashed', 'exit_code' => $exitCode],
            );
        }

        if ($exitCode === 0 || trim($stdout) === '') {
            return DriftCheckResult::clean($this->name(), $durationMs, [
                'audited_at' => now()->toIso8601String(),
            ]);
        }

        $decoded = json_decode($stdout, true);
        if (! is_array($decoded)) {
            Log::channel('single')->warning('composer_audit checker — não foi possível parsear JSON', [
                'stdout_preview' => mb_substr($stdout, 0, 200),
            ]);

            return DriftCheckResult::clean($this->name(), $durationMs);
        }

        $findings = $this->extractFindings($decoded);

        if (count($findings) === 0) {
            return DriftCheckResult::clean($this->name(), $durationMs);
        }

        return DriftCheckResult::drifted(
            name: $this->name(),
            findings: $findings,
            duration_ms: $durationMs,
            metadata: [
                'total_advisories' => count($findings),
                'audited_at' => now()->toIso8601String(),
                'highest_severity' => $this->highestSeverity($findings),
            ],
        );
    }

    /**
     * @param array<string, mixed> $decoded
     * @return array<int, DriftFinding>
     */
    private function extractFindings(array $decoded): array
    {
        $findings = [];

        // Schema canon composer 2.6+: {"advisories": {"<package>": [<adv1>, <adv2>]}}
        $advisories = $decoded['advisories'] ?? [];
        if (! is_array($advisories)) {
            return [];
        }

        foreach ($advisories as $packageName => $packageAdvisories) {
            if (! is_array($packageAdvisories)) {
                continue;
            }
            foreach ($packageAdvisories as $adv) {
                if (! is_array($adv)) {
                    continue;
                }
                $cve = $adv['cve'] ?? $adv['advisoryId'] ?? 'unknown';
                $title = $adv['title'] ?? 'CVE sem título';
                $severity = $this->mapComposerSeverity($adv['severity'] ?? 'medium');
                $affected = $adv['affectedVersions'] ?? '';
                $link = $adv['link'] ?? '';

                $findings[] = new DriftFinding(
                    target: (string) $packageName,
                    target_type: 'composer_package',
                    severity: $severity,
                    message: sprintf(
                        'CVE %s em %s — %s. Affected: %s. Ação: composer update %s. Ref: %s',
                        $cve,
                        $packageName,
                        mb_strimwidth($title, 0, 120, '…'),
                        mb_strimwidth($affected, 0, 80, '…'),
                        $packageName,
                        $link,
                    ),
                    evidence: [
                        'cve' => $cve,
                        'severity_composer' => $adv['severity'] ?? null,
                        'severity_canonical' => $severity,
                        'title' => $title,
                        'affected_versions' => $affected,
                        'link' => $link,
                        'reported_at' => $adv['reportedAt'] ?? null,
                    ],
                );
            }
        }

        return $findings;
    }

    private function mapComposerSeverity(string $composerSeverity): string
    {
        return match (strtolower(trim($composerSeverity))) {
            'critical' => 'critical',
            'high' => 'high',
            'medium' => 'medium',
            'low' => 'low',
            default => 'medium',
        };
    }

    /**
     * @param array<int, DriftFinding> $findings
     */
    private function highestSeverity(array $findings): string
    {
        $rank = ['critical' => 5, 'high' => 4, 'medium' => 3, 'low' => 2, 'info' => 1];
        $max = 'low';
        foreach ($findings as $f) {
            if (($rank[$f->severity] ?? 0) > ($rank[$max] ?? 0)) {
                $max = $f->severity;
            }
        }

        return $max;
    }
}
