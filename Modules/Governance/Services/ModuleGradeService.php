<?php

declare(strict_types=1);

namespace Modules\Governance\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Collection;
use Symfony\Component\Yaml\Yaml;

/**
 * ModuleGradeService — implementa rubrica oficial `module-grade-v1` (ADR 0153).
 *
 * Avalia maturidade de qualquer Modules/<X>/ via 5 dimensões ponderadas:
 *   D1 Multi-tenant Tier 0  (30 pts) — BusinessScope + cross-tenant Pest + Jobs $businessId
 *   D2 Pest cobertura       (20 pts) — razao tests/Controllers + canônicos + phpunit.xml
 *   D3 Documentação canon   (15 pts) — SPEC + BRIEFING + Charter + ADR mãe
 *   D4 Maturidade arq       (20 pts) — Service/Controller ratio + FSM + Inertia + AuditLog
 *   D5 Cliente real         (15 pts) — biz=4 prod / biz=1 Wagner / piloto / hipotese / ninguem
 *
 * Total normalizado: 0-100. Buckets: Excelente 80+ / Bom 60-79 / Médio 40-59 / Crítico 20-39 / Embrião <20.
 *
 * Coleta automática via filesystem inspection. D5 (cliente) lê config/governance/module_clients.yaml
 * (Wagner edita manualmente — quando Modules/Brief gerar volume/módulo vira automático).
 *
 * @see memory/decisions/0153-module-grade-rubrica-v1.md
 */
class ModuleGradeService
{
    private const BUCKETS = [
        ['min' => 80, 'label' => 'Excelente', 'color' => 'emerald'],
        ['min' => 60, 'label' => 'Bom',       'color' => 'sky'],
        ['min' => 40, 'label' => 'Médio',     'color' => 'amber'],
        ['min' => 20, 'label' => 'Crítico',   'color' => 'orange'],
        ['min' => 0,  'label' => 'Embrião',   'color' => 'red'],
    ];

    private string $modulesPath;
    private string $memoryPath;
    private string $pagesPath;
    private string $viewsPath;
    private string $phpunitXmlPath;
    private string $clientsConfigPath;

    public function __construct()
    {
        $this->modulesPath       = base_path('Modules');
        $this->memoryPath        = base_path('memory/requisitos');
        $this->pagesPath         = base_path('resources/js/Pages');
        $this->viewsPath         = base_path('resources/views');
        $this->phpunitXmlPath    = base_path('phpunit.xml');
        $this->clientsConfigPath = base_path('config/governance/module_clients.yaml');
    }

    /**
     * Avalia um único módulo.
     *
     * @return array{module: string, score: int, bucket: string, color: string, dimensions: array, gaps: array, evolve_tasks: array, evaluated_at: string}
     */
    public function gradeModule(string $name): array
    {
        $modulePath = $this->modulesPath . DIRECTORY_SEPARATOR . $name;

        if (! is_dir($modulePath)) {
            throw new \InvalidArgumentException("Módulo `{$name}` não existe em Modules/");
        }

        $d1 = $this->dim1MultiTenant($name, $modulePath);
        $d2 = $this->dim2PestCoverage($name, $modulePath);
        $d3 = $this->dim3Documentation($name, $modulePath);
        $d4 = $this->dim4Architecture($name, $modulePath);
        $d5 = $this->dim5Client($name);

        $score = $d1['score'] + $d2['score'] + $d3['score'] + $d4['score'] + $d5['score'];
        $bucket = $this->bucketFor($score);

        $gaps = $this->extractGaps($name, [
            'multi_tenant'  => $d1,
            'pest_coverage' => $d2,
            'documentation' => $d3,
            'architecture'  => $d4,
            'client_real'   => $d5,
        ]);

        return [
            'module'        => $name,
            'score'         => $score,
            'bucket'        => $bucket['label'],
            'color'         => $bucket['color'],
            'dimensions'    => [
                'multi_tenant'  => $d1,
                'pest_coverage' => $d2,
                'documentation' => $d3,
                'architecture'  => $d4,
                'client_real'   => $d5,
            ],
            'gaps'          => $gaps,
            'evolve_tasks'  => $this->suggestEvolveTasks($name, $gaps),
            'evaluated_at'  => now()->toIso8601String(),
        ];
    }

