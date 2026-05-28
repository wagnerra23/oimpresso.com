<?php

declare(strict_types=1);

namespace Modules\Governance\Services\Checkers;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Modules\Governance\Contracts\DriftChecker;
use Modules\Governance\Services\DriftCheckResult;
use Modules\Governance\Services\DriftFinding;

/**
 * ChartersFreshnessChecker — adapter ao `charter:audit` existente (ADR 0220).
 *
 * Princípio Constituição v2: "Charter > Spec" — charters precisam estar fresh
 * (last_validated dentro do TTL por tier: A=30d, B=60d, C=90d) e completos
 * (8 seções obrigatórias + 8 campos frontmatter).
 *
 * Adapter pattern (ADR 0216 §D2): NÃO duplica lógica do CharterAuditCommand
 * (407 linhas sofisticadas), apenas chama via Artisan::call e converte JSON
 * output em DriftFinding[]. Preserva back-compat 100%.
 *
 * Categorias detectadas:
 *   1. stale — last_validated > TTL (severity medium)
 *   2. invalid_frontmatter — falta 1+ dos 8 campos (severity medium)
 *   3. missing_sections — falta 1+ das 8 seções (severity low)
 *   4. tsx_without_charter — gap de cobertura (severity low, advisory)
 *
 * Severity baseline: medium · enforcement: warn (não bloqueia merge) · cadence: daily
 * Tags: tier_2, compliance, charter, ui_governance
 *
 * Refs:
 * - ADR 0220 mãe deste checker
 * - ADR 0101 Page Charter contract
 * - ADR 0094 §Charter > Spec
 * - CharterAuditCommand canon
 */
final class ChartersFreshnessChecker implements DriftChecker
{
    public function name(): string
    {
        return 'charters_freshness';
    }

    public function description(): string
    {
        return 'Detecta charters stale + frontmatter inválido + seções faltando + gap cobertura';
    }

    public function tags(): array
    {
        return ['tier_2', 'compliance', 'charter', 'ui_governance'];
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

        try {
            Artisan::call('charter:audit', ['--json' => true]);
            $output = Artisan::output();
            $report = json_decode($output, true);

            if (! is_array($report)) {
                Log::channel('single')->warning('charters_freshness — charter:audit JSON inválido', [
                    'output_preview' => mb_substr($output, 0, 200),
                ]);

                return DriftCheckResult::clean($this->name(), 0, ['error' => 'invalid_json']);
            }
        } catch (\Throwable $e) {
            Log::channel('single')->error('charters_freshness — charter:audit threw', [
                'exception' => $e->getMessage(),
            ]);

            return DriftCheckResult::clean($this->name(), 0, ['error' => $e->getMessage()]);
        }

        $findings = array_merge(
            $this->staleToFindings($report['stale'] ?? []),
            $this->invalidFrontmatterToFindings($report['invalid_frontmatter'] ?? []),
            $this->missingSectionsToFindings($report['missing_sections'] ?? []),
            $this->tsxWithoutCharterToFindings($report['tsx_without_charter'] ?? []),
        );

        $durationMs = (int) ((microtime(true) - $start) * 1000);

        if (count($findings) === 0) {
            return DriftCheckResult::clean(
                name: $this->name(),
                duration_ms: $durationMs,
                metadata: [
                    'charters_audited' => $report['total'] ?? 0,
                    'by_tier' => $report['by_tier'] ?? [],
                    'audited_at' => $report['audited_at'] ?? now()->toIso8601String(),
                ],
            );
        }

        return DriftCheckResult::drifted(
            name: $this->name(),
            findings: $findings,
            duration_ms: $durationMs,
            metadata: [
                'charters_audited' => $report['total'] ?? 0,
                'by_tier' => $report['by_tier'] ?? [],
                'audited_at' => $report['audited_at'] ?? now()->toIso8601String(),
                'category_counts' => [
                    'stale' => count($report['stale'] ?? []),
                    'invalid_frontmatter' => count($report['invalid_frontmatter'] ?? []),
                    'missing_sections' => count($report['missing_sections'] ?? []),
                    'tsx_without_charter' => count($report['tsx_without_charter'] ?? []),
                ],
            ],
        );
    }

