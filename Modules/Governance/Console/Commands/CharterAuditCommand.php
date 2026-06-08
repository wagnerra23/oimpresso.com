<?php

declare(strict_types=1);

namespace Modules\Governance\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Yaml\Yaml;

/**
 * Auditoria de Page Charters em prod.
 *
 * Escaneia `resources/js/Pages/**\/*.charter.md` (e, no futuro, Blade Tier C),
 * parseia frontmatter + estrutura de seções e reporta:
 *   - Total de charters por tier (A/B/C)
 *   - Charters stale (last_validated > limite por tier — A=30d, B=60d, C=90d)
 *   - Charters sem owner ou sem 8 seções obrigatórias
 *   - Telas .tsx sem charter (gap de cobertura)
 *
 * Sprint S6 F2 ([ADR 0101]). Spec em memory/sprints/s6-charter-capterra/02-charter-fetch-tool.md.
 *
 * Uso:
 *   php artisan charter:audit
 *   php artisan charter:audit --json
 *   php artisan charter:audit --module=Repair
 */
class CharterAuditCommand extends Command
{
    protected $signature = 'charter:audit
                            {--json : Output JSON em vez de tabela}
                            {--module= : Filtra por módulo (PascalCase)}';

    protected $description = 'Audita Page Charters: cobertura, drift, estrutura';

    private const STALE_DAYS = ['A' => 30, 'B' => 60, 'C' => 90];

    private const REQUIRED_FRONTMATTER = [
        'page', 'component', 'owner', 'status',
        'last_validated', 'parent_module', 'tier', 'charter_version',
    ];

    private const REQUIRED_SECTIONS = [
        'Mission', 'Goals', 'Non-Goals', 'UX Targets',
        'UX Anti-patterns', 'Automation Hooks', 'Automation Anti-hooks', 'Métricas vivas',
    ];

    public function handle(): int
    {
        $charters = $this->discoverCharters();
        $module = $this->option('module');

        if ($module !== null) {
            $charters = array_filter(
                $charters,
                fn ($c) => ($c['frontmatter']['parent_module'] ?? null) === $module,
            );
        }

        $report = [
            'audited_at' => now()->toIso8601String(),
            'total' => count($charters),
            'by_tier' => $this->groupByTier($charters),
            'stale' => $this->detectStale($charters),
            'invalid_frontmatter' => $this->detectInvalidFrontmatter($charters),
            'missing_sections' => $this->detectMissingSections($charters),
            'tsx_without_charter' => $this->detectGap(),
        ];

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->renderTable($report);
        }

        $hasIssues = count($report['stale']) > 0
            || count($report['invalid_frontmatter']) > 0
            || count($report['missing_sections']) > 0;

        return $hasIssues ? self::FAILURE : self::SUCCESS;
    }

    private function discoverCharters(): array
    {
        $files = $this->findFiles('resources/js/Pages', '.charter.md');

        $out = [];
        foreach ($files as $path) {
            $raw = (string) file_get_contents($path);
            $out[] = [
                'path' => $path,
                'frontmatter' => $this->parseFrontmatter($raw),
                'sections' => $this->parseSections($raw),
                'raw' => $raw,
            ];
        }

        return $out;
    }

    private function parseFrontmatter(string $raw): array
    {
        if (! preg_match('/^---\n(.+?)\n---/s', $raw, $m)) {
            return [];
        }

        try {
            return (array) Yaml::parse($m[1]);
        } catch (\Throwable) {
            return [];
        }
    }

    private function parseSections(string $raw): array
    {
        preg_match_all('/^## (.+)$/m', $raw, $m);
        return array_map('trim', $m[1] ?? []);
    }

    private function groupByTier(array $charters): array
    {
        $out = ['A' => 0, 'B' => 0, 'C' => 0];
        foreach ($charters as $c) {
            $tier = $c['frontmatter']['tier'] ?? 'unknown';
            if (isset($out[$tier])) {
                $out[$tier]++;
            }
        }
        return $out;
    }

    private function detectStale(array $charters): array
    {
        $out = [];
        foreach ($charters as $c) {
            $tier = $c['frontmatter']['tier'] ?? 'B';
            $limit = self::STALE_DAYS[$tier] ?? 60;
            $validated = $c['frontmatter']['last_validated'] ?? null;
            if ($validated === null) {
                continue;
            }
            $days = now()->diffInDays($validated);
            if ($days > $limit) {
                $out[] = [
                    'path' => $c['path'],
                    'tier' => $tier,
                    'days_stale' => $days,
                    'limit' => $limit,
                ];
            }
        }
        return $out;
    }

    private function detectInvalidFrontmatter(array $charters): array
    {
        $out = [];
        foreach ($charters as $c) {
            $missing = array_diff(self::REQUIRED_FRONTMATTER, array_keys($c['frontmatter']));
            if (count($missing) > 0) {
                $out[] = [
                    'path' => $c['path'],
                    'missing_keys' => array_values($missing),
                ];
            }
        }
        return $out;
    }

    private function detectMissingSections(array $charters): array
    {
        $out = [];
        foreach ($charters as $c) {
            $missing = array_diff(self::REQUIRED_SECTIONS, $c['sections']);
            if (count($missing) > 0) {
                $out[] = [
                    'path' => $c['path'],
                    'missing_sections' => array_values($missing),
                ];
            }
        }
        return $out;
    }

    private function detectGap(): array
    {
        $tsxFiles = $this->findFiles('resources/js/Pages', 'Index.tsx');
        $charterFiles = $this->findFiles('resources/js/Pages', '.charter.md');

        $charterDirs = array_map(fn ($p) => dirname($p), $charterFiles);
        $charterDirs = array_flip($charterDirs);

        $out = [];
        foreach ($tsxFiles as $tsx) {
            if (! isset($charterDirs[dirname($tsx)])) {
                $out[] = $tsx;
            }
        }
        return $out;
    }

    private function renderTable(array $report): void
    {
        $this->info("Charter Audit — {$report['audited_at']}");
        $this->line("Total: {$report['total']} (A={$report['by_tier']['A']} B={$report['by_tier']['B']} C={$report['by_tier']['C']})");
        $this->line('Stale: ' . count($report['stale']));
        $this->line('Frontmatter invalid: ' . count($report['invalid_frontmatter']));
        $this->line('Sections missing: ' . count($report['missing_sections']));
        $this->line('Telas .tsx sem charter: ' . count($report['tsx_without_charter']));

        if (count($report['stale']) > 0) {
            $this->warn('Stale charters:');
            foreach ($report['stale'] as $s) {
                $this->line("  - {$s['path']} (tier {$s['tier']}, {$s['days_stale']}d > {$s['limit']}d)");
            }
        }
    }

    private function findFiles(string $dir, string $endsWith): array
    {
        $base = base_path($dir);
        if (! is_dir($base)) {
            return [];
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS)
        );

        $out = [];
        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), $endsWith)) {
                $out[] = $file->getPathname();
            }
        }
        return $out;
    }
}