    /**
     * Avalia todos os módulos detectados em Modules/.
     *
     * @return Collection<int, array>
     */
    public function gradeAllModules(): Collection
    {
        $modules = collect(scandir($this->modulesPath))
            ->filter(fn ($d) => $d !== '.' && $d !== '..' && is_dir($this->modulesPath . DIRECTORY_SEPARATOR . $d))
            ->values();

        return $modules->map(fn ($m) => $this->gradeModule($m))->sortByDesc('score')->values();
    }

    // ────────────────────────────────────────────────────────────────────────
    // D1 — Multi-tenant Tier 0 (30 pts)
    // ────────────────────────────────────────────────────────────────────────

    private function dim1MultiTenant(string $name, string $modulePath): array
    {
        $breakdown = [];

        // D1.a — BusinessScope global em Entities críticas (10 pts)
        $entitiesPath = $modulePath . '/Entities';
        $modelsPath   = $modulePath . '/Models';
        $entities = array_merge(
            $this->phpFiles($entitiesPath),
            $this->phpFiles($modelsPath)
        );
        $businessScopeCount = 0;
        foreach ($entities as $file) {
            $content = @file_get_contents($file) ?: '';
            if (preg_match('/(BusinessScope|addGlobalScope.*business)/i', $content)) {
                $businessScopeCount++;
            }
        }
        $d1a = count($entities) === 0
            ? 5  // sem entities — neutro (módulo pode usar Models core)
            : (int) round(min(10, ($businessScopeCount / max(1, count($entities))) * 10));
        $breakdown[] = [
            'key'   => 'D1.a',
            'desc'  => 'BusinessScope global em Entities críticas',
            'score' => $d1a,
            'max'   => 10,
            'evidence' => count($entities) === 0
                ? 'sem Entities próprias (neutro)'
                : "{$businessScopeCount}/" . count($entities) . " Entities com BusinessScope",
        ];

        // D1.b — Cross-tenant Pest cobrindo ≥50% Entities (15 pts)
        $testsPath = $modulePath . '/Tests';
        $testFiles = $this->phpFiles($testsPath, recursive: true);
        $crossTenantTestFiles = 0;
        foreach ($testFiles as $file) {
            $content = @file_get_contents($file) ?: '';
            if (preg_match('/(biz=99|BIZ_FICTICIO|business_id.*99|withoutGlobalScopes)/', $content)) {
                $crossTenantTestFiles++;
            }
        }
        $entCount = count($entities);
        $d1b = $entCount === 0
            ? ($crossTenantTestFiles > 0 ? 10 : 0)
            : (int) round(min(15, ($crossTenantTestFiles / max(1, $entCount * 0.5)) * 15));
        $breakdown[] = [
            'key'   => 'D1.b',
            'desc'  => 'Cross-tenant Pest biz=1 vs biz=99',
            'score' => $d1b,
            'max'   => 15,
            'evidence' => "{$crossTenantTestFiles} test files com cross-tenant pattern",
        ];

        // D1.c — Jobs assíncronos $businessId no constructor (5 pts)
        $jobsPath = $modulePath . '/Jobs';
        $jobFiles = $this->phpFiles($jobsPath);
        $jobsBusinessId = 0;
        foreach ($jobFiles as $file) {
            $content = @file_get_contents($file) ?: '';
            if (preg_match('/__construct\s*\([^)]*\$business/', $content)) {
                $jobsBusinessId++;
            }
        }
        $d1c = count($jobFiles) === 0
            ? 5  // sem Jobs — neutro
            : (int) round(min(5, ($jobsBusinessId / max(1, count($jobFiles))) * 5));
        $breakdown[] = [
            'key'   => 'D1.c',
            'desc'  => 'Jobs assíncronos recebem $businessId no constructor',
            'score' => $d1c,
            'max'   => 5,
            'evidence' => count($jobFiles) === 0
                ? 'sem Jobs (neutro)'
                : "{$jobsBusinessId}/" . count($jobFiles) . " Jobs com \$businessId",
        ];

        return [
            'weight'    => 30,
            'score'     => $d1a + $d1b + $d1c,
            'max'       => 30,
            'breakdown' => $breakdown,
        ];
    }

