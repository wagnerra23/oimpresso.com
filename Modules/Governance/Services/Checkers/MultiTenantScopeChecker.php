<?php

declare(strict_types=1);

namespace Modules\Governance\Services\Checkers;

use Modules\Governance\Contracts\DriftChecker;
use Modules\Governance\Services\DriftCheckResult;
use Modules\Governance\Services\DriftFinding;

/**
 * MultiTenantScopeChecker — Tier 0 IRREVOGÁVEL ADR 0093.
 *
 * Princípio 6 Constituição v2: "Multi-tenant Tier 0 IRREVOGÁVEL".
 *
 * ADR 0218 (filha de 0216). Empiricamente justificado: postgres RLS CVEs
 * 2024-10976/2025-8713 mostraram que defesa DB-level falha. Defesa em
 * profundidade: AST scan de Eloquent Models procurando *ausência* de:
 *   - `use HasBusinessScope;` (App\Concerns\HasBusinessScope — global scope automático)
 *   - `use BelongsToBusinessViaParent;` (App\Concerns\BelongsToBusinessViaParent — herda biz do parent)
 *
 * Detecta:
 *   - Models em `Modules/*​/Entities/*.php` ou `Modules/*​/Models/*.php` sem 1 dos 2 traits
 *   - Models que estendem Eloquent\Model E não estão em allowlist de "globais legítimos"
 *
 * Allowlist `config/governance.php > multi_tenant_scope_allowlist[]`:
 *   - Models system-wide (User, Business, Module, ScorecardRule, etc.)
 *   - Models read-only de catálogo (Country, State, City, PaymentMethod)
 *
 * Severity: critical (Tier 0)
 * Enforcement: block (CI gate + pre-commit)
 * Cadence: on_pr + daily
 *
 * Modo diff_only: lê staged files via `git diff --cached --name-only` filtra
 * só *.php em Modules/​*​/Entities ou Modules/​*​/Models.
 */
final class MultiTenantScopeChecker implements DriftChecker
{
    private const MULTI_TENANT_TRAITS = [
        'App\Concerns\HasBusinessScope',
        'App\Concerns\BelongsToBusinessViaParent',
    ];

    public function name(): string
    {
        return 'multi_tenant_scope';
    }

    public function description(): string
    {
        return 'Verifica Models Eloquent multi-tenant Tier 0 (HasBusinessScope/BelongsToBusinessViaParent)';
    }

    public function tags(): array
    {
        return ['tier_0', 'security', 'multi_tenant', 'compliance'];
    }

    public function severity(): string
    {
        return 'critical';
    }

    public function enforcement(): string
    {
        return 'block';
    }

    public function cadence(): string
    {
        return 'daily'; // + on_pr via CI; pre-commit roda --diff-only
    }

    public function check(array $opts = []): DriftCheckResult
    {
        $start = microtime(true);
        $diffOnly = (bool) ($opts['diff_only'] ?? false);

        $modelFiles = $diffOnly ? $this->stagedModelFiles() : $this->scanModelFiles();
        $allowlist = $this->loadAllowlist();
        $findings = [];

        foreach ($modelFiles as $relPath) {
            $absPath = base_path($relPath);
            if (! is_readable($absPath)) {
                continue;
            }
            $content = file_get_contents($absPath);
            if ($content === false || ! $this->extendsEloquentModel($content)) {
                continue;
            }

            $fqcn = $this->extractFqcn($content, $relPath);
            if ($fqcn && in_array($fqcn, $allowlist, true)) {
                continue;
            }

            if ($this->hasMultiTenantTrait($content)) {
                continue;
            }

            $findings[] = new DriftFinding(
                target: $relPath,
                target_type: 'eloquent_model',
                severity: 'critical',
                message: sprintf(
                    'Model %s sem HasBusinessScope/BelongsToBusinessViaParent (Tier 0 ADR 0093). ' .
                    'Ação: adicionar `use App\Concerns\HasBusinessScope;` no model + `use HasBusinessScope;` ' .
                    'no body, OU declarar exception em config/governance.php > multi_tenant_scope_allowlist.',
                    $fqcn ?? basename($relPath),
                ),
                evidence: [
                    'fqcn' => $fqcn,
                    'file' => $relPath,
                    'allowlist_size' => count($allowlist),
                    'detected_at' => now()->toIso8601String(),
                ],
            );
        }

        $durationMs = (int) ((microtime(true) - $start) * 1000);

        return count($findings) === 0
            ? DriftCheckResult::clean($this->name(), $durationMs, ['models_scanned' => count($modelFiles)])
            : DriftCheckResult::drifted(
                name: $this->name(),
                findings: $findings,
                duration_ms: $durationMs,
                metadata: [
                    'models_scanned' => count($modelFiles),
                    'allowlist_size' => count($allowlist),
                ],
            );
    }

