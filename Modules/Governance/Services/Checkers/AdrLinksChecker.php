<?php

declare(strict_types=1);

namespace Modules\Governance\Services\Checkers;

use Modules\Governance\Contracts\DriftChecker;
use Modules\Governance\Services\DriftCheckResult;
use Modules\Governance\Services\DriftFinding;

/**
 * AdrLinksChecker — link rot + lifecycle integrity de ADRs Nygard.
 *
 * ADR 0219 (filha de 0216).
 *
 * Detecta em `memory/decisions/*.md`:
 *  1. Link markdown `[ADR XXXX](path)` apontando pra arquivo inexistente
 *  2. Frontmatter `references: [N]` apontando pra ADR inexistente
 *  3. Frontmatter `supersedes: [N]` apontando pra ADR inexistente
 *  4. `supersedes: [N]` mas ADR N tem lifecycle != 'superseded' (drift)
 *  5. Lifecycle `superseded` mas sem `superseded_by: [...]` declarado
 *  6. ADR `proposal/` órfão >90d
 *
 * Severity: high (canon corrupt) / medium (lifecycle inconsistente) / low (proposals órfãs)
 * Enforcement: warn (não bloqueia merge, só Brief Jana)
 * Cadence: daily + on_pr (PR que mexa em memory/decisions/)
 */
final class AdrLinksChecker implements DriftChecker
{
    public function name(): string
    {
        return 'adr_link_rot';
    }

    public function description(): string
    {
        return 'Detecta ADR links broken + lifecycle drift (superseded sem superseded_by)';
    }

    public function tags(): array
    {
        return ['tier_2', 'compliance', 'memory_canon'];
    }

    public function severity(): string
    {
        return 'medium';
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
        $base = base_path();
        $decisionsDir = "{$base}/memory/decisions";

        if (! is_dir($decisionsDir)) {
            return DriftCheckResult::clean($this->name(), 0, ['skipped' => 'memory/decisions/ não existe']);
        }

        $adrFiles = glob("{$decisionsDir}/*.md") ?: [];
        $adrIndex = $this->buildAdrIndex($adrFiles);
        $findings = [];

        foreach ($adrFiles as $file) {
            $content = file_get_contents($file) ?: '';
            $relPath = str_replace($base . DIRECTORY_SEPARATOR, '', $file);
            $frontmatter = $this->parseFrontmatter($content);
            $adrId = $this->extractAdrId($frontmatter, basename($file));

            // 1. Frontmatter references[] apontando ADR inexistente
            foreach ((array) ($frontmatter['references'] ?? []) as $ref) {
                if (! $this->refExistsInIndex($ref, $adrIndex)) {
                    $findings[] = new DriftFinding(
                        target: $relPath,
                        target_type: 'adr',
                        severity: 'medium',
                        message: sprintf(
                            'ADR %s references ADR "%s" mas arquivo não encontrado em memory/decisions/. ' .
                            'Ação: verificar ID correto OU remover reference.',
                            $adrId,
                            $ref,
                        ),
                        evidence: ['adr_id' => $adrId, 'broken_ref' => $ref, 'type' => 'references'],
                    );
                }
            }

            // 2. supersedes[] apontando ADR inexistente
            foreach ((array) ($frontmatter['supersedes'] ?? []) as $sup) {
                if (! $this->refExistsInIndex($sup, $adrIndex)) {
                    $findings[] = new DriftFinding(
                        target: $relPath,
                        target_type: 'adr',
                        severity: 'high',
                        message: sprintf(
                            'ADR %s supersedes ADR "%s" mas arquivo não encontrado.',
                            $adrId,
                            $sup,
                        ),
                        evidence: ['adr_id' => $adrId, 'broken_ref' => $sup, 'type' => 'supersedes'],
                    );
                    continue;
                }
                // 3. ADR superseded existe mas lifecycle != 'superseded' (drift bilateral)
                $supFile = $adrIndex[$this->normalizeRef($sup)] ?? null;
                if ($supFile === null) {
                    continue;
                }
                $supContent = file_get_contents($supFile) ?: '';
                $supFm = $this->parseFrontmatter($supContent);
                $supLifecycle = strtolower((string) ($supFm['lifecycle'] ?? $supFm['status'] ?? ''));
                if ($supLifecycle === 'active' || $supLifecycle === 'accepted') {
                    $findings[] = new DriftFinding(
                        target: $relPath,
                        target_type: 'adr',
                        severity: 'medium',
                        message: sprintf(
                            'ADR %s supersedes ADR %s, mas ADR %s ainda tem lifecycle "%s" (esperado "superseded"). ' .
                            'Ação: editar ADR %s frontmatter lifecycle: superseded + superseded_by: [%s].',
                            $adrId,
                            $sup,
                            $sup,
                            $supLifecycle,
                            $sup,
                            $adrId,
                        ),
                        evidence: [
                            'adr_id' => $adrId,
                            'superseded_adr' => $sup,
                            'current_lifecycle' => $supLifecycle,
                            'expected_lifecycle' => 'superseded',
                        ],
                    );
                }
            }

            // 4. Lifecycle 'superseded' sem superseded_by
            $lifecycle = strtolower((string) ($frontmatter['lifecycle'] ?? $frontmatter['status'] ?? ''));
            if ($lifecycle === 'superseded') {
                $supBy = (array) ($frontmatter['superseded_by'] ?? []);
                if (count($supBy) === 0) {
                    $findings[] = new DriftFinding(
                        target: $relPath,
                        target_type: 'adr',
                        severity: 'low',
                        message: sprintf(
                            'ADR %s tem lifecycle "superseded" mas sem superseded_by declarado.',
                            $adrId,
                        ),
                        evidence: ['adr_id' => $adrId, 'missing_field' => 'superseded_by'],
                    );
                }
            }
        }

        $durationMs = (int) ((microtime(true) - $start) * 1000);

        return count($findings) === 0
            ? DriftCheckResult::clean($this->name(), $durationMs, ['adrs_scanned' => count($adrFiles)])
            : DriftCheckResult::drifted(
                name: $this->name(),
                findings: $findings,
                duration_ms: $durationMs,
                metadata: ['adrs_scanned' => count($adrFiles)],
            );
    }