    // ────────────────────────────────────────────────────────────────────────
    // D2 — Pest cobertura (20 pts)
    // ────────────────────────────────────────────────────────────────────────

    private function dim2PestCoverage(string $name, string $modulePath): array
    {
        $breakdown = [];

        $controllers = $this->phpFiles($modulePath . '/Http/Controllers');
        $testsPath = $modulePath . '/Tests';
        $testFiles = array_filter(
            $this->phpFiles($testsPath, recursive: true),
            fn ($f) => ! str_ends_with($f, 'Pest.php') && ! str_ends_with($f, 'TestCase.php')
        );

        // D2.a — Razão tests/Controllers ≥ 0.5 (8 pts)
        $ratio = count($controllers) === 0 ? 1.0 : count($testFiles) / count($controllers);
        $d2a = (int) round(min(8, $ratio * 16));  // ratio 0.5 → 8 pts
        $breakdown[] = [
            'key'   => 'D2.a',
            'desc'  => 'Razão tests/Controllers ≥ 0.5',
            'score' => $d2a,
            'max'   => 8,
            'evidence' => sprintf('%d tests / %d controllers (ratio %.2f)', count($testFiles), count($controllers), $ratio),
        ];

        // D2.b — Tem MultiTenant + Smoke + Scaffold canônicos (8 pts)
        $canonicalCount = 0;
        $patterns = ['MultiTenant', 'Smoke', 'Scaffold'];
        foreach ($patterns as $p) {
            foreach ($testFiles as $f) {
                if (str_contains($f, $p)) {
                    $canonicalCount++;
                    break;
                }
            }
        }
        $d2b = (int) round(($canonicalCount / 3) * 8);
        $breakdown[] = [
            'key'   => 'D2.b',
            'desc'  => 'Tem MultiTenant + Smoke + Scaffold canônicos',
            'score' => $d2b,
            'max'   => 8,
            'evidence' => "{$canonicalCount}/3 padrões canônicos presentes",
        ];

        // D2.c — Registrado em phpunit.xml CI (4 pts)
        $phpunit = file_exists($this->phpunitXmlPath) ? file_get_contents($this->phpunitXmlPath) : '';
        $isRegistered = str_contains($phpunit, "./Modules/{$name}/Tests");
        $d2c = $isRegistered ? 4 : 0;
        $breakdown[] = [
            'key'   => 'D2.c',
            'desc'  => 'Registrado em phpunit.xml CI',
            'score' => $d2c,
            'max'   => 4,
            'evidence' => $isRegistered ? 'sim — Tests em phpunit.xml' : 'NÃO REGISTRADO — falsa cobertura',
        ];

        return [
            'weight'    => 20,
            'score'     => $d2a + $d2b + $d2c,
            'max'       => 20,
            'breakdown' => $breakdown,
        ];
    }

    // ────────────────────────────────────────────────────────────────────────
    // D3 — Documentação canônica (15 pts)
    // ────────────────────────────────────────────────────────────────────────