    /**
     * @return array<int, string> caminhos relativos Modules/*​/Entities/*.php + Modules/*​/Models/*.php
     */
    private function scanModelFiles(): array
    {
        $base = base_path();
        $files = [];

        foreach (['Entities', 'Models'] as $dir) {
            $pattern = "{$base}/Modules/*/{$dir}/*.php";
            foreach (glob($pattern) ?: [] as $abs) {
                $files[] = str_replace($base . DIRECTORY_SEPARATOR, '', $abs);
            }
            // Subdiretórios profundos (ex Jana/Entities/Mcp/*.php)
            $pattern2 = "{$base}/Modules/*/{$dir}/*/*.php";
            foreach (glob($pattern2) ?: [] as $abs) {
                $files[] = str_replace($base . DIRECTORY_SEPARATOR, '', $abs);
            }
        }

        return array_values(array_unique($files));
    }

    /**
     * @return array<int, string>
     */
    private function stagedModelFiles(): array
    {
        $cmd = 'git diff --cached --name-only --diff-filter=AM 2>&1';
        $output = shell_exec($cmd) ?: '';
        $lines = array_filter(explode("\n", trim($output)));
        $filtered = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (preg_match('#^Modules/[^/]+/(Entities|Models)/.*\.php$#', $line)) {
                $filtered[] = $line;
            }
        }

        return $filtered;
    }

    private function extendsEloquentModel(string $content): bool
    {
        return (bool) preg_match(
            '/class\s+\w+\s+extends\s+(?:\\\\)?(?:Illuminate\\\\Database\\\\Eloquent\\\\)?Model\b/',
            $content,
        );
    }

    private function hasMultiTenantTrait(string $content): bool
    {
        foreach (self::MULTI_TENANT_TRAITS as $fqcn) {
            $short = substr($fqcn, strrpos($fqcn, '\\') + 1);
            // use FQCN; OU use ShortName; (inside class body)
            if (preg_match('/use\s+' . preg_quote($short, '/') . '\s*[;,]/', $content)) {
                return true;
            }
            $fqEscaped = preg_quote('\\' . $fqcn, '/');
            if (preg_match('/use\s+' . ltrim($fqEscaped, '\\\\') . '\s*;/', $content)) {
                return true;
            }
        }

        return false;
    }

    private function extractFqcn(string $content, string $relPath): ?string
    {
        $namespace = null;
        if (preg_match('/^namespace\s+([^;]+);/m', $content, $m)) {
            $namespace = trim($m[1]);
        }
        if (! preg_match('/class\s+(\w+)\s+/', $content, $m)) {
            return null;
        }
        $className = $m[1];

        return $namespace ? "{$namespace}\\{$className}" : $className;
    }

    /**
     * @return array<int, string>
     */
    private function loadAllowlist(): array
    {
        return array_values(array_filter(
            (array) config('governance.multi_tenant_scope_allowlist', []),
            'is_string',
        ));
    }
}
