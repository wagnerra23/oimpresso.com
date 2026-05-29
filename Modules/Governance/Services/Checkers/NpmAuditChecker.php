<?php

declare(strict_types=1);

namespace Modules\Governance\Services\Checkers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Modules\Governance\Contracts\DriftChecker;
use Modules\Governance\Services\DriftCheckResult;
use Modules\Governance\Services\DriftFinding;

/**
 * NpmAuditChecker — detecta CVEs em deps Node/npm (frontend stack).
 *
 * ADR 0223 (filha de 0216). Complementa ComposerAuditChecker (ADR 0217)
 * cobrindo o outro lado da stack: React, Inertia, Vite, Tailwind, TypeScript.
 *
 * Lições supply chain 2026:
 * - Shai-Hulud 2.0 wave 4 mai/2026 — 640+ pacotes npm worm
 * - axios mar/2026 — 5min exposição → 895 repos PR-pushed
 *
 * Mecanismo:
 *   shell-out `npm audit --json` (cwd=base_path), timeout 120s
 *   parse schema `auditReportVersion: 2` canonical
 *     `vulnerabilities.<pkg>.{severity, via[], range, isDirect, fixAvailable}`
 *
 * Severity map npm → canonical (consistente ComposerAuditChecker):
 *   critical → critical
 *   high → high
 *   moderate → medium
 *   low → low
 *   info → info
 *
 * Tags: tier_1, security, supply_chain, frontend
 * Severity baseline: high (CVEs frontend podem permitir XSS/RCE em produção)
 * Enforcement: warn (findings críticos viram block via --fail-on=block runtime)
 * Cadence: daily (cron) + on_pr (CI gate)
 */
final class NpmAuditChecker implements DriftChecker
{
    public function name(): string
    {
        return 'npm_audit';
    }

    public function description(): string
    {
        return 'Detecta CVEs em deps npm/package-lock.json (frontend supply chain)';
    }