    private function dim3Documentation(string $name, string $modulePath): array
    {
        $breakdown = [];

        $specPath = $this->memoryPath . "/{$name}/SPEC.md";
        $briefingPath = $this->memoryPath . "/{$name}/BRIEFING.md";
        // Resolve case-insensitive — Linux Hostinger é case-sensitive, Windows local não.
        // Convenção real do oimpresso é inconsistente (governance/ vs Crm/ vs Vestuario/).
        $pagesModulePath = $this->resolveCaseInsensitivePagesPath($name) ?? ($this->pagesPath . "/{$name}");

        // D3.a — SPEC.md (5 pts)
        $d3a = file_exists($specPath) ? 5 : 0;
        $breakdown[] = [
            'key' => 'D3.a', 'desc' => 'memory/requisitos/<X>/SPEC.md',
            'score' => $d3a, 'max' => 5,
            'evidence' => $d3a ? 'sim' : 'ausente',
        ];

        // D3.b — BRIEFING.md atualizado ≤90d (5 pts)
        $d3b = 0;
        $briefingEvidence = 'ausente';
        if (file_exists($briefingPath)) {
            $age = (time() - filemtime($briefingPath)) / 86400;
            $d3b = $age <= 90 ? 5 : 2;
            $briefingEvidence = sprintf('idade %.0fd', $age);
        }
        $breakdown[] = [
            'key' => 'D3.b', 'desc' => 'BRIEFING.md 1-pager ≤90d',
            'score' => $d3b, 'max' => 5,
            'evidence' => $briefingEvidence,
        ];

        // D3.c — Charter ≥30% telas (3 pts)
        $tsxFiles = $this->filesByExt($pagesModulePath, '.tsx');
        $charterFiles = $this->filesByExt($pagesModulePath, '.charter.md');
        $charterRatio = count($tsxFiles) === 0 ? 0 : count($charterFiles) / count($tsxFiles);
        $d3c = $charterRatio >= 0.30 ? 3 : (int) round($charterRatio * 10);
        $breakdown[] = [
            'key' => 'D3.c', 'desc' => 'Charter por tela ≥30%',
            'score' => $d3c, 'max' => 3,
            'evidence' => sprintf('%d charters / %d tsx (%.0f%%)', count($charterFiles), count($tsxFiles), $charterRatio * 100),
        ];

        // D3.d — ADR mãe declarada (2 pts) — busca em memory/decisions/ frontmatter module: <Name>
        $d3d = $this->hasModuleAdr($name) ? 2 : 0;
        $breakdown[] = [
            'key' => 'D3.d', 'desc' => 'ADR mãe com module: <X> no frontmatter',
            'score' => $d3d, 'max' => 2,
            'evidence' => $d3d ? 'sim' : 'ausente',
        ];

        return [
            'weight'    => 15,
            'score'     => $d3a + $d3b + $d3c + $d3d,
            'max'       => 15,
            'breakdown' => $breakdown,
        ];
    }

    // ────────────────────────────────────────────────────────────────────────
    // D4 — Maturidade arquitetura (20 pts)
    // ────────────────────────────────────────────────────────────────────────