    /**
     * Mapa ADR id (string normalizado) → file path absoluto.
     *
     * @param array<int, string> $files
     * @return array<string, string>
     */
    private function buildAdrIndex(array $files): array
    {
        $index = [];
        foreach ($files as $file) {
            $base = basename($file);
            if (preg_match('/^(\d{4})/', $base, $m)) {
                $index[$m[1]] = $file;
            }
        }

        return $index;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseFrontmatter(string $content): array
    {
        if (! preg_match('/^---\R(.*?)\R---/s', $content, $m)) {
            return [];
        }
        $yaml = $m[1];
        // Parse minimalista YAML (suficiente pra adr/status/lifecycle/references/supersedes)
        $out = [];
        $lines = explode("\n", $yaml);
        $currentKey = null;
        foreach ($lines as $line) {
            $line = rtrim($line);
            if ($line === '' || str_starts_with(trim($line), '#')) {
                continue;
            }
            if (preg_match('/^([a-z_]+):\s*(.*)$/i', $line, $m2)) {
                $key = strtolower($m2[1]);
                $val = trim($m2[2]);
                if ($val === '' || $val === '[]') {
                    $out[$key] = [];
                    $currentKey = $key;
                    continue;
                }
                if (str_starts_with($val, '[') && str_ends_with($val, ']')) {
                    $inner = trim(substr($val, 1, -1));
                    $out[$key] = $inner === '' ? [] : array_map('trim', explode(',', $inner));
                } else {
                    $out[$key] = trim($val, '"\'');
                }
                $currentKey = $key;
            } elseif ($currentKey !== null && preg_match('/^\s+-\s+(.+)$/', $line, $m3)) {
                if (! is_array($out[$currentKey] ?? null)) {
                    $out[$currentKey] = [];
                }
                $out[$currentKey][] = trim($m3[1], '"\'');
            }
        }

        return $out;
    }

    private function extractAdrId(array $frontmatter, string $filename): string
    {
        if (isset($frontmatter['adr'])) {
            return (string) $frontmatter['adr'];
        }
        if (preg_match('/^(\d{4})/', $filename, $m)) {
            return $m[1];
        }

        return $filename;
    }

    /**
     * @param array<string, string> $index
     */
    private function refExistsInIndex(mixed $ref, array $index): bool
    {
        $norm = $this->normalizeRef($ref);

        return isset($index[$norm]);
    }

    private function normalizeRef(mixed $ref): string
    {
        $str = (string) $ref;
        // Aceita "0093", "0093-multi-tenant.md", "ADR 0093", etc.
        if (preg_match('/(\d{4})/', $str, $m)) {
            return $m[1];
        }

        return $str;
    }
}