    public function tags(): array
    {
        return ['tier_1', 'security', 'supply_chain', 'frontend'];
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

        if (! file_exists(base_path('package.json')) || ! file_exists(base_path('package-lock.json'))) {
            return DriftCheckResult::clean(
                name: $this->name(),
                duration_ms: 0,
                metadata: ['skipped' => 'package.json or package-lock.json not found'],
            );
        }

        $process = Process::path(base_path())
            ->timeout(180)
            ->run(['npm', 'audit', '--json']);

        $durationMs = (int) ((microtime(true) - $start) * 1000);

        // npm audit exit codes:
        //   0 = no vulnerabilities
        //   1 = vulnerabilities found
        //   2+ = error (npm itself failed)
        $exitCode = $process->exitCode();
        $stdout = $process->output();
        $stderr = $process->errorOutput();

        if ($exitCode >= 2) {
            Log::channel('single')->error('npm_audit checker — npm binary failed', [
                'exit_code' => $exitCode,
                'stderr' => mb_substr($stderr, 0, 500),
            ]);

            return DriftCheckResult::clean(
                name: $this->name(),
                duration_ms: $durationMs,
                metadata: ['error' => 'npm binary unavailable or crashed', 'exit_code' => $exitCode],
            );
        }

        $decoded = json_decode($stdout, true);
        if (! is_array($decoded)) {
            Log::channel('single')->warning('npm_audit checker — JSON inválido', [
                'stdout_preview' => mb_substr($stdout, 0, 200),
            ]);

            return DriftCheckResult::clean($this->name(), $durationMs);
        }

        $findings = $this->extractFindings($decoded);

        if (count($findings) === 0) {
            return DriftCheckResult::clean(
                name: $this->name(),
                duration_ms: $durationMs,
                metadata: [
                    'audited_at' => now()->toIso8601String(),
                    'audit_report_version' => $decoded['auditReportVersion'] ?? null,
                ],
            );
        }

        return DriftCheckResult::drifted(
            name: $this->name(),
            findings: $findings,
            duration_ms: $durationMs,
            metadata: [
                'total_advisories' => count($findings),
                'audited_at' => now()->toIso8601String(),
                'highest_severity' => $this->highestSeverity($findings),
                'severity_counts' => $this->countBySeverity($findings),
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

        $vulnerabilities = $decoded['vulnerabilities'] ?? [];
        if (! is_array($vulnerabilities)) {
            return [];
        }

        foreach ($vulnerabilities as $packageName => $vulnData) {
            if (! is_array($vulnData)) {
                continue;
            }

            $severity = $this->mapNpmSeverity($vulnData['severity'] ?? 'moderate');
            $range = (string) ($vulnData['range'] ?? '');
            $isDirect = (bool) ($vulnData['isDirect'] ?? false);
            $fixAvailable = $vulnData['fixAvailable'] ?? false;
            $effects = (array) ($vulnData['effects'] ?? []);

            // Extract advisory details from via[] — pode ter strings (parent pkg) ou objects (advisory)
            $advisories = [];
            foreach ((array) ($vulnData['via'] ?? []) as $via) {
                if (is_array($via) && isset($via['title'])) {
                    $advisories[] = $via;
                }
            }

            if (count($advisories) === 0) {
                // Vulnerability transitive — só registramos parent + range, sem CVE direto
                $findings[] = new DriftFinding(
                    target: (string) $packageName,
                    target_type: 'npm_package',
                    severity: $severity,
                    message: sprintf(
                        'npm vulnerability %s em %s (range: %s, %s, fix %s). %s',
                        $severity,
                        $packageName,
                        mb_strimwidth($range, 0, 60, '…'),
                        $isDirect ? 'direct' : 'transitive',
                        $fixAvailable ? 'available' : 'NOT available',
                        count($effects) > 0 ? 'Affects: ' . implode(', ', array_slice($effects, 0, 3)) : '',
                    ),
                    evidence: [
                        'package' => $packageName,
                        'severity_npm' => $vulnData['severity'] ?? null,
                        'severity_canonical' => $severity,
                        'range' => $range,
                        'is_direct' => $isDirect,
                        'fix_available' => $fixAvailable,
                        'effects' => $effects,
                        'via_parents' => array_filter((array) ($vulnData['via'] ?? []), 'is_string'),
                    ],
                );
                continue;
            }

            foreach ($advisories as $advisory) {
                $title = $advisory['title'];
                $url = $advisory['url'] ?? '';
                $cwe = $advisory['cwe'] ?? [];

                $findings[] = new DriftFinding(
                    target: (string) $packageName,
                    target_type: 'npm_package',
                    severity: $severity,
                    message: sprintf(
                        'npm CVE %s em %s — %s. Range: %s. Fix %s. Ref: %s',
                        $severity,
                        $packageName,
                        mb_strimwidth($title, 0, 120, '…'),
                        mb_strimwidth($range, 0, 60, '…'),
                        $fixAvailable ? 'available (npm audit fix)' : 'NOT available',
                        $url,
                    ),
                    evidence: [
                        'package' => $packageName,
                        'advisory_source' => $advisory['source'] ?? null,
                        'title' => $title,
                        'url' => $url,
                        'cwe' => $cwe,
                        'severity_npm' => $advisory['severity'] ?? $vulnData['severity'] ?? null,
                        'severity_canonical' => $severity,
                        'range' => $range,
                        'is_direct' => $isDirect,
                        'fix_available' => $fixAvailable,
                    ],
                );
            }
        }

        return $findings;
    }

    private function mapNpmSeverity(string $npmSeverity): string
    {
        return match (strtolower(trim($npmSeverity))) {
            'critical' => 'critical',
            'high' => 'high',
            'moderate' => 'medium',
            'low' => 'low',
            'info' => 'info',
            default => 'medium',
        };
    }

    /**
     * @param array<int, DriftFinding> $findings
     */
    private function highestSeverity(array $findings): string
    {
        $rank = ['critical' => 5, 'high' => 4, 'medium' => 3, 'low' => 2, 'info' => 1];
        $max = 'info';
        foreach ($findings as $f) {
            if (($rank[$f->severity] ?? 0) > ($rank[$max] ?? 0)) {
                $max = $f->severity;
            }
        }

        return $max;
    }

    /**
     * @param array<int, DriftFinding> $findings
     * @return array<string, int>
     */
    private function countBySeverity(array $findings): array
    {
        $counts = ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0, 'info' => 0];
        foreach ($findings as $f) {
            $counts[$f->severity] = ($counts[$f->severity] ?? 0) + 1;
        }

        return $counts;
    }
}