    private function dim4Architecture(string $name, string $modulePath): array
    {
        $breakdown = [];

        $services = $this->phpFiles($modulePath . '/Services');
        $controllers = $this->phpFiles($modulePath . '/Http/Controllers');

        // D4.a — Service/Controller ratio ≥ 0.3 (6 pts)
        $ratio = count($controllers) === 0 ? 0 : count($services) / count($controllers);
        $d4a = (int) round(min(6, $ratio * 20));  // ratio 0.3 → 6
        $breakdown[] = [
            'key' => 'D4.a', 'desc' => 'Services/Controllers ratio ≥ 0.3',
            'score' => $d4a, 'max' => 6,
            'evidence' => sprintf('%d Services / %d Controllers (ratio %.2f)', count($services), count($controllers), $ratio),
        ];

        // D4.b — FSM canônica (5 pts) — busca trait GuardsFsmTransitions em Models OR sale_processes referenciado
        $d4b = 0;
        $modelFiles = array_merge($this->phpFiles($modulePath . '/Entities'), $this->phpFiles($modulePath . '/Models'));
        foreach ($modelFiles as $f) {
            $content = @file_get_contents($f) ?: '';
            if (str_contains($content, 'GuardsFsmTransitions') || str_contains($content, 'current_stage_id')) {
                $d4b = 5;
                break;
            }
        }
        $breakdown[] = [
            'key' => 'D4.b', 'desc' => 'FSM canônica (ADR 0143)',
            'score' => $d4b, 'max' => 5,
            'evidence' => $d4b ? 'sim — GuardsFsmTransitions ou current_stage_id detectado' : 'não aplicável ou ausente',
        ];

        // D4.c — Pages Inertia .tsx ≥ Blade .blade.php legacy (5 pts)
        // Resolve case-insensitive — Linux Hostinger ≠ Windows local (mesmo bug do D3.c).
        $pagesModulePath = $this->resolveCaseInsensitivePagesPath($name) ?? ($this->pagesPath . "/{$name}");
        $tsxCount = count($this->filesByExt($pagesModulePath, '.tsx'));
        $bladeViewsPath = $this->resolveCaseInsensitiveViewsPath($name) ?? ($this->viewsPath . '/' . strtolower($name));
        $bladeCount = count($this->filesByExt($bladeViewsPath, '.blade.php', recursive: true));
        $d4c = $tsxCount + $bladeCount === 0
            ? 3  // módulo backend-only — neutro
            : ($tsxCount >= $bladeCount ? 5 : (int) round(($tsxCount / max(1, $tsxCount + $bladeCount)) * 5));
        $breakdown[] = [
            'key' => 'D4.c', 'desc' => 'Pages Inertia .tsx ≥ Blade .blade.php legacy',
            'score' => $d4c, 'max' => 5,
            'evidence' => "{$tsxCount} tsx / {$bladeCount} blade",
        ];

        // D4.d — AuditLog + OTel telemetry (4 pts)
        $d4d = 0;
        foreach ([...$controllers, ...$services] as $f) {
            $content = @file_get_contents($f) ?: '';
            if (preg_match('/(LogsActivity|activity\(\)|OpenTelemetry|otel_span|Telemetry)/i', $content)) {
                $d4d = 4;
                break;
            }
        }
        $breakdown[] = [
            'key' => 'D4.d', 'desc' => 'AuditLog + OTel telemetry',
            'score' => $d4d, 'max' => 4,
            'evidence' => $d4d ? 'sim — activitylog ou OTel detectado' : 'ausente',
        ];

        return [
            'weight'    => 20,
            'score'     => $d4a + $d4b + $d4c + $d4d,
            'max'       => 20,
            'breakdown' => $breakdown,
        ];
    }

    // ────────────────────────────────────────────────────────────────────────
    // D5 — Cliente real + criticidade (15 pts)
    // ────────────────────────────────────────────────────────────────────────

    private function dim5Client(string $name): array
    {
        $clients = $this->loadClients();
        $entry = $clients[$name] ?? null;

        $score = 0;
        $evidence = 'ninguém usa (D5.e)';

        if ($entry) {
            $level = $entry['level'] ?? 'none';
            $score = match ($level) {
                'biz_4_rota_livre_prod'    => 15,
                'biz_1_wagner_active'      => 10,
                'piloto_reportando_dor'    => 8,
                'backlog_hipotese'         => 3,
                default                    => 0,
            };
            $evidence = "{$level}" . (isset($entry['note']) ? " — {$entry['note']}" : '');
        }

        return [
            'weight'    => 15,
            'score'     => $score,
            'max'       => 15,
            'breakdown' => [
                [
                    'key' => 'D5', 'desc' => 'Cliente real (manual via config/governance/module_clients.yaml)',
                    'score' => $score, 'max' => 15,
                    'evidence' => $evidence,
                ],
            ],
        ];
    }

    // ────────────────────────────────────────────────────────────────────────
    // Gaps + Evolve suggestions
    // ────────────────────────────────────────────────────────────────────────

    /**
     * Extrai top gaps ordenados por (perda absoluta de pontos × prioridade dimensão).
     */
    private function extractGaps(string $name, array $dimensions): array
    {
        $gaps = [];
        foreach ($dimensions as $dimKey => $dim) {
            foreach ($dim['breakdown'] as $item) {
                $lost = $item['max'] - $item['score'];
                if ($lost > 0) {
                    $gaps[] = [
                        'dimension' => $dimKey,
                        'key'       => $item['key'],
                        'desc'      => $item['desc'],
                        'evidence'  => $item['evidence'],
                        'lost'      => $lost,
                        'max'       => $item['max'],
                        'priority'  => $this->priorityFor($dimKey, $lost),
                    ];
                }
            }
        }
        usort($gaps, fn ($a, $b) => $b['lost'] <=> $a['lost']);
        return $gaps;
    }

