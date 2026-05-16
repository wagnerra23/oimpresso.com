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
 * V2 (ADR 0154 proposto) — suporta "N/A justificado" lido do frontmatter `na_justified`
 * em `memory/requisitos/<X>/SPEC.md`. Sub-itens/dimensões marcadas N/A recebem pontuação
 * máxima + evidence "N/A justificado: <razão>". Anti-gaming: máx 3 N/A por módulo —
 * excedentes são ignoradas + warning logado.
 *
 * @see memory/decisions/0153-module-grade-rubrica-v1.md
 * @see memory/decisions/0154-na-justificado-rubrica-v2.md (proposto)
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

    /** Máximo de sub-itens/dimensões N/A justificados por módulo (anti-gaming v2). */
    private const NA_JUSTIFIED_LIMIT = 3;

    /**
     * Pesos canônicos v3 (ADR 0155 proposto) — D1..D5 recalibrados + D6..D9 novas.
     * Total raw = 118; score final normalizado pra 100 via `* 100 / 118`.
     *
     * Backward-compat: `dim['weight']` continua expondo pesos v2 (30/20/15/20/15)
     * pra tests v1/v2 não quebrarem; `dim['weight_v3']` expõe novo valor.
     * `score` (top-level) passa a refletir `score_v3_normalized`.
     */
    private const WEIGHTS_V3 = [
        'multi_tenant'  => 25,
        'pest_coverage' => 17,
        'documentation' => 12,
        'architecture'  => 17,
        'client_real'   => 12,
        'performance'   => 10,
        'lgpd'          => 10,
        'security'      => 8,
        'observability' => 7,
    ];

    /** Soma de WEIGHTS_V3 — usado pra normalizar score raw → 0-100. */
    private const WEIGHTS_V3_TOTAL = 118;

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

        $naJustified = $this->loadNaJustified($name);

        $d1 = $this->dim1MultiTenant($name, $modulePath, $naJustified);
        $d2 = $this->dim2PestCoverage($name, $modulePath, $naJustified);
        $d3 = $this->dim3Documentation($name, $modulePath, $naJustified);
        $d4 = $this->dim4Architecture($name, $modulePath, $naJustified);
        $d5 = $this->dim5Client($name, $naJustified);
        $d6 = $this->dim6Performance($name, $modulePath, $naJustified);
        $d7 = $this->dim7LgpdCompliance($name, $modulePath, $naJustified);
        $d8 = $this->dim8Security($name, $modulePath, $naJustified);
        $d9 = $this->dim9Observability($name, $modulePath, $naJustified);

        // Annotate v3 weight metadata em cada dim (não-destrutivo — preserva `weight` v2)
        $d1['weight_v3'] = self::WEIGHTS_V3['multi_tenant'];
        $d2['weight_v3'] = self::WEIGHTS_V3['pest_coverage'];
        $d3['weight_v3'] = self::WEIGHTS_V3['documentation'];
        $d4['weight_v3'] = self::WEIGHTS_V3['architecture'];
        $d5['weight_v3'] = self::WEIGHTS_V3['client_real'];

        $dimensions = [
            'multi_tenant'  => $d1,
            'pest_coverage' => $d2,
            'documentation' => $d3,
            'architecture'  => $d4,
            'client_real'   => $d5,
            'performance'   => $d6,
            'lgpd'          => $d7,
            'security'      => $d8,
            'observability' => $d9,
        ];

        // Score raw v3: cada dim contribui (score/max) * weight_v3 → soma máx = 118
        $scoreV3Raw = 0.0;
        foreach ($dimensions as $dimKey => $dim) {
            $weightV3 = self::WEIGHTS_V3[$dimKey] ?? 0;
            $max = max(1, (int) ($dim['max'] ?? 1));
            $scoreV3Raw += ((float) $dim['score'] / $max) * $weightV3;
        }
        $scoreV3Normalized = (int) round($scoreV3Raw * 100 / self::WEIGHTS_V3_TOTAL);
        $scoreV3RawRounded = (int) round($scoreV3Raw);

        $bucket = $this->bucketFor($scoreV3Normalized);

        $gaps = $this->extractGaps($name, $dimensions);
        $totalNaJustified = $this->countNaApplied(array_values($dimensions));

        return [
            'module'              => $name,
            // `score` agora é sinônimo de score_v3_normalized (0-100) — UI v1/v2 continua funcionando.
            'score'               => $scoreV3Normalized,
            'score_v3_normalized' => $scoreV3Normalized,
            'score_v3_raw'        => $scoreV3RawRounded,
            'bucket'              => $bucket['label'],
            'color'               => $bucket['color'],
            'dimensions'          => $dimensions,
            'gaps'                => $gaps,
            'evolve_tasks'        => $this->suggestEvolveTasks($name, $gaps),
            'total_na_justified'  => $totalNaJustified,
            'weights_v3'          => self::WEIGHTS_V3,
            'weights_v3_total'    => self::WEIGHTS_V3_TOTAL,
            'evaluated_at'        => now()->toIso8601String(),
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

    private function dim1MultiTenant(string $name, string $modulePath, array $naJustified = []): array
    {
        $breakdown = [];
        $naApplied = [];

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
        $d1aItem = [
            'key'   => 'D1.a',
            'desc'  => 'BusinessScope global em Entities críticas',
            'score' => $d1a,
            'max'   => 10,
            'evidence' => count($entities) === 0
                ? 'sem Entities próprias (neutro)'
                : "{$businessScopeCount}/" . count($entities) . " Entities com BusinessScope",
        ];
        $d1aItem = $this->applyNaJustified($d1aItem, 'D1.a', $naJustified, $naApplied);
        $breakdown[] = $d1aItem;
        $d1a = $d1aItem['score'];

        // D1.b — Cross-tenant Pest cobrindo ≥50% Entities (15 pts)
        // Regex expandido pra capturar variantes canônicas (PR Wave H 2026-05-16):
        //   - constantes BIZ_FICTICIO* / BIZ_WAGNER* / CRM_BIZ_* / AUDIT_BIZ_*
        //   - helpers setBizSession / setAccBizSession
        //   - patterns explícitos "cross-tenant" / "isolation" / "tenant isol*"
        //   - withoutGlobalScopes (escape valve documentado)
        // Detecta TRUE cross-tenant exigindo (a) regex bate OU (b) 2 biz_ids
        // diferentes no mesmo arquivo OU (c) constante BIZ_* aparece 2+ vezes
        // com valores distintos. Single test file sem evidência de 2 biz vira
        // falso positivo — bloqueia.
        $testsPath = $modulePath . '/Tests';
        $testFiles = $this->phpFiles($testsPath, recursive: true);
        $crossTenantTestFiles = 0;
        foreach ($testFiles as $file) {
            $content = @file_get_contents($file) ?: '';
            if ($this->isCrossTenantTestFile($content)) {
                $crossTenantTestFiles++;
            }
        }
        $entCount = count($entities);
        // Módulo SEM Entities (ex Governance — cross-tenant by design Art. 6+8):
        // 1+ test file com policy gate qualifica pra 15/15.
        // Módulo COM Entities: razão crossTenant/(entities×0.5) escalada pra 15.
        $d1b = $entCount === 0
            ? ($crossTenantTestFiles > 0 ? 15 : 0)
            : (int) round(min(15, ($crossTenantTestFiles / max(1, $entCount * 0.5)) * 15));
        $d1bItem = [
            'key'   => 'D1.b',
            'desc'  => 'Cross-tenant Pest biz=1 vs biz=99',
            'score' => $d1b,
            'max'   => 15,
            'evidence' => "{$crossTenantTestFiles} test files com cross-tenant pattern",
        ];
        $d1bItem = $this->applyNaJustified($d1bItem, 'D1.b', $naJustified, $naApplied);
        $breakdown[] = $d1bItem;
        $d1b = $d1bItem['score'];

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
        $d1cItem = [
            'key'   => 'D1.c',
            'desc'  => 'Jobs assíncronos recebem $businessId no constructor',
            'score' => $d1c,
            'max'   => 5,
            'evidence' => count($jobFiles) === 0
                ? 'sem Jobs (neutro)'
                : "{$jobsBusinessId}/" . count($jobFiles) . " Jobs com \$businessId",
        ];
        $d1cItem = $this->applyNaJustified($d1cItem, 'D1.c', $naJustified, $naApplied);
        $breakdown[] = $d1cItem;
        $d1c = $d1cItem['score'];

        // D1 dimensão inteira N/A (override total)
        $dimResult = [
            'weight'    => 30,
            'score'     => $d1a + $d1b + $d1c,
            'max'       => 30,
            'breakdown' => $breakdown,
            'na_justified' => $naApplied,
        ];
        return $this->applyDimensionNa($dimResult, 'D1', $naJustified);
    }

    // ────────────────────────────────────────────────────────────────────────
    // D2 — Pest cobertura (20 pts)
    // ────────────────────────────────────────────────────────────────────────

    private function dim2PestCoverage(string $name, string $modulePath, array $naJustified = []): array
    {
        $breakdown = [];
        $naApplied = [];

        $controllers = $this->phpFiles($modulePath . '/Http/Controllers');
        $testsPath = $modulePath . '/Tests';
        $testFiles = array_filter(
            $this->phpFiles($testsPath, recursive: true),
            fn ($f) => ! str_ends_with($f, 'Pest.php') && ! str_ends_with($f, 'TestCase.php')
        );

        // D2.a — Razão tests/Controllers ≥ 0.5 (8 pts)
        $ratio = count($controllers) === 0 ? 1.0 : count($testFiles) / count($controllers);
        $d2a = (int) round(min(8, $ratio * 16));  // ratio 0.5 → 8 pts
        $d2aItem = [
            'key'   => 'D2.a',
            'desc'  => 'Razão tests/Controllers ≥ 0.5',
            'score' => $d2a,
            'max'   => 8,
            'evidence' => sprintf('%d tests / %d controllers (ratio %.2f)', count($testFiles), count($controllers), $ratio),
        ];
        $d2aItem = $this->applyNaJustified($d2aItem, 'D2.a', $naJustified, $naApplied);
        $breakdown[] = $d2aItem;
        $d2a = $d2aItem['score'];

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
        $d2bItem = [
            'key'   => 'D2.b',
            'desc'  => 'Tem MultiTenant + Smoke + Scaffold canônicos',
            'score' => $d2b,
            'max'   => 8,
            'evidence' => "{$canonicalCount}/3 padrões canônicos presentes",
        ];
        $d2bItem = $this->applyNaJustified($d2bItem, 'D2.b', $naJustified, $naApplied);
        $breakdown[] = $d2bItem;
        $d2b = $d2bItem['score'];

        // D2.c — Registrado em phpunit.xml CI (4 pts)
        $phpunit = file_exists($this->phpunitXmlPath) ? file_get_contents($this->phpunitXmlPath) : '';
        $isRegistered = str_contains($phpunit, "./Modules/{$name}/Tests");
        $d2c = $isRegistered ? 4 : 0;
        $d2cItem = [
            'key'   => 'D2.c',
            'desc'  => 'Registrado em phpunit.xml CI',
            'score' => $d2c,
            'max'   => 4,
            'evidence' => $isRegistered ? 'sim — Tests em phpunit.xml' : 'NÃO REGISTRADO — falsa cobertura',
        ];
        $d2cItem = $this->applyNaJustified($d2cItem, 'D2.c', $naJustified, $naApplied);
        $breakdown[] = $d2cItem;
        $d2c = $d2cItem['score'];

        $dimResult = [
            'weight'    => 20,
            'score'     => $d2a + $d2b + $d2c,
            'max'       => 20,
            'breakdown' => $breakdown,
            'na_justified' => $naApplied,
        ];
        return $this->applyDimensionNa($dimResult, 'D2', $naJustified);
    }

    // ────────────────────────────────────────────────────────────────────────
    // D3 — Documentação canônica (15 pts)
    // ────────────────────────────────────────────────────────────────────────

    private function dim3Documentation(string $name, string $modulePath, array $naJustified = []): array
    {
        $breakdown = [];
        $naApplied = [];

        $specPath = $this->memoryPath . "/{$name}/SPEC.md";
        $briefingPath = $this->memoryPath . "/{$name}/BRIEFING.md";
        // Resolve case-insensitive — Linux Hostinger é case-sensitive, Windows local não.
        // Convenção real do oimpresso é inconsistente (governance/ vs Crm/ vs Vestuario/).
        $pagesModulePath = $this->resolveCaseInsensitivePagesPath($name) ?? ($this->pagesPath . "/{$name}");

        // D3.a — SPEC.md (5 pts)
        $d3a = file_exists($specPath) ? 5 : 0;
        $d3aItem = [
            'key' => 'D3.a', 'desc' => 'memory/requisitos/<X>/SPEC.md',
            'score' => $d3a, 'max' => 5,
            'evidence' => $d3a ? 'sim' : 'ausente',
        ];
        $d3aItem = $this->applyNaJustified($d3aItem, 'D3.a', $naJustified, $naApplied);
        $breakdown[] = $d3aItem;
        $d3a = $d3aItem['score'];

        // D3.b — BRIEFING.md atualizado ≤90d (5 pts)
        $d3b = 0;
        $briefingEvidence = 'ausente';
        if (file_exists($briefingPath)) {
            $age = (time() - filemtime($briefingPath)) / 86400;
            $d3b = $age <= 90 ? 5 : 2;
            $briefingEvidence = sprintf('idade %.0fd', $age);
        }
        $d3bItem = [
            'key' => 'D3.b', 'desc' => 'BRIEFING.md 1-pager ≤90d',
            'score' => $d3b, 'max' => 5,
            'evidence' => $briefingEvidence,
        ];
        $d3bItem = $this->applyNaJustified($d3bItem, 'D3.b', $naJustified, $naApplied);
        $breakdown[] = $d3bItem;
        $d3b = $d3bItem['score'];

        // D3.c — Charter ≥30% telas (3 pts)
        $tsxFiles = $this->filesByExt($pagesModulePath, '.tsx');
        $charterFiles = $this->filesByExt($pagesModulePath, '.charter.md');
        $charterRatio = count($tsxFiles) === 0 ? 0 : count($charterFiles) / count($tsxFiles);
        $d3c = $charterRatio >= 0.30 ? 3 : (int) round($charterRatio * 10);
        $d3cItem = [
            'key' => 'D3.c', 'desc' => 'Charter por tela ≥30%',
            'score' => $d3c, 'max' => 3,
            'evidence' => sprintf('%d charters / %d tsx (%.0f%%)', count($charterFiles), count($tsxFiles), $charterRatio * 100),
        ];
        $d3cItem = $this->applyNaJustified($d3cItem, 'D3.c', $naJustified, $naApplied);
        $breakdown[] = $d3cItem;
        $d3c = $d3cItem['score'];

        // D3.d — ADR mãe declarada (2 pts) — busca em memory/decisions/ frontmatter module: <Name>
        $d3d = $this->hasModuleAdr($name) ? 2 : 0;
        $d3dItem = [
            'key' => 'D3.d', 'desc' => 'ADR mãe com module: <X> no frontmatter',
            'score' => $d3d, 'max' => 2,
            'evidence' => $d3d ? 'sim' : 'ausente',
        ];
        $d3dItem = $this->applyNaJustified($d3dItem, 'D3.d', $naJustified, $naApplied);
        $breakdown[] = $d3dItem;
        $d3d = $d3dItem['score'];

        $dimResult = [
            'weight'    => 15,
            'score'     => $d3a + $d3b + $d3c + $d3d,
            'max'       => 15,
            'breakdown' => $breakdown,
            'na_justified' => $naApplied,
        ];
        return $this->applyDimensionNa($dimResult, 'D3', $naJustified);
    }

    // ────────────────────────────────────────────────────────────────────────
    // D4 — Maturidade arquitetura (20 pts)
    // ────────────────────────────────────────────────────────────────────────

    private function dim4Architecture(string $name, string $modulePath, array $naJustified = []): array
    {
        $breakdown = [];
        $naApplied = [];

        $services = $this->phpFiles($modulePath . '/Services');
        $controllers = $this->phpFiles($modulePath . '/Http/Controllers');

        // D4.a — Service/Controller ratio ≥ 0.3 (6 pts)
        $ratio = count($controllers) === 0 ? 0 : count($services) / count($controllers);
        $d4a = (int) round(min(6, $ratio * 20));  // ratio 0.3 → 6
        $d4aItem = [
            'key' => 'D4.a', 'desc' => 'Services/Controllers ratio ≥ 0.3',
            'score' => $d4a, 'max' => 6,
            'evidence' => sprintf('%d Services / %d Controllers (ratio %.2f)', count($services), count($controllers), $ratio),
        ];
        $d4aItem = $this->applyNaJustified($d4aItem, 'D4.a', $naJustified, $naApplied);
        $breakdown[] = $d4aItem;
        $d4a = $d4aItem['score'];

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
        $d4bItem = [
            'key' => 'D4.b', 'desc' => 'FSM canônica (ADR 0143)',
            'score' => $d4b, 'max' => 5,
            'evidence' => $d4b ? 'sim — GuardsFsmTransitions ou current_stage_id detectado' : 'não aplicável ou ausente',
        ];
        $d4bItem = $this->applyNaJustified($d4bItem, 'D4.b', $naJustified, $naApplied);
        $breakdown[] = $d4bItem;
        $d4b = $d4bItem['score'];

        // D4.c — Pages Inertia .tsx ≥ Blade .blade.php legacy (5 pts)
        // Resolve case-insensitive — Linux Hostinger ≠ Windows local (mesmo bug do D3.c).
        $pagesModulePath = $this->resolveCaseInsensitivePagesPath($name) ?? ($this->pagesPath . "/{$name}");
        $tsxCount = count($this->filesByExt($pagesModulePath, '.tsx'));
        $bladeViewsPath = $this->resolveCaseInsensitiveViewsPath($name) ?? ($this->viewsPath . '/' . strtolower($name));
        $bladeCount = count($this->filesByExt($bladeViewsPath, '.blade.php', recursive: true));
        $d4c = $tsxCount + $bladeCount === 0
            ? 3  // módulo backend-only — neutro
            : ($tsxCount >= $bladeCount ? 5 : (int) round(($tsxCount / max(1, $tsxCount + $bladeCount)) * 5));
        $d4cItem = [
            'key' => 'D4.c', 'desc' => 'Pages Inertia .tsx ≥ Blade .blade.php legacy',
            'score' => $d4c, 'max' => 5,
            'evidence' => "{$tsxCount} tsx / {$bladeCount} blade",
        ];
        $d4cItem = $this->applyNaJustified($d4cItem, 'D4.c', $naJustified, $naApplied);
        $breakdown[] = $d4cItem;
        $d4c = $d4cItem['score'];

        // D4.d — AuditLog + OTel telemetry (4 pts)
        $d4d = 0;
        foreach ([...$controllers, ...$services] as $f) {
            $content = @file_get_contents($f) ?: '';
            if (preg_match('/(LogsActivity|activity\(\)|OpenTelemetry|otel_span|Telemetry)/i', $content)) {
                $d4d = 4;
                break;
            }
        }
        $d4dItem = [
            'key' => 'D4.d', 'desc' => 'AuditLog + OTel telemetry',
            'score' => $d4d, 'max' => 4,
            'evidence' => $d4d ? 'sim — activitylog ou OTel detectado' : 'ausente',
        ];
        $d4dItem = $this->applyNaJustified($d4dItem, 'D4.d', $naJustified, $naApplied);
        $breakdown[] = $d4dItem;
        $d4d = $d4dItem['score'];

        $dimResult = [
            'weight'    => 20,
            'score'     => $d4a + $d4b + $d4c + $d4d,
            'max'       => 20,
            'breakdown' => $breakdown,
            'na_justified' => $naApplied,
        ];
        return $this->applyDimensionNa($dimResult, 'D4', $naJustified);
    }

    // ────────────────────────────────────────────────────────────────────────
    // D5 — Cliente real + criticidade (15 pts)
    // ────────────────────────────────────────────────────────────────────────

    private function dim5Client(string $name, array $naJustified = []): array
    {
        $clients = $this->loadClients();
        $entry = $clients[$name] ?? null;
        $naApplied = [];

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

        $d5Item = [
            'key' => 'D5', 'desc' => 'Cliente real (manual via config/governance/module_clients.yaml)',
            'score' => $score, 'max' => 15,
            'evidence' => $evidence,
        ];
        $d5Item = $this->applyNaJustified($d5Item, 'D5', $naJustified, $naApplied);
        $score = $d5Item['score'];

        $dimResult = [
            'weight'    => 15,
            'score'     => $score,
            'max'       => 15,
            'breakdown' => [$d5Item],
            'na_justified' => $naApplied,
        ];
        return $this->applyDimensionNa($dimResult, 'D5', $naJustified);
    }

    // ────────────────────────────────────────────────────────────────────────
    // D6 — Performance (10 pts) — v3 ADR 0155 proposto
    // ────────────────────────────────────────────────────────────────────────

    /**
     * D6.a Inertia::defer em Controllers (4 pts) — grep `Inertia::defer(` por arquivo
     * D6.b p99 <500ms (3 pts) — placeholder; Service NÃO faz HTTP, default 1.5/3 (50%)
     *      até OTel exportar tabela `module_perf_p99` (futuro)
     * D6.c Sem N+1 evidente (3 pts) — heurística: Controllers com paginate() devem ter with()
     */
    private function dim6Performance(string $name, string $modulePath, array $naJustified = []): array
    {
        $breakdown = [];
        $naApplied = [];

        $controllers = $this->phpFiles($modulePath . '/Http/Controllers', recursive: true);

        // D6.a — Inertia::defer presente em Controllers que renderizam Inertia
        $controllersWithInertia = 0;
        $controllersWithDefer   = 0;
        foreach ($controllers as $f) {
            $content = @file_get_contents($f) ?: '';
            if (str_contains($content, 'Inertia::render')) {
                $controllersWithInertia++;
                if (preg_match('/Inertia::defer\s*\(/', $content)) {
                    $controllersWithDefer++;
                }
            }
        }
        $d6a = $controllersWithInertia === 0
            ? 4  // módulo sem Inertia (CLI/backend-only) — neutro
            : (int) round(min(4, ($controllersWithDefer / max(1, $controllersWithInertia)) * 4));
        $d6aItem = [
            'key'   => 'D6.a',
            'desc'  => 'Inertia::defer em Controllers que renderizam Inertia',
            'score' => $d6a,
            'max'   => 4,
            'evidence' => $controllersWithInertia === 0
                ? 'sem Controllers Inertia (neutro)'
                : "{$controllersWithDefer}/{$controllersWithInertia} Controllers Inertia usam defer",
        ];
        $d6aItem = $this->applyNaJustified($d6aItem, 'D6.a', $naJustified, $naApplied);
        $breakdown[] = $d6aItem;
        $d6a = $d6aItem['score'];

        // D6.b — p99 <500ms (placeholder até OTel exportar métricas — score default 1.5→2)
        $d6bItem = [
            'key'   => 'D6.b',
            'desc'  => 'p99 latency <500ms (OTel)',
            'score' => 2,  // placeholder neutro (50% de 3 ≈ 1.5; arredondado pra 2)
            'max'   => 3,
            'evidence' => 'placeholder — OTel `module_perf_p99` ainda não exportado (Service não faz HTTP)',
        ];
        $d6bItem = $this->applyNaJustified($d6bItem, 'D6.b', $naJustified, $naApplied);
        $breakdown[] = $d6bItem;
        $d6b = $d6bItem['score'];

        // D6.c — Sem N+1: Controllers com paginate() devem ter with()
        $paginateNoWith = 0;
        $paginateTotal  = 0;
        foreach ($controllers as $f) {
            $content = @file_get_contents($f) ?: '';
            if (preg_match('/->paginate\s*\(/', $content)) {
                $paginateTotal++;
                if (! preg_match('/->with\s*\(/', $content) && ! preg_match('/->load\s*\(/', $content)) {
                    $paginateNoWith++;
                }
            }
        }
        $d6c = $paginateTotal === 0
            ? 3  // sem paginate() — neutro
            : (int) round(min(3, max(0, 1 - $paginateNoWith / max(1, $paginateTotal)) * 3));
        $d6cItem = [
            'key'   => 'D6.c',
            'desc'  => 'Sem N+1 evidente (paginate() acompanhado de with()/load())',
            'score' => $d6c,
            'max'   => 3,
            'evidence' => $paginateTotal === 0
                ? 'sem paginate() (neutro)'
                : ($paginateTotal - $paginateNoWith) . "/{$paginateTotal} paginate() com eager-load",
        ];
        $d6cItem = $this->applyNaJustified($d6cItem, 'D6.c', $naJustified, $naApplied);
        $breakdown[] = $d6cItem;
        $d6c = $d6cItem['score'];

        $dimResult = [
            'weight'       => 10,
            'weight_v3'    => self::WEIGHTS_V3['performance'],
            'score'        => $d6a + $d6b + $d6c,
            'max'          => 10,
            'breakdown'    => $breakdown,
            'na_justified' => $naApplied,
        ];
        return $this->applyDimensionNa($dimResult, 'D6', $naJustified);
    }

    // ────────────────────────────────────────────────────────────────────────
    // D7 — LGPD Compliance (10 pts) — v3 ADR 0155 proposto
    // ────────────────────────────────────────────────────────────────────────

    /**
     * D7.a PiiRedactor (4 pts) — grep `PiiRedactor` em qualquer arquivo do módulo
     * D7.b LogsActivity em Models (3 pts) — grep `LogsActivity` em Entities/Models
     * D7.c Retention configurada (3 pts) — `retention_days` em module.json OR config/retention.<modulo>.php
     */
    private function dim7LgpdCompliance(string $name, string $modulePath, array $naJustified = []): array
    {
        $breakdown = [];
        $naApplied = [];

        // D7.a — PiiRedactor uso (4 pts)
        $allModuleFiles = $this->phpFiles($modulePath, recursive: true);
        $hasPiiRedactor = false;
        foreach ($allModuleFiles as $f) {
            $content = @file_get_contents($f) ?: '';
            if (str_contains($content, 'PiiRedactor')) {
                $hasPiiRedactor = true;
                break;
            }
        }
        $d7a = $hasPiiRedactor ? 4 : 0;
        $d7aItem = [
            'key'   => 'D7.a',
            'desc'  => 'PiiRedactor usado pra logs/diffs',
            'score' => $d7a,
            'max'   => 4,
            'evidence' => $hasPiiRedactor ? 'sim — PiiRedactor referenciado' : 'ausente',
        ];
        $d7aItem = $this->applyNaJustified($d7aItem, 'D7.a', $naJustified, $naApplied);
        $breakdown[] = $d7aItem;
        $d7a = $d7aItem['score'];

        // D7.b — LogsActivity em Models (3 pts)
        $modelFiles = array_merge(
            $this->phpFiles($modulePath . '/Entities'),
            $this->phpFiles($modulePath . '/Models')
        );
        $modelsWithLog = 0;
        foreach ($modelFiles as $f) {
            $content = @file_get_contents($f) ?: '';
            if (preg_match('/\bLogsActivity\b/', $content)) {
                $modelsWithLog++;
            }
        }
        $d7b = count($modelFiles) === 0
            ? 2  // sem Models próprios — parcial neutro
            : (int) round(min(3, ($modelsWithLog / max(1, count($modelFiles))) * 3));
        $d7bItem = [
            'key'   => 'D7.b',
            'desc'  => 'Models com trait LogsActivity (Spatie ActivityLog)',
            'score' => $d7b,
            'max'   => 3,
            'evidence' => count($modelFiles) === 0
                ? 'sem Models próprios (parcial neutro)'
                : "{$modelsWithLog}/" . count($modelFiles) . " Models com LogsActivity",
        ];
        $d7bItem = $this->applyNaJustified($d7bItem, 'D7.b', $naJustified, $naApplied);
        $breakdown[] = $d7bItem;
        $d7b = $d7bItem['score'];

        // D7.c — Retention configurada (3 pts)
        $moduleJson = $modulePath . '/module.json';
        $retentionConfigA = base_path("config/retention.{$name}.php");
        $retentionConfigB = base_path('config/retention.' . strtolower($name) . '.php');
        $hasRetention = false;
        $retentionEvidence = 'ausente';
        if (file_exists($moduleJson)) {
            $jsonContent = @file_get_contents($moduleJson) ?: '';
            if (preg_match('/"retention_days"\s*:/', $jsonContent)) {
                $hasRetention = true;
                $retentionEvidence = 'module.json tem retention_days';
            }
        }
        if (! $hasRetention && (file_exists($retentionConfigA) || file_exists($retentionConfigB))) {
            $hasRetention = true;
            $retentionEvidence = 'config/retention.' . (file_exists($retentionConfigA) ? $name : strtolower($name)) . '.php existe';
        }
        $d7c = $hasRetention ? 3 : 0;
        $d7cItem = [
            'key'   => 'D7.c',
            'desc'  => 'Retention policy declarada (LGPD Art. 16)',
            'score' => $d7c,
            'max'   => 3,
            'evidence' => $retentionEvidence,
        ];
        $d7cItem = $this->applyNaJustified($d7cItem, 'D7.c', $naJustified, $naApplied);
        $breakdown[] = $d7cItem;
        $d7c = $d7cItem['score'];

        $dimResult = [
            'weight'       => 10,
            'weight_v3'    => self::WEIGHTS_V3['lgpd'],
            'score'        => $d7a + $d7b + $d7c,
            'max'          => 10,
            'breakdown'    => $breakdown,
            'na_justified' => $naApplied,
        ];
        return $this->applyDimensionNa($dimResult, 'D7', $naJustified);
    }

    // ────────────────────────────────────────────────────────────────────────
    // D8 — Security (8 pts) — v3 ADR 0155 proposto
    // ────────────────────────────────────────────────────────────────────────

    /**
     * D8.a Rate-limit middleware throttle (3 pts) — scan routes do módulo
     * D8.b CSRF Inertia/Blade (2 pts) — presença Inertia + @csrf em Blade
     * D8.c FormRequest cobertura (3 pts) — ratio Requests/Controllers
     */
    private function dim8Security(string $name, string $modulePath, array $naJustified = []): array
    {
        $breakdown = [];
        $naApplied = [];

        // D8.a — throttle middleware (3 pts)
        $routesFiles = array_filter([
            $modulePath . '/Routes/web.php',
            $modulePath . '/Routes/api.php',
            $modulePath . '/routes/web.php',
            $modulePath . '/routes/api.php',
        ], 'file_exists');
        $hasThrottle = false;
        foreach ($routesFiles as $rf) {
            $content = @file_get_contents($rf) ?: '';
            if (preg_match("/->middleware\s*\(\s*['\"]throttle[:.]/", $content) || str_contains($content, "'throttle.api'") || str_contains($content, '"throttle.api"')) {
                $hasThrottle = true;
                break;
            }
        }
        $d8a = $hasThrottle ? 3 : (count($routesFiles) === 0 ? 1 : 0);  // sem routes = parcial neutro
        $d8aItem = [
            'key'   => 'D8.a',
            'desc'  => 'Rate-limit throttle middleware em rotas',
            'score' => $d8a,
            'max'   => 3,
            'evidence' => $hasThrottle
                ? 'sim — middleware throttle detectado'
                : (count($routesFiles) === 0 ? 'sem rotas próprias (parcial)' : 'ausente'),
        ];
        $d8aItem = $this->applyNaJustified($d8aItem, 'D8.a', $naJustified, $naApplied);
        $breakdown[] = $d8aItem;
        $d8a = $d8aItem['score'];

        // D8.b — CSRF except penalty (ADR 0155 §D8 — 2 pts default, -2 se route do módulo
        // está listada em app/Http/Middleware/VerifyCsrfToken.php::$except).
        //
        // Inertia entrega CSRF nativo via X-XSRF-TOKEN — default Laravel = ON.
        // Penalty SÓ se VerifyCsrfToken::except listar route que pertence ao módulo
        // (sinal explícito de bypass intencional → -2 pts → score 0).
        $csrfExcept = $this->loadCsrfExcept();
        $moduleRoutesInExcept = $this->filterExceptForModule($csrfExcept, $name);
        if (! empty($moduleRoutesInExcept)) {
            $d8b = 0;
            $d8bEvidence = 'PENALTY -2: VerifyCsrfToken::except lista route(s) do módulo: '
                . implode(', ', $moduleRoutesInExcept);
        } else {
            $d8b = 2;
            $d8bEvidence = 'default Laravel CSRF on (Inertia X-XSRF-TOKEN — sem except detectada)';
        }
        $d8bItem = [
            'key'   => 'D8.b',
            'desc'  => 'CSRF except penalty (default 2; -2 se route do módulo em VerifyCsrfToken::except)',
            'score' => $d8b,
            'max'   => 2,
            'evidence' => $d8bEvidence,
        ];
        $d8bItem = $this->applyNaJustified($d8bItem, 'D8.b', $naJustified, $naApplied);
        $breakdown[] = $d8bItem;
        $d8b = $d8bItem['score'];

        // D8.c — FormRequest cobertura (3 pts)
        $requests = $this->phpFiles($modulePath . '/Http/Requests', recursive: true);
        $controllers = $this->phpFiles($modulePath . '/Http/Controllers', recursive: true);
        $controllersCount = count($controllers);
        if ($controllersCount === 0) {
            $d8c = 2;  // sem Controllers — parcial neutro
            $d8cEvidence = 'sem Controllers (parcial neutro)';
        } else {
            $ratio = count($requests) / $controllersCount;
            $d8c = (int) round(min(3, $ratio * 6));  // ratio 0.5 → 3pts
            $d8cEvidence = count($requests) . " FormRequests / {$controllersCount} Controllers (ratio " . number_format($ratio, 2) . ")";
        }
        $d8cItem = [
            'key'   => 'D8.c',
            'desc'  => 'FormRequest cobertura (ratio Requests/Controllers ≥ 0.5)',
            'score' => $d8c,
            'max'   => 3,
            'evidence' => $d8cEvidence,
        ];
        $d8cItem = $this->applyNaJustified($d8cItem, 'D8.c', $naJustified, $naApplied);
        $breakdown[] = $d8cItem;
        $d8c = $d8cItem['score'];

        $dimResult = [
            'weight'       => 8,
            'weight_v3'    => self::WEIGHTS_V3['security'],
            'score'        => $d8a + $d8b + $d8c,
            'max'          => 8,
            'breakdown'    => $breakdown,
            'na_justified' => $naApplied,
        ];
        return $this->applyDimensionNa($dimResult, 'D8', $naJustified);
    }

    // ────────────────────────────────────────────────────────────────────────
    // D9 — Observability (7 pts) — v3 ADR 0155 proposto
    // ────────────────────────────────────────────────────────────────────────

    /**
     * D9.a OTel spans em Services (4 pts) — detecta instrumentation OTel em `Modules/<X>/Services/`.
     *
     * Regex canônica (ADR 0156 §Errata 1) cobre:
     *   - `OpenTelemetry` (SDK direto via namespace)
     *   - `otel_span` (helper legacy)
     *   - `Tracer`, `StartSpan`, `tracer()` (API OTel direta)
     *   - `OtelHelper::span(` e `OtelHelper::spanBiz(` (facade canônica oimpresso em `app/Util/OtelHelper.php`)
     *
     * O `\s*\(` final exige parêntese após `OtelHelper::span[Biz]`, evitando match em
     * comentários/docblocks que apenas mencionam a API sem invocá-la (ex.: MeilisearchDriver:77).
     *
     * D9.b failed_jobs <5/24h (3 pts) — opt-in via config `governance.observability.query_failed_jobs`,
     *      default off; quando off retorna placeholder 1.5→2.
     */
    private function dim9Observability(string $name, string $modulePath, array $naJustified = []): array
    {
        $breakdown = [];
        $naApplied = [];

        // D9.a — OTel spans em Services (4 pts) — regex ADR 0156 §Errata 1
        $services = $this->phpFiles($modulePath . '/Services', recursive: true);
        $servicesWithOtel = 0;
        foreach ($services as $f) {
            $content = @file_get_contents($f) ?: '';
            if (preg_match('/\b(OpenTelemetry|otel_span|Tracer|StartSpan|tracer\(\)|OtelHelper::span(?:Biz)?\s*\()/i', $content)) {
                $servicesWithOtel++;
            }
        }
        $d9a = count($services) === 0
            ? 2  // sem Services próprios — parcial neutro
            : (int) round(min(4, ($servicesWithOtel / max(1, count($services))) * 4));
        $d9aItem = [
            'key'   => 'D9.a',
            'desc'  => 'OTel spans em Services',
            'score' => $d9a,
            'max'   => 4,
            'evidence' => count($services) === 0
                ? 'sem Services próprios (parcial neutro)'
                : "{$servicesWithOtel}/" . count($services) . " Services com OTel/Tracer",
        ];
        $d9aItem = $this->applyNaJustified($d9aItem, 'D9.a', $naJustified, $naApplied);
        $breakdown[] = $d9aItem;
        $d9a = $d9aItem['score'];

        // D9.b — failed_jobs <5/24h (3 pts) — opt-in DB query
        $d9b = 2;  // placeholder default (50% de 3 arredondado)
        $d9bEvidence = 'placeholder — DB query opt-in via config governance.observability.query_failed_jobs';
        $optIn = false;
        try {
            $optIn = function_exists('config') ? (bool) config('governance.observability.query_failed_jobs', false) : false;
        } catch (\Throwable $e) {
            $optIn = false;
        }
        if ($optIn) {
            try {
                if (class_exists(\Illuminate\Support\Facades\Schema::class)
                    && \Illuminate\Support\Facades\Schema::hasTable('failed_jobs')) {
                    $count = (int) \Illuminate\Support\Facades\DB::table('failed_jobs')
                        ->where('failed_at', '>', now()->subHours(24))
                        ->count();
                    $d9b = $count < 5 ? 3 : ($count < 20 ? 1 : 0);
                    $d9bEvidence = "{$count} failed_jobs nas últimas 24h";
                } else {
                    $d9bEvidence = 'tabela failed_jobs ausente — placeholder mantido';
                }
            } catch (\Throwable $e) {
                $d9bEvidence = 'erro ao consultar failed_jobs: ' . $e->getMessage();
            }
        }
        $d9bItem = [
            'key'   => 'D9.b',
            'desc'  => 'failed_jobs <5 nas últimas 24h',
            'score' => $d9b,
            'max'   => 3,
            'evidence' => $d9bEvidence,
        ];
        $d9bItem = $this->applyNaJustified($d9bItem, 'D9.b', $naJustified, $naApplied);
        $breakdown[] = $d9bItem;
        $d9b = $d9bItem['score'];

        $dimResult = [
            'weight'       => 7,
            'weight_v3'    => self::WEIGHTS_V3['observability'],
            'score'        => $d9a + $d9b,
            'max'          => 7,
            'breakdown'    => $breakdown,
            'na_justified' => $naApplied,
        ];
        return $this->applyDimensionNa($dimResult, 'D9', $naJustified);
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
            'D6.a' => "Aplicar Inertia::defer em Controllers do {$module} (props caras)",
            'D6.b' => "Instrumentar OTel p99 latency em {$module} (export pra dashboard)",
            'D6.c' => "Eliminar N+1 — adicionar with()/load() nos paginate() de {$module}",
            'D7.a' => "Aplicar PiiRedactor em logs/diffs do {$module} (LGPD Art. 6)",
            'D7.b' => "Adicionar trait LogsActivity nos Models do {$module} (Spatie ActivityLog)",
            'D7.c' => "Declarar retention_days em module.json OR config/retention.{$module}.php (LGPD Art. 16)",
            'D8.a' => "Adicionar middleware throttle nas rotas do {$module}",
            'D8.b' => "Remover route(s) do {$module} de VerifyCsrfToken::except OU justificar via na_justified_v3",
            'D8.c' => "Criar FormRequests pros Controllers do {$module} (ratio Requests/Controllers ≥ 0.5)",
            'D9.a' => "Instrumentar OTel spans nos Services do {$module}",
            'D9.b' => "Investigar failed_jobs do {$module} (alvo <5/24h)",
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
            'D6.a' => '1-2h',
            'D6.b' => '4-8h',
            'D6.c' => '1-2h',
            'D7.a' => '2-4h',
            'D7.b' => '2-4h',
            'D7.c' => '30min',
            'D8.a' => '30min',
            'D8.b' => '1h',
            'D8.c' => '2-4h',
            'D9.a' => '4-8h',
            'D9.b' => 'investigação',
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
        if ($dimension === 'lgpd' && $lost >= 5) return 'P0';   // LGPD = legal — P0
        if ($dimension === 'lgpd') return 'P1';
        if ($dimension === 'security' && $lost >= 4) return 'P1';
        if ($dimension === 'security') return 'P2';
        if ($dimension === 'performance' && $lost >= 5) return 'P1';
        if ($dimension === 'performance') return 'P2';
        if ($dimension === 'observability') return 'P2';
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

    /**
     * Detecta se conteúdo de test file qualifica como cross-tenant Pest válido.
     *
     * Patterns canônicos (Wave H 2026-05-16 — expansão regex D1.b):
     *   (a) constante `BIZ_FICTICIO*` / `BIZ_WAGNER*` (qualquer suffix) usada
     *   (b) helper `setBizSession` / `setAccBizSession` com 2+ chamadas
     *   (c) `business_id` com 2 valores distintos no MESMO arquivo (true isolamento)
     *   (d) `withoutGlobalScopes` (escape valve com comentário SUPERADMIN)
     *   (e) palavra `cross-tenant`, `cross_tenant`, `isolation`, `tenant isol*` no arquivo
     *   (f) pattern legado `biz=99` / `business_id.*99` (back-compat regex original)
     *
     * Pra evitar falso positivo: arquivo precisa também ter (i) `biz=1`, (ii)
     * constante BIZ_WAGNER*, (iii) 2 chamadas de session()/setBizSession() OU
     * (iv) explicit "biz=1" e "biz=99" em strings/comentários. Garante que o
     * test EXERCITA 2 tenants — não só menciona um.
     *
     * @internal
     */
    private function isCrossTenantTestFile(string $content): bool
    {
        // Captura constantes BIZ_* e variantes (case-sensitive — convenção PHP)
        $hasBizFicticio = (bool) preg_match('/\bBIZ_FICTICIO\w*\b|\b[A-Z]+_BIZ_FICTICIO\w*\b/', $content);
        $hasBizWagner   = (bool) preg_match('/\bBIZ_WAGNER\w*\b|\b[A-Z]+_BIZ_WAGNER\w*\b/', $content);
        $hasBiz99Lit    = (bool) preg_match('/biz=99|business_id.{0,30}99\b/', $content);
        $hasBiz1Lit     = (bool) preg_match('/biz=1\b|business_id.{0,30}1\b/', $content);
        $hasWithout     = str_contains($content, 'withoutGlobalScopes');
        $hasSetBiz      = (bool) preg_match_all('/\b(setBizSession|setAccBizSession)\b/', $content, $m) >= 2;
        $hasCrossWord   = (bool) preg_match('/cross[-_ ]tenant|tenant[-_ ]?isol|isolation|multi[-_ ]?tenant/i', $content);

        // Critério (a) — 2 constantes BIZ_* DIFERENTES no mesmo arquivo = true isolamento
        if ($hasBizFicticio && $hasBizWagner) {
            return true;
        }

        // Critério (b) — helper setBizSession chamado 2+ vezes (sinal canônico ComVis)
        if ($hasSetBiz && ($hasBizFicticio || $hasBizWagner || $hasBiz99Lit)) {
            return true;
        }

        // Critério (c) — biz=1 e biz=99 ambos presentes (literais ou business_id.*N)
        if ($hasBiz1Lit && $hasBiz99Lit) {
            return true;
        }

        // Critério (d) — withoutGlobalScopes + constante BIZ_* OU literal biz= (com pelo
        // menos 2 marcadores de business diferentes, evita falso positivo)
        if ($hasWithout && ($hasBizFicticio || $hasBizWagner) && ($hasBiz99Lit || $hasBiz1Lit)) {
            return true;
        }

        // Critério (e) — palavra "cross-tenant"/"isolation"/"multi-tenant" + constante BIZ_*
        // Cobre Governance/CrossTenantPolicyTest mesmo se cenários usarem só 1 constante
        if ($hasCrossWord && ($hasBizFicticio || $hasBizWagner)) {
            return true;
        }

        // Critério (f) — back-compat regex original puro literal (caso legado)
        if ($hasBiz99Lit || $hasWithout) {
            return $hasBizFicticio || $hasBizWagner || $hasBiz1Lit || $hasCrossWord;
        }

        return false;
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

    /**
     * Lê frontmatter YAML campo `na_justified` (v2) E `na_justified_v3` (v3 — ADR 0155
     * Fase 1) em `memory/requisitos/<X>/SPEC.md` e retorna mapa MESCLADO.
     *
     * Aceita 2 formatos em ambas chaves:
     *   na_justified:                      |  na_justified_v3:
     *     D4.b: "Sem state machine"        |    D6.a: "CLI-only, sem Inertia"
     *     D5: "Cross-tenant Art. 6"        |    D7.c: "retention global"
     * OU array simples (sem razão — fallback string vazia):
     *   na_justified: [D4.b, D5]
     *   na_justified_v3: [D6.a, D8.b]
     *
     * Convenção v2/v3 (ADR 0155 §"N/A v2 backward-compat"):
     *   - `na_justified` v2 cobre D1-D5 originais E continua aceitando D6-D9
     *     (backward-compat total — ADR 0155 §"N/A v2 backward-compat" exige Service v3
     *     lê `na_justified: [d6.a, d7.c]` com mesma lógica v2)
     *   - `na_justified_v3` v3 é o canal preferencial pra D6-D9 (chave nova)
     *   - Chaves declaradas em AMBAS são mescladas; v3 sobrescreve v2 em colisão
     *   - Soft-deprecation log quando D6-D9 vem por v2 (sugere migrar pra v3)
     *
     * Anti-gaming v2 (ADR 0154): máx NA_JUSTIFIED_LIMIT (3) entradas TOTAIS (v2 + v3
     * mescladas). Excedentes são ignoradas + Log::warning() emitido.
     *
     * @return array<string, string> mapeamento key (D1.a / D5 / D6.a) → razão
     */
    private function loadNaJustified(string $module): array
    {
        $specPath = $this->memoryPath . "/{$module}/SPEC.md";
        if (! file_exists($specPath)) {
            return [];
        }

        $content = @file_get_contents($specPath) ?: '';
        // Extrai frontmatter YAML (entre --- ... ---)
        if (! preg_match('/\A---\s*\n(.*?)\n---\s*\n/s', $content, $m)) {
            return [];
        }

        try {
            $front = Yaml::parse($m[1]);
        } catch (\Throwable $e) {
            return [];
        }

        if (! is_array($front)) {
            return [];
        }

        // Parse v2 (na_justified) — aceita D6-D9 (backward-compat ADR 0155);
        // soft-deprecation log se aparecer D6-D9 (sugere usar na_justified_v3)
        $parsedV2 = $this->parseNaJustifiedRaw($front['na_justified'] ?? null);
        $v6plusInV2 = [];
        foreach ($parsedV2 as $key => $reason) {
            if (preg_match('/^[dD][6789](\b|\.)/', $key)) {
                $v6plusInV2[] = $key;
            }
        }

        // Parse v3 (na_justified_v3) — todas chaves aceitas (compat ampliado)
        $parsedV3 = $this->parseNaJustifiedRaw($front['na_justified_v3'] ?? null);

        // Merge — v3 sobrescreve v2 se mesma chave declarada (decisão Wagner: v3 wins)
        $parsed = array_merge($parsedV2, $parsedV3);

        // Soft-deprecation: chaves D6-D9 em `na_justified` v2 continuam funcionando,
        // mas log sugere migração pra `na_justified_v3` (canal preferencial v3)
        if (! empty($v6plusInV2) && class_exists(\Illuminate\Support\Facades\Log::class)) {
            try {
                \Illuminate\Support\Facades\Log::info(
                    "[ModuleGradeService] Módulo {$module}: chave(s) D6-D9 declaradas em `na_justified` v2 " .
                    "(aceitas via backward-compat). Sugestão: migrar pra `na_justified_v3` (ADR 0155). " .
                    "Chaves: " . implode(', ', $v6plusInV2)
                );
            } catch (\Throwable $e) {
                // logger indisponível em test isolado — segue sem log
            }
        }

        // Anti-gaming: máximo NA_JUSTIFIED_LIMIT entradas totais (v2 + v3)
        if (count($parsed) > self::NA_JUSTIFIED_LIMIT) {
            $excedentes = array_slice($parsed, self::NA_JUSTIFIED_LIMIT, null, true);
            $parsed = array_slice($parsed, 0, self::NA_JUSTIFIED_LIMIT, true);

            if (class_exists(\Illuminate\Support\Facades\Log::class)) {
                try {
                    \Illuminate\Support\Facades\Log::warning(
                        "[ModuleGradeService] Módulo {$module} declarou " . (count($parsed) + count($excedentes)) .
                        " N/A justificados — limite é " . self::NA_JUSTIFIED_LIMIT . ". Excedentes ignoradas: " .
                        implode(', ', array_keys($excedentes))
                    );
                } catch (\Throwable $e) {
                    // logger indisponível em test isolado — segue sem log
                }
            }
        }

        return $parsed;
    }

    /**
     * Helper: parse raw value (lista OR mapping) → array key=>reason.
     *
     * @param mixed $raw Valor cru do frontmatter (array, null, string, etc)
     * @return array<string, string>
     */
    private function parseNaJustifiedRaw($raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $parsed = [];
        foreach ($raw as $key => $value) {
            if (is_int($key)) {
                // Formato lista: [D4.b, D5]
                if (is_string($value)) {
                    $parsed[$value] = '';
                }
            } else {
                // Formato mapping: D4.b: "razão"
                $parsed[(string) $key] = is_string($value) ? $value : '';
            }
        }
        return $parsed;
    }

    /**
     * Lê array `$except` de `app/Http/Middleware/VerifyCsrfToken.php` via regex.
     *
     * Suporta tanto middleware tradicional (Laravel 10/11/12) quanto eventual
     * config flat futura (`bootstrap/app.php` com `->validateCsrfTokens(except: [...])`).
     *
     * Retorna lista de paths string. Vazio se arquivo ausente ou regex não bate.
     *
     * @return string[]
     */
    private function loadCsrfExcept(): array
    {
        $paths = [];

        // 1. Middleware clássico (Laravel 10/11/12 — oimpresso atual em 13.6)
        $middlewarePath = base_path('app/Http/Middleware/VerifyCsrfToken.php');
        if (is_file($middlewarePath)) {
            $content = @file_get_contents($middlewarePath) ?: '';
            if (preg_match('/\$except\s*=\s*\[(.*?)\]/s', $content, $m)) {
                preg_match_all('/[\'"]([^\'"]+)[\'"]/', $m[1], $matches);
                $paths = array_merge($paths, $matches[1] ?? []);
            }
        }

        // 2. Config flat (bootstrap/app.php — Laravel 11+ skeleton)
        $bootstrapApp = base_path('bootstrap/app.php');
        if (is_file($bootstrapApp)) {
            $content = @file_get_contents($bootstrapApp) ?: '';
            if (preg_match('/validateCsrfTokens\s*\(\s*except\s*:\s*\[(.*?)\]\s*\)/s', $content, $m)) {
                preg_match_all('/[\'"]([^\'"]+)[\'"]/', $m[1], $matches);
                $paths = array_merge($paths, $matches[1] ?? []);
            }
        }

        return array_values(array_unique($paths));
    }

    /**
     * Filtra paths do CSRF except que pertencem ao módulo via heurística.
     *
     * Heurística (ADR 0155 §D8.b "route do módulo"):
     *   - Path contém segmento `<modulo-lowercase>/` no início OU como sub-path
     *   - Match também via `module.json` `routes_prefix` se declarado
     *   - Wildcard `*` ignorado pra normalização
     *
     * Exemplos (módulo=Crm):
     *   '/crm/webhook'           → MATCH (segmento /crm/)
     *   'crm/inbound/*'          → MATCH (prefix crm/)
     *   '/webhook/*'             → SEM match (path genérico não-específico)
     *   '/whatsapp/webhook'      → SEM match (outro módulo)
     *
     * @param string[] $exceptPaths Lista de paths em VerifyCsrfToken::except
     * @param string $moduleName Nome PascalCase do módulo
     * @return string[] Subset que casa com o módulo
     */
    private function filterExceptForModule(array $exceptPaths, string $moduleName): array
    {
        if (empty($exceptPaths)) {
            return [];
        }

        $lower = strtolower($moduleName);
        $candidates = [$lower];

        // module.json `routes_prefix` (raro mas vale conferir)
        $moduleJsonPath = $this->modulesPath . DIRECTORY_SEPARATOR . $moduleName . DIRECTORY_SEPARATOR . 'module.json';
        if (is_file($moduleJsonPath)) {
            $json = @json_decode(@file_get_contents($moduleJsonPath) ?: '', true);
            if (is_array($json)) {
                $prefix = $json['routes_prefix'] ?? null;
                if (is_string($prefix) && $prefix !== '') {
                    $candidates[] = strtolower(trim($prefix, '/'));
                }
            }
        }
        $candidates = array_values(array_unique(array_filter($candidates)));

        $matched = [];
        foreach ($exceptPaths as $path) {
            // Normaliza: tira leading slash + lowercase + tira wildcard final
            $normalized = strtolower(ltrim($path, '/'));
            $normalized = rtrim($normalized, '*');
            $normalized = trim($normalized, '/');

            if ($normalized === '') {
                continue;
            }

            $segments = explode('/', $normalized);
            $firstSegment = $segments[0] ?? '';

            foreach ($candidates as $cand) {
                // Match estrito: primeiro segmento bate (rota raiz do módulo)
                if ($firstSegment === $cand) {
                    $matched[] = $path;
                    break;
                }
            }
        }

        return $matched;
    }

    /**
     * Aplica N/A justificado a um sub-item (D1.a, D2.b, etc) se key estiver declarada.
     * Substitui score por max + apenda razão ao evidence + marca em $naApplied.
     */
    private function applyNaJustified(array $item, string $key, array $naJustified, array &$naApplied): array
    {
        if (! array_key_exists($key, $naJustified)) {
            return $item;
        }

        $reason = $naJustified[$key];
        $item['score'] = $item['max'];
        $item['evidence'] = 'N/A justificado: ' . ($reason !== '' ? $reason : 'sem razão declarada');
        $item['na_justified'] = true;

        $naApplied[$key] = $reason;
        return $item;
    }

    /**
     * Aplica N/A na DIMENSÃO inteira (ex D5 completo) — força score = max + marca todos breakdowns.
     */
    private function applyDimensionNa(array $dimResult, string $dimKey, array $naJustified): array
    {
        if (! array_key_exists($dimKey, $naJustified)) {
            return $dimResult;
        }

        $reason = $naJustified[$dimKey];
        $dimResult['score'] = $dimResult['max'];

        // Marca todos sub-itens N/A (caso ainda não estejam)
        foreach ($dimResult['breakdown'] as $idx => $sub) {
            if (! ($sub['na_justified'] ?? false)) {
                $dimResult['breakdown'][$idx]['score'] = $sub['max'];
                $dimResult['breakdown'][$idx]['evidence'] = 'N/A justificado: ' . ($reason !== '' ? $reason : 'sem razão declarada');
                $dimResult['breakdown'][$idx]['na_justified'] = true;
            }
        }

        $dimResult['na_justified'][$dimKey] = $reason;
        return $dimResult;
    }

    /**
     * Conta total de sub-itens + dimensões N/A aplicados ao longo das 5 dimensões.
     */
    private function countNaApplied(array $dimensions): int
    {
        $total = 0;
        foreach ($dimensions as $dim) {
            $total += count($dim['na_justified'] ?? []);
        }
        return $total;
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