    /**
     * @param array<int, array<string, mixed>> $stale
     * @return array<int, DriftFinding>
     */
    private function staleToFindings(array $stale): array
    {
        return array_map(
            fn (array $item) => new DriftFinding(
                target: (string) ($item['file'] ?? $item['page'] ?? 'unknown'),
                target_type: 'charter',
                severity: 'medium',
                message: sprintf(
                    'Charter stale: %s (tier %s, last_validated %s, %d dias > TTL %d). ' .
                    'Ação: revalidar conteúdo + atualizar last_validated no frontmatter.',
                    $item['file'] ?? $item['page'] ?? 'unknown',
                    $item['tier'] ?? '?',
                    $item['last_validated'] ?? '?',
                    (int) ($item['days_since_validated'] ?? 0),
                    (int) ($item['ttl_days'] ?? 0),
                ),
                evidence: [
                    'category' => 'stale',
                    'tier' => $item['tier'] ?? null,
                    'last_validated' => $item['last_validated'] ?? null,
                    'days_since_validated' => $item['days_since_validated'] ?? null,
                    'ttl_days' => $item['ttl_days'] ?? null,
                ],
            ),
            $stale,
        );
    }

    /**
     * @param array<int, array<string, mixed>> $invalid
     * @return array<int, DriftFinding>
     */
    private function invalidFrontmatterToFindings(array $invalid): array
    {
        return array_map(
            fn (array $item) => new DriftFinding(
                target: (string) ($item['file'] ?? 'unknown'),
                target_type: 'charter',
                severity: 'medium',
                message: sprintf(
                    'Charter frontmatter inválido: %s. Campos faltando: %s. ' .
                    'Ação: adicionar campos obrigatórios no YAML.',
                    $item['file'] ?? 'unknown',
                    implode(', ', (array) ($item['missing'] ?? [])),
                ),
                evidence: [
                    'category' => 'invalid_frontmatter',
                    'missing_fields' => $item['missing'] ?? [],
                ],
            ),
            $invalid,
        );
    }

    /**
     * @param array<int, array<string, mixed>> $missing
     * @return array<int, DriftFinding>
     */
    private function missingSectionsToFindings(array $missing): array
    {
        return array_map(
            fn (array $item) => new DriftFinding(
                target: (string) ($item['file'] ?? 'unknown'),
                target_type: 'charter',
                severity: 'low',
                message: sprintf(
                    'Charter seções faltando: %s. Seções: %s. ' .
                    'Ação: adicionar headers ## faltantes (conteúdo pode ser "—" placeholder).',
                    $item['file'] ?? 'unknown',
                    implode(', ', (array) ($item['missing'] ?? [])),
                ),
                evidence: [
                    'category' => 'missing_sections',
                    'missing_sections' => $item['missing'] ?? [],
                ],
            ),
            $missing,
        );
    }

    /**
     * @param array<int, string> $gap
     * @return array<int, DriftFinding>
     */
    private function tsxWithoutCharterToFindings(array $gap): array
    {
        return array_map(
            fn (string $tsxPath) => new DriftFinding(
                target: $tsxPath,
                target_type: 'tsx_without_charter',
                severity: 'low',
                message: sprintf(
                    'Página Inertia sem charter ao lado: %s. ' .
                    'Ação: gerar via `php artisan charter:write %s` OR aceitar gap se Tier C low-traffic.',
                    $tsxPath,
                    pathinfo($tsxPath, PATHINFO_FILENAME),
                ),
                evidence: [
                    'category' => 'tsx_without_charter',
                    'tsx_path' => $tsxPath,
                ],
            ),
            $gap,
        );
    }
}