    /**
     * Sugere tasks-create batch pra MCP server baseado em top gaps.
     * Botão Evoluir mostra essa lista; Wagner aprova → cria via tasks-create.
     */
    private function suggestEvolveTasks(string $name, array $gaps): array
    {
        $tasks = [];
        foreach (array_slice($gaps, 0, 5) as $g) {
            $tasks[] = [
                'title'     => $this->taskTitleFor($name, $g),
                'module'    => $name,
                'priority'  => $g['priority'],
                'estimate'  => $this->estimateFor($g),
                'gap_ref'   => $g['key'],
                'rationale' => "{$g['desc']} (perda: {$g['lost']}/{$g['max']} pts)",
            ];
        }
        return $tasks;
    }

    private function taskTitleFor(string $module, array $gap): string
    {
        return match ($gap['key']) {
            'D1.a' => "Adicionar BusinessScope global em Entities {$module}",
            'D1.b' => "Adicionar cross-tenant Pest biz=1 vs biz=99 em {$module}",
            'D1.c' => "Refatorar Jobs {$module} pra receber \$businessId no constructor",
            'D2.a' => "Aumentar cobertura Pest em {$module} (ratio tests/Controllers ≥ 0.5)",
            'D2.b' => "Adicionar tests canônicos faltantes em {$module} (MultiTenant + Smoke + Scaffold)",
            'D2.c' => "Registrar Modules/{$module}/Tests/Feature em phpunit.xml",
            'D3.a' => "Criar memory/requisitos/{$module}/SPEC.md com US-XXX-NNN",
            'D3.b' => "Criar/atualizar memory/requisitos/{$module}/BRIEFING.md (1-pager ≤90d)",
            'D3.c' => "Criar Charter .charter.md pras telas principais de {$module}",
            'D3.d' => "Declarar ADR mãe pra {$module} com frontmatter module: {$module}",
            'D4.a' => "Extrair Service classes em {$module} (ratio Services/Controllers ≥ 0.3)",
            'D4.b' => "Avaliar adoção FSM canônica (ADR 0143) em {$module}",
            'D4.c' => "Migrar Blade legacy {$module} pra Inertia .tsx",
            'D4.d' => "Adicionar AuditLog (LogsActivity) + OTel telemetry em {$module}",
            'D5'   => "Identificar cliente real ou desclassificar {$module} pra backlog feature-wish",
            default => "Endereçar gap {$gap['key']} em {$module}",
        };
    }

    private function estimateFor(array $gap): string
    {
        return match ($gap['key']) {
            'D2.c' => '15min',
            'D3.d' => '30min',
            'D3.a', 'D3.b' => '1h',
            'D3.c' => '2h',
            'D1.a', 'D1.b', 'D1.c' => '2-4h',
            'D2.a', 'D2.b' => '2-4h',
            'D4.a' => '4-8h',
            'D4.b' => '8h+',
            'D4.c' => '8-40h',
            'D4.d' => '2-4h',
            'D5'   => 'decisão Wagner',
            default => '2h',
        };
    }

    private function priorityFor(string $dimension, int $lost): string
    {
        if ($dimension === 'multi_tenant' && $lost >= 10) return 'P0';
        if ($dimension === 'multi_tenant') return 'P1';
        if ($dimension === 'pest_coverage' && $lost >= 8) return 'P1';
        if ($dimension === 'pest_coverage') return 'P2';
        if ($dimension === 'documentation') return 'P2';
        if ($dimension === 'architecture' && $lost >= 8) return 'P1';
        if ($dimension === 'architecture') return 'P2';
        return 'P3';
    }

    // ────────────────────────────────────────────────────────────────────────
    // Helpers
    // ────────────────────────────────────────────────────────────────────────

    private function bucketFor(int $score): array
    {
        foreach (self::BUCKETS as $b) {
            if ($score >= $b['min']) return $b;
        }
        return self::BUCKETS[count(self::BUCKETS) - 1];
    }

    private function phpFiles(string $dir, bool $recursive = false): array
    {
        if (! is_dir($dir)) return [];
        if (! $recursive) {
            return array_values(array_filter(
                glob($dir . '/*.php') ?: [],
                fn ($f) => is_file($f)
            ));
        }
        $iter = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        $files = [];
        foreach ($iter as $f) {
            if ($f->isFile() && str_ends_with($f->getFilename(), '.php')) {
                $files[] = $f->getPathname();
            }
        }
        return $files;
    }

    private function filesByExt(string $dir, string $ext, bool $recursive = true): array
    {
        if (! is_dir($dir)) return [];
        $iter = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        $files = [];
        foreach ($iter as $f) {
            if ($f->isFile() && str_ends_with($f->getFilename(), $ext)) {
                $files[] = $f->getPathname();
            }
        }
        return $files;
    }

    private function hasModuleAdr(string $name): bool
    {
        $decisionsPath = base_path('memory/decisions');
        if (! is_dir($decisionsPath)) return false;

        foreach (glob($decisionsPath . '/*.md') ?: [] as $file) {
            $content = @file_get_contents($file) ?: '';
            if (preg_match('/^module:\s*[\'"]?' . preg_quote($name, '/') . '[\'"]?\s*$/m', $content)) {
                return true;
            }
        }
        return false;
    }

    private function loadClients(): array
    {
        if (! file_exists($this->clientsConfigPath)) {
            return [];
        }

        try {
            $data = Yaml::parseFile($this->clientsConfigPath);
            return is_array($data) ? $data : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Resolve diretório `resources/js/Pages/<Modulo>/` de forma case-insensitive.
     *
     * Convenção real do oimpresso é INCONSISTENTE:
     *   - resources/js/Pages/governance/ (g minúsculo)
     *   - resources/js/Pages/Crm/        (C maiúsculo)
     *   - resources/js/Pages/Vestuario/  (V maiúsculo)
     *
     * Windows local é case-insensitive (não falha). Linux Hostinger (prod) é
     * case-sensitive — Service reportava "0 charters / 0 tsx" mesmo quando
     * arquivos existiam (bug detectado em 2026-05-15 no Modules/Governance).
     *
     * Ordem de fallback: case exato → lowercase → lcfirst → scandir match.
     * Performance OK: Controller faz Cache::remember 5min.
     *
     * @return string|null Path real OR null se nenhum existir
     */
    private function resolveCaseInsensitivePagesPath(string $moduleName): ?string
    {
        return $this->resolveCaseInsensitiveDir($this->pagesPath, $moduleName);
    }

    /**
     * Resolve diretório `resources/views/<modulo>/` (Blade legacy) case-insensitive.
     * Mesma motivação do helper Pages — convenção Blade também varia.
     */
    private function resolveCaseInsensitiveViewsPath(string $moduleName): ?string
    {
        return $this->resolveCaseInsensitiveDir($this->viewsPath, $moduleName);
    }

    /**
     * Helper genérico — tenta resolver subdir `<basePath>/<moduleName>` em
     * 4 variantes antes de desistir.
     */
    private function resolveCaseInsensitiveDir(string $basePath, string $moduleName): ?string
    {
        if (! is_dir($basePath)) {
            return null;
        }

        // 1. case exato (caminho mais rápido — Windows local + alguns módulos)
        $exact = $basePath . DIRECTORY_SEPARATOR . $moduleName;
        if (is_dir($exact)) {
            return $exact;
        }

        // 2. lowercase (convenção Blade legacy + Pages/governance, Pages/kb)
        $lower = $basePath . DIRECTORY_SEPARATOR . strtolower($moduleName);
        if (is_dir($lower)) {
            return $lower;
        }

        // 3. lcfirst (convenção Inertia camelCase eventual)
        $lcFirst = $basePath . DIRECTORY_SEPARATOR . lcfirst($moduleName);
        if (is_dir($lcFirst)) {
            return $lcFirst;
        }

        // 4. scandir + strcasecmp — último recurso (~ms em diretório de ~30 entries)
        $entries = @scandir($basePath) ?: [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $candidate = $basePath . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($candidate) && strcasecmp($entry, $moduleName) === 0) {
                return $candidate;
            }
        }

        return null;
    }
}
