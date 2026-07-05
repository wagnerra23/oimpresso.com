<?php

declare(strict_types=1);

use Modules\Governance\Services\ModuleGradeService;

uses(Tests\TestCase::class);

/**
 * Tests v3 da rubrica module-grade — 4 sub-dimensões novas (ADR 0155 proposto).
 *
 *   D6 Performance    (10 pts) — Inertia::defer + p99 + sem N+1
 *   D7 LGPD           (10 pts) — PiiRedactor + LogsActivity + retention
 *   D8 Security       (8  pts) — throttle + CSRF + FormRequest
 *   D9 Observability  (7  pts) — OTel spans + failed_jobs
 *
 * Pesos v3: 25+17+12+17+12+10+10+8+7 = 118 raw → normalizado pra 100.
 *
 * Service é puro filesystem inspection — funciona sem DB. Estes tests cobrem
 * 6 cenários blindando o contrato canônico v3 contra regressão.
 *
 * @see memory/decisions/0153-module-grade-rubrica-v1.md
 * @see memory/decisions/0154-na-justificado-rubrica-v2.md (proposto)
 * @see memory/decisions/0155-module-grade-rubrica-v3.md (proposto)
 */

// ─────────────────────────────────────────────────────────────────────────────
// CENÁRIO 1 — dim6Performance retorna array com breakdown + score + max
// ─────────────────────────────────────────────────────────────────────────────

it('cenário 1 — dim6Performance retorna estrutura canônica completa', function () {
    $service = app(ModuleGradeService::class);
    $grade = $service->gradeModule('Governance');

    expect($grade['dimensions'])->toHaveKey('performance');

    $d6 = $grade['dimensions']['performance'];
    expect($d6)->toHaveKeys(['weight', 'weight_v3', 'score', 'max', 'breakdown']);
    expect($d6['weight'])->toBe(10);
    expect($d6['weight_v3'])->toBe(10);
    expect($d6['max'])->toBe(10);
    expect($d6['score'])->toBeInt()->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(10);

    // 3 sub-itens: D6.a (Inertia::defer), D6.b (p99 placeholder), D6.c (N+1)
    expect($d6['breakdown'])->toBeArray()->toHaveCount(3);
    $keys = array_column($d6['breakdown'], 'key');
    expect($keys)->toBe(['D6.a', 'D6.b', 'D6.c']);

    // Max sub-itens somam 10
    $maxes = array_column($d6['breakdown'], 'max');
    expect(array_sum($maxes))->toBe(10);
});

// ─────────────────────────────────────────────────────────────────────────────
// CENÁRIO 2 — dim7Lgpd detecta LogsActivity em Models (Whatsapp como módulo real)
// ─────────────────────────────────────────────────────────────────────────────

it('cenário 2 — dim7LgpdCompliance possui D7.b LogsActivity com avaliação ratio Models', function () {
    $service = app(ModuleGradeService::class);
    $grade = $service->gradeModule('Whatsapp');

    expect($grade['dimensions'])->toHaveKey('lgpd');
    $d7 = $grade['dimensions']['lgpd'];

    // D7.b — LogsActivity em Models (3 pts max)
    $d7b = collect($d7['breakdown'])->firstWhere('key', 'D7.b');
    expect($d7b)->not->toBeNull();
    expect($d7b['max'])->toBe(3);
    expect($d7b['score'])->toBeInt()->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(3);
    expect($d7b['evidence'])->toBeString()->not->toBeEmpty();

    // 3 sub-itens: D7.a PiiRedactor, D7.b LogsActivity, D7.c Retention
    $keys = array_column($d7['breakdown'], 'key');
    expect($keys)->toBe(['D7.a', 'D7.b', 'D7.c']);

    // Soma maxes = 10 (4+3+3)
    expect(array_sum(array_column($d7['breakdown'], 'max')))->toBe(10);
});

// ─────────────────────────────────────────────────────────────────────────────
// CENÁRIO 3 — dim8Security detecta FormRequest cobertura
// ─────────────────────────────────────────────────────────────────────────────

it('cenário 3 — dim8Security expõe D8.c FormRequest ratio Requests/Controllers', function () {
    $service = app(ModuleGradeService::class);
    $grade = $service->gradeModule('Governance');

    expect($grade['dimensions'])->toHaveKey('security');
    $d8 = $grade['dimensions']['security'];

    expect($d8['weight'])->toBe(8);
    expect($d8['weight_v3'])->toBe(8);
    expect($d8['max'])->toBe(8);

    // D8.c — FormRequest cobertura (3 pts)
    $d8c = collect($d8['breakdown'])->firstWhere('key', 'D8.c');
    expect($d8c)->not->toBeNull();
    expect($d8c['max'])->toBe(3);
    expect($d8c['score'])->toBeInt()->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(3);
    // Evidence menciona ratio OR fallback (sem Controllers)
    expect($d8c['evidence'])->toBeString()->not->toBeEmpty();

    // 3 sub-itens
    $keys = array_column($d8['breakdown'], 'key');
    expect($keys)->toBe(['D8.a', 'D8.b', 'D8.c']);
});

// ─────────────────────────────────────────────────────────────────────────────
// CENÁRIO 4 — dim9Observability retorna placeholder gracioso sem OTel
// ─────────────────────────────────────────────────────────────────────────────

it('cenário 4 — dim9Observability retorna placeholder quando OTel ausente (graceful)', function () {
    $service = app(ModuleGradeService::class);
    // AssetManagement não tem OTel instrumentado nem failed_jobs query opt-in
    $grade = $service->gradeModule('AssetManagement');

    expect($grade['dimensions'])->toHaveKey('observability');
    $d9 = $grade['dimensions']['observability'];

    expect($d9['weight'])->toBe(7);
    expect($d9['weight_v3'])->toBe(7);
    expect($d9['max'])->toBe(7);

    // D9.b deve ser placeholder (config opt-in default false)
    $d9b = collect($d9['breakdown'])->firstWhere('key', 'D9.b');
    expect($d9b)->not->toBeNull();
    expect($d9b['max'])->toBe(3);
    // Score placeholder = 2 (50% de 3 arredondado pra cima) quando opt-in está desligado
    expect($d9b['score'])->toBeInt()->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(3);
    expect($d9b['evidence'])->toBeString()->not->toBeEmpty();

    // D9 score nunca explode (graceful)
    expect($d9['score'])->toBeInt()->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(7);
});

// ─────────────────────────────────────────────────────────────────────────────
// CENÁRIO 5 — score_v3_normalized = round(raw * 100/118) e consistência
// ─────────────────────────────────────────────────────────────────────────────

it('cenário 5 — score_v3_normalized respeita fórmula round(raw * 100 / 118)', function () {
    $service = app(ModuleGradeService::class);
    $grade = $service->gradeModule('Governance');

    // Chaves canônicas v3 expostas
    expect($grade)->toHaveKeys(['score', 'score_v3_normalized', 'score_v3_raw', 'weights_v3', 'weights_v3_total']);

    expect($grade['weights_v3_total'])->toBe(118);
    expect($grade['weights_v3'])->toBe([
        'multi_tenant'  => 25,
        'pest_coverage' => 17,
        'documentation' => 12,
        'architecture'  => 17,
        'client_real'   => 12,
        'performance'   => 10,
        'lgpd'          => 10,
        'security'      => 8,
        'observability' => 7,
    ]);

    // Soma dos weights_v3 = 118
    expect(array_sum($grade['weights_v3']))->toBe(118);

    // Score deve estar entre 0 e 100
    expect($grade['score_v3_normalized'])->toBeInt()->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(100);
    expect($grade['score_v3_raw'])->toBeInt()->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(118);

    // `score` é sinônimo de score_v3_normalized (backward-compat UI v1/v2)
    expect($grade['score'])->toBe($grade['score_v3_normalized']);

    // Reconstroi score raw via fórmula: sum (dim.score/dim.max * weight_v3)
    $reconstructed = 0.0;
    foreach ($grade['dimensions'] as $key => $dim) {
        $weight = $grade['weights_v3'][$key] ?? 0;
        $max = max(1, (int) $dim['max']);
        $reconstructed += ((float) $dim['score'] / $max) * $weight;
    }
    $expectedNormalized = (int) round($reconstructed * 100 / 118);

    // Tolerância de 1 ponto pra rounding em string→float em PHP
    expect(abs($grade['score_v3_normalized'] - $expectedNormalized))->toBeLessThanOrEqual(1);

    // 9 dimensões expostas
    expect(array_keys($grade['dimensions']))->toBe([
        'multi_tenant',
        'pest_coverage',
        'documentation',
        'architecture',
        'client_real',
        'performance',
        'lgpd',
        'security',
        'observability',
    ]);
});

// ─────────────────────────────────────────────────────────────────────────────
// SAFETY HARNESS — arquivos REAIS do repo + arquivos FAKE são protegidos contra
// drift mesmo em fatal/segfault/Ctrl+C/OOM (P0 BLOQUEADOR — ultrareview Bloco A
// 2026-05-16). Combina:
//
//   1. Mutex flock() em storage/framework/cache/ — serializa worktrees paralelos
//   2. register_shutdown_function — restaura SEMPRE, inclusive em fatal PHP
//   3. pcntl_signal handlers (Linux) — captura SIGINT/SIGTERM via Ctrl+C
//   4. try/finally — fluxo normal restaura first; shutdown handler é safety-net
//   5. Módulo fake __GovernanceTestFake__ pra cenários 6/9/10/11 — substitui
//      AssetManagement (arquivo versionado) por diretório efêmero não-tracked
//
// Risco residual: VerifyCsrfToken.php e Crm/SPEC.md NÃO têm versão fake (Service
// lê base_path() hardcoded — refactor pra path injection é PR separado). Pra
// esses 2 arquivos confiamos em (1)+(2)+(3)+(4) acima.
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Mutex flock — serializa execução de cenários que patcheiam arquivos REAIS
 * entre múltiplos processos Pest (e.g. Wagner rodando 2 worktrees em paralelo).
 *
 * Retorna o handle aberto; chamador é responsável por chamar releaseFsMutex().
 *
 * @return resource
 */
function acquireFsMutex()
{
    $lockDir = base_path('storage/framework/cache');
    if (! is_dir($lockDir)) {
        @mkdir($lockDir, 0755, true);
    }
    $lockPath = $lockDir . '/governance-grade-fs-mutex.lock';
    $handle = fopen($lockPath, 'c');
    if ($handle === false) {
        // Falha em criar lock não deve quebrar test — degrada pra "best-effort"
        return null;
    }
    // Blocking lock — espera worktree irmão liberar
    @flock($handle, LOCK_EX);
    return $handle;
}

function releaseFsMutex($handle): void
{
    if (is_resource($handle)) {
        @flock($handle, LOCK_UN);
        @fclose($handle);
    }
}

/**
 * Registra restauração resiliente:
 *  - try/finally restaura no caminho feliz (assertion ok ou Exception capturada)
 *  - register_shutdown_function cobre fatal error / die() / parse error
 *  - pcntl_signal cobre Ctrl+C e SIGTERM (Linux/Mac apenas — Windows ignora)
 *
 * O handler é IDEMPOTENTE: usa flag por path pra evitar dupla restauração
 * (try/finally já restaurou + shutdown handler dispararia de novo com lixo).
 *
 * @param string      $path     Caminho absoluto do arquivo a restaurar
 * @param string|null $original Conteúdo original (null = arquivo NÃO existia)
 */
function registerFileRestoreSafetyNet(string $path, ?string $original): void
{
    static $registered = [];
    static $signalsBound = false;

    // Track estado atual por path (último registro vence — match nested patches)
    $GLOBALS['__governance_test_restore_map'][$path] = $original;

    if (! isset($registered[$path])) {
        $registered[$path] = true;
        register_shutdown_function(function () use ($path) {
            if (! isset($GLOBALS['__governance_test_restore_done'][$path])) {
                $orig = $GLOBALS['__governance_test_restore_map'][$path] ?? null;
                if ($orig === null) {
                    @unlink($path); // arquivo era sintético — apaga
                } else {
                    @file_put_contents($path, $orig);
                }
                $GLOBALS['__governance_test_restore_done'][$path] = true;
            }
        });
    }

    // Bind signal handlers UMA vez (pcntl só existe em Linux/Mac)
    if (! $signalsBound && function_exists('pcntl_signal') && function_exists('pcntl_async_signals')) {
        pcntl_async_signals(true);
        $handler = function () {
            // Shutdown handlers vão rodar — só termina graciosamente
            exit(130);
        };
        pcntl_signal(SIGINT, $handler);
        pcntl_signal(SIGTERM, $handler);
        $signalsBound = true;
    }
}

/**
 * Marca path como "já restaurado" pelo try/finally — neutraliza shutdown handler.
 */
function markFileRestored(string $path): void
{
    $GLOBALS['__governance_test_restore_done'][$path] = true;
}

/**
 * Cria módulo FAKE efêmero pra isolar cenários N/A justificado dos arquivos
 * REAIS de AssetManagement/Crm. Cria 2 dirs com cleanup blindado:
 *
 *   - Modules/__GovernanceTestFake__/      (mínimo: dir vazia satisfaz is_dir())
 *   - memory/requisitos/__GovernanceTestFake__/SPEC.md  (conteúdo sintético)
 *
 * Service só exige `is_dir(Modules/<X>)` pra prosseguir (linha 96 do Service).
 * Cleanup: tanto try/finally do chamador quanto register_shutdown_function
 * removem ambos os dirs.
 *
 * @return array{moduleDir: string, memoryDir: string, specPath: string}
 */
function createFakeModule(string $fakeName = '__GovernanceTestFake__'): array
{
    $moduleDir  = base_path("Modules/{$fakeName}");
    $memoryDir  = base_path("memory/requisitos/{$fakeName}");
    $specPath   = $memoryDir . '/SPEC.md';

    if (! is_dir($moduleDir)) {
        @mkdir($moduleDir, 0755, true);
    }
    if (! is_dir($memoryDir)) {
        @mkdir($memoryDir, 0755, true);
    }

    // Marca cleanup do MÓDULO via shutdown handler (Service-required dir)
    if (! isset($GLOBALS['__governance_test_fake_module_registered'])) {
        $GLOBALS['__governance_test_fake_module_registered'] = true;
        register_shutdown_function(function () use ($moduleDir, $memoryDir) {
            // Remove arquivos órfãos primeiro
            if (is_dir($memoryDir)) {
                foreach ((array) @scandir($memoryDir) as $f) {
                    if ($f !== '.' && $f !== '..') {
                        @unlink($memoryDir . '/' . $f);
                    }
                }
                @rmdir($memoryDir);
            }
            if (is_dir($moduleDir)) {
                @rmdir($moduleDir);
            }
        });
    }

    return [
        'moduleDir' => $moduleDir,
        'memoryDir' => $memoryDir,
        'specPath'  => $specPath,
    ];
}

function cleanupFakeModule(array $fake): void
{
    @unlink($fake['specPath']);
    @rmdir($fake['memoryDir']);
    @rmdir($fake['moduleDir']);
    markFileRestored($fake['specPath']);
}

/**
 * Sobrescreve $except em VerifyCsrfToken.php temporariamente — BLINDADO contra
 * fatal/segfault via register_shutdown_function + mutex inter-worktree.
 *
 * Retorna conteúdo original pra restauração no try/finally do chamador.
 */
function patchVerifyCsrfTokenExcept(array $newExcept): string
{
    $path = base_path('app/Http/Middleware/VerifyCsrfToken.php');
    $original = file_get_contents($path);
    if ($original === false) {
        throw new RuntimeException("Não conseguiu ler {$path} pra snapshot — abort.");
    }

    // Safety-net ANTES do file_put_contents — se write falhar pelo meio, shutdown
    // handler restaura o que estiver lá
    registerFileRestoreSafetyNet($path, $original);

    $renderArray = '[' . implode(', ', array_map(fn ($p) => "'{$p}'", $newExcept)) . ']';
    $patched = preg_replace(
        '/(\$except\s*=\s*)\[.*?\]/s',
        '$1' . $renderArray,
        $original,
        1
    );

    file_put_contents($path, $patched);
    return $original;
}

function restoreVerifyCsrfToken(string $originalContent): void
{
    $path = base_path('app/Http/Middleware/VerifyCsrfToken.php');
    file_put_contents($path, $originalContent);
    markFileRestored($path);
}

/**
 * Snapshot + patch resiliente de SPEC.md (Crm cenários 7/8).
 */
function patchSpecResilient(string $path, string $newContent): ?string
{
    $original = file_exists($path) ? file_get_contents($path) : null;
    registerFileRestoreSafetyNet($path, $original);
    file_put_contents($path, $newContent);
    return $original;
}

function restoreSpec(string $path, ?string $originalContent): void
{
    if ($originalContent === null) {
        @unlink($path);
    } else {
        file_put_contents($path, $originalContent);
    }
    markFileRestored($path);
}

// ─────────────────────────────────────────────────────────────────────────────
// CENÁRIO 6 — N/A v2 continua funcionando em sub-itens D6-D9 (backward-compat)
// ─────────────────────────────────────────────────────────────────────────────

it('cenário 6 — N/A justificado v2 aplicável em D6.a (backward-compat sub-itens v3)', function () {
    // FIX P0 BLOQUEADOR — usa módulo FAKE __GovernanceTestFake__ em vez de
    // patchear AssetManagement/SPEC.md (arquivo REAL versionado). Diretório
    // não existe no repo, é criado/destruído por test — ZERO risco de drift.
    $fake = createFakeModule();
    $fakeName = '__GovernanceTestFake__';

    $syntheticSpec = <<<YAML
---
lifecycle: active
owner: [W]
module: {$fakeName}
na_justified:
  D6.a: "módulo CLI-only, sem Inertia::render"
---

# Test synthetic SPEC v3
YAML;

    registerFileRestoreSafetyNet($fake['specPath'], null);
    file_put_contents($fake['specPath'], $syntheticSpec);

    try {
        $service = app(ModuleGradeService::class);
        $grade = $service->gradeModule($fakeName);

        // D6.a deve estar marcado N/A com score=max (4)
        $d6aBreakdown = collect($grade['dimensions']['performance']['breakdown'])->firstWhere('key', 'D6.a');
        expect($d6aBreakdown)->not->toBeNull();
        expect($d6aBreakdown['score'])->toBe(4, 'D6.a N/A → score = max (4)');
        expect($d6aBreakdown['na_justified'] ?? false)->toBeTrue();
        expect($d6aBreakdown['evidence'])->toContain('N/A justificado')->toContain('CLI-only');

        // total_na_justified conta ≥1
        expect($grade['total_na_justified'])->toBeGreaterThanOrEqual(1);

        // Estrutura v3 completa preservada
        expect($grade)->toHaveKeys(['score_v3_normalized', 'score_v3_raw', 'weights_v3']);
    } finally {
        cleanupFakeModule($fake);
    }
});

// ─────────────────────────────────────────────────────────────────────────────
// CENÁRIO 7 — D8.b CSRF except penalty detectada quando route do módulo lista
// em app/Http/Middleware/VerifyCsrfToken.php::$except (ADR 0155 §D8.b)
// ─────────────────────────────────────────────────────────────────────────────

it('cenário 7 — D8.b CSRF except penalty aplicada quando route do módulo em VerifyCsrfToken::except', function () {
    // FIX P0 BLOQUEADOR — mutex flock serializa entre worktrees paralelos.
    // VerifyCsrfToken.php é arquivo único do framework (não tem fake).
    // Patch é blindado por register_shutdown_function + try/finally.
    $mutex = acquireFsMutex();

    $crmSpecPath = base_path('memory/requisitos/Crm/SPEC.md');
    $crmSpecBackup = null;
    $original = null;

    try {
        // Patch VerifyCsrfToken.php inserindo route que pertence ao módulo Crm
        $original = patchVerifyCsrfTokenExcept(['/install/details', 'crm/webhook/*']);

        // Bypass do SPEC Crm que declara `na_justified: { D8.b }` — substitui
        // por SPEC sintético sem na_justified pra evidenciar a heurística pura.
        if (file_exists($crmSpecPath)) {
            $crmSpecBackup = patchSpecResilient($crmSpecPath, <<<'YAML'
---
module: Crm
lifecycle: active
owner: [W]
---
# Test synthetic SPEC — sem na_justified pra validar penalty pura
YAML);
        }

        $service = app(ModuleGradeService::class);
        $grade = $service->gradeModule('Crm');

        $d8 = $grade['dimensions']['security'];
        $d8b = collect($d8['breakdown'])->firstWhere('key', 'D8.b');

        expect($d8b)->not->toBeNull();
        expect($d8b['max'])->toBe(2);
        expect($d8b['score'])->toBe(0, 'D8.b → 0 quando route do módulo está em VerifyCsrfToken::except');
        expect($d8b['evidence'])->toContain('PENALTY')->toContain('crm/webhook');
    } finally {
        if ($original !== null) {
            restoreVerifyCsrfToken($original);
        }
        if ($crmSpecBackup !== null) {
            restoreSpec($crmSpecPath, $crmSpecBackup);
        }
        releaseFsMutex($mutex);
    }
});

// ─────────────────────────────────────────────────────────────────────────────
// CENÁRIO 8 — D8.b sem penalty quando except só lista routes externas/genéricas
// ─────────────────────────────────────────────────────────────────────────────

it('cenário 8 — D8.b mantém default 2 pts quando except não lista route do módulo', function () {
    // FIX P0 BLOQUEADOR — mesma blindagem do cenário 7 (mutex + safety-net).
    $mutex = acquireFsMutex();

    $crmSpecPath = base_path('memory/requisitos/Crm/SPEC.md');
    $crmSpecBackup = null;
    $original = null;

    try {
        // Patch removendo qualquer prefix do módulo Crm — só /install + /api ecom
        $original = patchVerifyCsrfTokenExcept([
            '/install/details',
            '/install/post-details',
            '/api/ecom/customers',
        ]);

        // Bypass SPEC Crm (mesma razão do cenário 7)
        if (file_exists($crmSpecPath)) {
            $crmSpecBackup = patchSpecResilient($crmSpecPath, <<<'YAML'
---
module: Crm
lifecycle: active
owner: [W]
---
# Test synthetic SPEC — sem na_justified pra validar default 2 pts
YAML);
        }

        $service = app(ModuleGradeService::class);
        $grade = $service->gradeModule('Crm');

        $d8b = collect($grade['dimensions']['security']['breakdown'])->firstWhere('key', 'D8.b');

        expect($d8b)->not->toBeNull();
        expect($d8b['score'])->toBe(2, 'D8.b default 2 pts (Laravel CSRF on, sem except do módulo)');
        expect($d8b['evidence'])->toContain('default Laravel CSRF on');
    } finally {
        if ($original !== null) {
            restoreVerifyCsrfToken($original);
        }
        if ($crmSpecBackup !== null) {
            restoreSpec($crmSpecPath, $crmSpecBackup);
        }
        releaseFsMutex($mutex);
    }
});

// ─────────────────────────────────────────────────────────────────────────────
// CENÁRIO 9 — na_justified_v3 exclui sub-dim v3 (D6.a) + denominador re-normaliza
// ─────────────────────────────────────────────────────────────────────────────

it('cenário 9 — na_justified_v3 D6.a aceito e aplicado igual a na_justified v2', function () {
    // FIX P0 BLOQUEADOR — módulo FAKE (não toca AssetManagement real)
    $fake = createFakeModule();
    $fakeName = '__GovernanceTestFake__';

    $syntheticSpec = <<<YAML
---
lifecycle: active
owner: [W]
module: {$fakeName}
na_justified_v3:
  D6.a: "CLI-only, sem Inertia (declarado via na_justified_v3 ADR 0155)"
---

# Test synthetic SPEC v3 — chave nova
YAML;

    registerFileRestoreSafetyNet($fake['specPath'], null);
    file_put_contents($fake['specPath'], $syntheticSpec);

    try {
        $service = app(ModuleGradeService::class);
        $grade = $service->gradeModule($fakeName);

        $d6aBreakdown = collect($grade['dimensions']['performance']['breakdown'])->firstWhere('key', 'D6.a');
        expect($d6aBreakdown)->not->toBeNull();
        expect($d6aBreakdown['score'])->toBe(4, 'D6.a N/A (via v3) → score = max');
        expect($d6aBreakdown['na_justified'] ?? false)->toBeTrue();
        expect($d6aBreakdown['evidence'])->toContain('N/A justificado')->toContain('CLI-only');
        expect($grade['total_na_justified'])->toBeGreaterThanOrEqual(1);
    } finally {
        cleanupFakeModule($fake);
    }
});

// ─────────────────────────────────────────────────────────────────────────────
// CENÁRIO 10 — na_justified (v2) + na_justified_v3 coexistem no MESMO SPEC
// ─────────────────────────────────────────────────────────────────────────────

it('cenário 10 — na_justified v2 + na_justified_v3 coexistem (merge)', function () {
    // FIX P0 BLOQUEADOR — módulo FAKE
    $fake = createFakeModule();
    $fakeName = '__GovernanceTestFake__';

    $syntheticSpec = <<<YAML
---
lifecycle: active
owner: [W]
module: {$fakeName}
na_justified:
  D5: "Asset CLI-only — sem cliente externo (v2 backward-compat)"
na_justified_v3:
  D6.a: "Sem Inertia — CLI-only (v3 chave nova)"
---

# Test synthetic SPEC — coexistência v2 + v3
YAML;

    registerFileRestoreSafetyNet($fake['specPath'], null);
    file_put_contents($fake['specPath'], $syntheticSpec);

    try {
        $service = app(ModuleGradeService::class);
        $grade = $service->gradeModule($fakeName);

        // D5 marcado N/A via v2
        $d5 = $grade['dimensions']['client_real'];
        expect($d5['score'])->toBe($d5['max'], 'D5 N/A via na_justified v2');

        // D6.a marcado N/A via v3
        $d6a = collect($grade['dimensions']['performance']['breakdown'])->firstWhere('key', 'D6.a');
        expect($d6a['na_justified'] ?? false)->toBeTrue('D6.a N/A via na_justified_v3');
        expect($d6a['score'])->toBe(4);

        // total_na_justified conta ambos (mín 2)
        expect($grade['total_na_justified'])->toBeGreaterThanOrEqual(2);
    } finally {
        cleanupFakeModule($fake);
    }
});

// ─────────────────────────────────────────────────────────────────────────────
// CENÁRIO 11 — na_justified_v3 com chave de dim v2 (D5) também é aceito
// (compat ampliado — ADR 0155 não restringe na_justified_v3 a D6-D9)
// ─────────────────────────────────────────────────────────────────────────────

it('cenário 11 — na_justified_v3 aceita chave de dim v2 (D5) — compat ampliado', function () {
    // FIX P0 BLOQUEADOR — módulo FAKE
    $fake = createFakeModule();
    $fakeName = '__GovernanceTestFake__';

    // Declara D5 em na_justified_v3 (chave v3 com dim v2) — Service deve aceitar
    $syntheticSpec = <<<YAML
---
lifecycle: active
owner: [W]
module: {$fakeName}
na_justified_v3:
  D5: "Asset CLI-only — declarado via chave nova v3 (compat ampliado ADR 0155)"
---

# Test synthetic SPEC — D5 declarado em na_justified_v3 (canal preferencial)
YAML;

    registerFileRestoreSafetyNet($fake['specPath'], null);
    file_put_contents($fake['specPath'], $syntheticSpec);

    try {
        $service = app(ModuleGradeService::class);
        $grade = $service->gradeModule($fakeName);

        // D5 deve estar marcado N/A — Service trata chave v3 como ampliação de v2
        $d5 = $grade['dimensions']['client_real'];
        expect($d5['score'])->toBe($d5['max'], 'D5 N/A via na_justified_v3 (compat ampliado)');
        expect($grade['total_na_justified'])->toBeGreaterThanOrEqual(1);
    } finally {
        cleanupFakeModule($fake);
    }
});

// ─────────────────────────────────────────────────────────────────────────────
// CENÁRIO 12 — D9.a detecta padrão canônico OtelHelper::spanBiz (ADR 0156 §Errata 1)
//
// Confirma que regex atualizada captura a facade canônica oimpresso
// (`app/Util/OtelHelper.php`), corrigindo "falsa promessa" da ADR 0156 antes do fix:
// módulos como Sells FSM, Jana, Whatsapp que usam o canônico pontuariam ZERO.
// ─────────────────────────────────────────────────────────────────────────────

it('cenário 12 — D9.a detecta OtelHelper::spanBiz canônico (regex ADR 0156 §Errata 1)', function () {
    // Módulo fake — Service exige is_dir(Modules/<X>/Services) pra contar arquivos
    $fake = createFakeModule();
    $fakeName = '__GovernanceTestFake__';

    $servicesDir = $fake['moduleDir'] . '/Services';
    if (! is_dir($servicesDir)) {
        @mkdir($servicesDir, 0755, true);
    }
    $fakeService = $servicesDir . '/FakeOtelService.php';

    // Service que invoca facade canônica `OtelHelper::spanBiz(...)` — padrão prod-real
    // (igual MessagePersister, MetaCloudDriver, MeilisearchDriver, FluxoCaixaService, etc.)
    $fakeServiceContent = <<<'PHP'
<?php

namespace Modules\__GovernanceTestFake__\Services;

class FakeOtelService
{
    public function run(): bool
    {
        return \App\Util\OtelHelper::spanBiz('test.fake.action', fn () => true);
    }
}
PHP;
    file_put_contents($fakeService, $fakeServiceContent);
    registerFileRestoreSafetyNet($fakeService, null);

    // Cleanup do dir Services (não coberto pelo registerFakeModule)
    register_shutdown_function(function () use ($servicesDir) {
        if (is_dir($servicesDir)) {
            foreach ((array) @scandir($servicesDir) as $f) {
                if ($f !== '.' && $f !== '..') {
                    @unlink($servicesDir . '/' . $f);
                }
            }
            @rmdir($servicesDir);
        }
    });

    try {
        $service = app(ModuleGradeService::class);
        $grade = $service->gradeModule($fakeName);

        $d9a = collect($grade['dimensions']['observability']['breakdown'])->firstWhere('key', 'D9.a');
        expect($d9a)->not->toBeNull();
        // 1/1 Services com OTel → score = 4 (max)
        expect($d9a['score'])->toBe(4, 'D9.a → 4 quando 100% Services usam OtelHelper::spanBiz canônico');
        expect($d9a['evidence'])->toContain('1/1');
    } finally {
        @unlink($fakeService);
        @rmdir($servicesDir);
        markFileRestored($fakeService);
        cleanupFakeModule($fake);
    }
});

// ─────────────────────────────────────────────────────────────────────────────
// CENÁRIO 13 — D9.a NÃO faz match em menção apenas em comentário (sem invocação)
//
// Regra `\s*\(` no regex força parêntese após `OtelHelper::span[Biz]`, evitando
// match em docblocks/comentários que apenas mencionam a API (ex.: MeilisearchDriver:77
// "pra OtelHelper::spanBiz envolver sem afetar comportamento").
// ─────────────────────────────────────────────────────────────────────────────

it('cenário 13 — D9.a NÃO faz match em menção textual de OtelHelper sem invocação', function () {
    $fake = createFakeModule();
    $fakeName = '__GovernanceTestFake__';

    $servicesDir = $fake['moduleDir'] . '/Services';
    if (! is_dir($servicesDir)) {
        @mkdir($servicesDir, 0755, true);
    }
    $fakeService = $servicesDir . '/FakeNoOtelService.php';

    // Service que MENCIONA OtelHelper apenas em comentário/docblock — sem invocar.
    // Não deve pontuar D9.a (regex exige `\s*\(`).
    $fakeServiceContent = <<<'PHP'
<?php

namespace Modules\__GovernanceTestFake__\Services;

class FakeNoOtelService
{
    /**
     * Implementação isolada — preservada idêntica pra OtelHelper::spanBiz envolver
     * sem afetar comportamento. (TODO: futuro — wrap em facade canônica)
     */
    public function run(): bool
    {
        // OtelHelper::span foi planejado mas ainda não implementado.
        return true;
    }
}
PHP;
    file_put_contents($fakeService, $fakeServiceContent);
    registerFileRestoreSafetyNet($fakeService, null);

    register_shutdown_function(function () use ($servicesDir) {
        if (is_dir($servicesDir)) {
            foreach ((array) @scandir($servicesDir) as $f) {
                if ($f !== '.' && $f !== '..') {
                    @unlink($servicesDir . '/' . $f);
                }
            }
            @rmdir($servicesDir);
        }
    });

    try {
        $service = app(ModuleGradeService::class);
        $grade = $service->gradeModule($fakeName);

        $d9a = collect($grade['dimensions']['observability']['breakdown'])->firstWhere('key', 'D9.a');
        expect($d9a)->not->toBeNull();
        // 0/1 Services com OTel → score = 0 (regex `\s*\(` exige parêntese)
        expect($d9a['score'])->toBe(0, 'D9.a → 0 quando apenas comentário menciona OtelHelper (sem invocação)');
        expect($d9a['evidence'])->toContain('0/1');
    } finally {
        @unlink($fakeService);
        @rmdir($servicesDir);
        markFileRestored($fakeService);
        cleanupFakeModule($fake);
    }
});

// ─────────────────────────────────────────────────────────────────────────────
// SECTION D2 HARDENED (ADR 0157) — endurecimento detection com dual-mode flag
// ─────────────────────────────────────────────────────────────────────────────
//
// Helpers locais pra cenários hardened — patcham phpunit.xml temporariamente
// inserindo `<directory>` linhas pra módulo fake, com safety-net.

function patchPhpunitInsertDirectories(string $fakeName, array $relativeDirs): string
{
    $path = base_path('phpunit.xml');
    $original = file_get_contents($path);
    if ($original === false) {
        throw new RuntimeException("Não conseguiu ler phpunit.xml pra snapshot — abort.");
    }

    registerFileRestoreSafetyNet($path, $original);

    // Insere linhas `<directory>./Modules/<fakeName>/Tests/Xxx</directory>` ANTES
    // do fechamento do testsuite Feature. Marcador: `</testsuite>` precedido pela
    // linha do último Modules/<>/Tests/Feature já presente.
    $injection = '';
    foreach ($relativeDirs as $rel) {
        $injection .= "            <directory>./{$rel}</directory>\n";
    }

    // Insere logo antes do `</testsuite>` que fecha o Feature suite. Substituição
    // simples na PRIMEIRA ocorrência depois do nome="Feature".
    $patched = preg_replace(
        '#(</testsuite>\s*</testsuites>)#',
        $injection . '$1',
        $original,
        1
    );

    if ($patched === null || $patched === $original) {
        // Fallback: insere antes do último </testsuite>
        $patched = preg_replace(
            '#(</testsuite>)(\s*</testsuites>)#s',
            $injection . '$1$2',
            $original,
            1
        );
    }

    file_put_contents($path, $patched);
    return $original;
}

function restorePhpunit(string $originalContent): void
{
    $path = base_path('phpunit.xml');
    file_put_contents($path, $originalContent);
    markFileRestored($path);
}

// ─────────────────────────────────────────────────────────────────────────────
// CENÁRIO 14 — D2 hardened NÃO credita Tests/Feature dir NÃO REGISTRADO
//
// Setup: módulo fake com Tests/Feature/SmokeTest.php (com asserção), mas SEM
// entry no phpunit.xml. Hardened deve dar D2.a=0 (nenhum arquivo registrado) e
// D2.c=0 (0 dirs). Legacy daria D2.a alto + D2.c=4 (str_contains false positive).
// ─────────────────────────────────────────────────────────────────────────────

it('cenário 14 — d2_hardened NÃO credita Tests/Feature dir não-registrado em phpunit.xml', function () {
    config(['governance.d2_hardened' => true]);

    $fake = createFakeModule();
    $fakeName = '__GovernanceTestFake__';

    // Cria Tests/Feature com arquivo CONTENDO asserção real
    $testsFeatureDir = $fake['moduleDir'] . '/Tests/Feature';
    @mkdir($testsFeatureDir, 0755, true);
    $smokeTest = $testsFeatureDir . '/SmokeRoutesTest.php';
    file_put_contents($smokeTest, <<<'PHP'
<?php
test('smoke route', function () {
    expect(1)->toBe(1);
});
PHP);
    registerFileRestoreSafetyNet($smokeTest, null);

    // Cleanup blindado
    register_shutdown_function(function () use ($testsFeatureDir) {
        if (is_dir($testsFeatureDir)) {
            foreach ((array) @scandir($testsFeatureDir) as $f) {
                if ($f !== '.' && $f !== '..') {
                    @unlink($testsFeatureDir . '/' . $f);
                }
            }
            @rmdir($testsFeatureDir);
            @rmdir(dirname($testsFeatureDir));
        }
    });

    try {
        $service = app(ModuleGradeService::class);
        $grade = $service->gradeModule($fakeName);

        $d2 = $grade['dimensions']['pest_coverage'];
        expect($d2)->toHaveKey('mode');
        expect($d2['mode'])->toBe('hardened');

        // D2.c hardened — 0 dirs registrados → score 0
        $d2c = collect($d2['breakdown'])->firstWhere('key', 'D2.c');
        expect($d2c['score'])->toBe(0, 'D2.c hardened → 0 quando dir não registrado em phpunit.xml');
        expect($d2c['evidence'])->toContain('NÃO REGISTRADO');

        // D2.a hardened — 0 tests contados (nenhum dir registrado) → score 0 (sem controllers, score sobe a 8 — adjust)
        // Como fake module tem 0 controllers, ratio = 1.0 → d2a = 8 mesmo. Validamos evidence em vez disso.
        $d2a = collect($d2['breakdown'])->firstWhere('key', 'D2.a');
        expect($d2a['evidence'])->toContain('dirs: NENHUM');
    } finally {
        @unlink($smokeTest);
        @rmdir($testsFeatureDir);
        @rmdir(dirname($testsFeatureDir));
        markFileRestored($smokeTest);
        cleanupFakeModule($fake);
        config(['governance.d2_hardened' => false]);
    }
});

// ─────────────────────────────────────────────────────────────────────────────
// CENÁRIO 15 — D2 hardened credita INTEGRAL quando 2+ dirs registrados
//
// Setup: módulo fake com Tests/Feature + Tests/Unit, ambos registrados em
// phpunit.xml via patch temporário. Hardened deve dar D2.c=4 (integral).
// ─────────────────────────────────────────────────────────────────────────────

it('cenário 15 — d2_hardened credita FULL 4pts em D2.c quando Tests/Feature + Tests/Unit registrados', function () {
    config(['governance.d2_hardened' => true]);
    $mutex = acquireFsMutex();

    $fake = createFakeModule();
    $fakeName = '__GovernanceTestFake__';

    $testsFeatureDir = $fake['moduleDir'] . '/Tests/Feature';
    $testsUnitDir = $fake['moduleDir'] . '/Tests/Unit';
    @mkdir($testsFeatureDir, 0755, true);
    @mkdir($testsUnitDir, 0755, true);

    $featureTest = $testsFeatureDir . '/MultiTenantDecisionTest.php';
    file_put_contents($featureTest, <<<'PHP'
<?php
test('multi tenant decision', function () {
    expect(true)->toBeTrue();
});
PHP);
    registerFileRestoreSafetyNet($featureTest, null);

    $unitTest = $testsUnitDir . '/ScaffoldTest.php';
    file_put_contents($unitTest, <<<'PHP'
<?php
test('scaffold', function () {
    expect('x')->toBe('x');
});
PHP);
    registerFileRestoreSafetyNet($unitTest, null);

    register_shutdown_function(function () use ($testsFeatureDir, $testsUnitDir) {
        foreach ([$testsFeatureDir, $testsUnitDir] as $d) {
            if (is_dir($d)) {
                foreach ((array) @scandir($d) as $f) {
                    if ($f !== '.' && $f !== '..') {
                        @unlink($d . '/' . $f);
                    }
                }
                @rmdir($d);
            }
        }
        @rmdir(dirname($testsFeatureDir));
    });

    // Patcha phpunit.xml registrando ambos dirs
    $originalPhpunit = patchPhpunitInsertDirectories($fakeName, [
        "Modules/{$fakeName}/Tests/Feature",
        "Modules/{$fakeName}/Tests/Unit",
    ]);

    try {
        $service = app(ModuleGradeService::class);
        $grade = $service->gradeModule($fakeName);

        $d2 = $grade['dimensions']['pest_coverage'];
        expect($d2['mode'])->toBe('hardened');

        $d2c = collect($d2['breakdown'])->firstWhere('key', 'D2.c');
        expect($d2c['score'])->toBe(4, 'D2.c hardened → 4 (INTEGRAL) com Tests/Feature + Tests/Unit');
        expect($d2c['evidence'])->toContain('INTEGRAL')->toContain('2 dirs');

        // D2.b hardened — MultiTenant + Scaffold detectados COM asserção → 2/3 padrões = 5 pts (round)
        $d2b = collect($d2['breakdown'])->firstWhere('key', 'D2.b');
        expect($d2b['score'])->toBeGreaterThanOrEqual(5, 'D2.b hardened detecta MultiTenant + Scaffold com expect()');
        expect($d2b['evidence'])->toContain('MultiTenant')->toContain('Scaffold');
    } finally {
        restorePhpunit($originalPhpunit);
        @unlink($featureTest);
        @unlink($unitTest);
        @rmdir($testsFeatureDir);
        @rmdir($testsUnitDir);
        @rmdir(dirname($testsFeatureDir));
        markFileRestored($featureTest);
        markFileRestored($unitTest);
        cleanupFakeModule($fake);
        releaseFsMutex($mutex);
        config(['governance.d2_hardened' => false]);
    }
});

// ─────────────────────────────────────────────────────────────────────────────
// CENÁRIO 16 — D2 hardened NÃO credita arquivo registrado SEM asserção real
//
// Setup: módulo fake com Tests/Feature/MultiTenantTest.php registrado em
// phpunit.xml, mas corpo SEM `expect`/`assert`. Hardened deve dar D2.b=0 (nome
// casa mas sem asserção — anti-gaming scaffold vazio). Legacy daria 8/3 ≈ 3 pts.
// ─────────────────────────────────────────────────────────────────────────────

it('cenário 16 — d2_hardened NÃO credita test file registrado SEM asserção real no corpo', function () {
    config(['governance.d2_hardened' => true]);
    $mutex = acquireFsMutex();

    $fake = createFakeModule();
    $fakeName = '__GovernanceTestFake__';

    $testsFeatureDir = $fake['moduleDir'] . '/Tests/Feature';
    @mkdir($testsFeatureDir, 0755, true);

    // Scaffold vazio: NOME canônico MultiTenant, mas corpo só placeholder sem
    // expect/assert — D2.b hardened deve rejeitar.
    $scaffoldTest = $testsFeatureDir . '/MultiTenantTest.php';
    file_put_contents($scaffoldTest, <<<'PHP'
<?php

// TODO: implementar asserções multi-tenant biz=1 vs biz=99 (ADR 0093)
test('placeholder', function () {
    // sem expect / sem assert — scaffold só
});
PHP);
    registerFileRestoreSafetyNet($scaffoldTest, null);

    register_shutdown_function(function () use ($testsFeatureDir) {
        if (is_dir($testsFeatureDir)) {
            foreach ((array) @scandir($testsFeatureDir) as $f) {
                if ($f !== '.' && $f !== '..') {
                    @unlink($testsFeatureDir . '/' . $f);
                }
            }
            @rmdir($testsFeatureDir);
            @rmdir(dirname($testsFeatureDir));
        }
    });

    // Registra Tests/Feature em phpunit.xml pra arquivo CONTAR no filesystem
    $originalPhpunit = patchPhpunitInsertDirectories($fakeName, [
        "Modules/{$fakeName}/Tests/Feature",
    ]);

    try {
        $service = app(ModuleGradeService::class);
        $grade = $service->gradeModule($fakeName);

        $d2 = $grade['dimensions']['pest_coverage'];
        expect($d2['mode'])->toBe('hardened');

        // D2.b — MultiTenant detectado por NOME mas SEM asserção → canonicalCount=0
        $d2b = collect($d2['breakdown'])->firstWhere('key', 'D2.b');
        expect($d2b['score'])->toBe(0, 'D2.b hardened → 0 quando MultiTenantTest.php existe mas sem asserção real');
        expect($d2b['evidence'])->toContain('0/3');

        // D2.c — 1 dir registrado → PARCIAL = 2 pts
        $d2c = collect($d2['breakdown'])->firstWhere('key', 'D2.c');
        expect($d2c['score'])->toBe(2, 'D2.c hardened → 2 (PARCIAL) com apenas Tests/Feature registrado');
        expect($d2c['evidence'])->toContain('PARCIAL');
    } finally {
        restorePhpunit($originalPhpunit);
        @unlink($scaffoldTest);
        @rmdir($testsFeatureDir);
        @rmdir(dirname($testsFeatureDir));
        markFileRestored($scaffoldTest);
        cleanupFakeModule($fake);
        releaseFsMutex($mutex);
        config(['governance.d2_hardened' => false]);
    }
});

// ─────────────────────────────────────────────────────────────────────────────
// CENÁRIO 17 — Backward-compat: dual-mode dispatcher preserva legacy quando flag false
//
// Garante que sem flag set (default), Service retorna mode=legacy + score
// idêntico à heurística ADR 0155 (regressão zero pra baseline atual).
// ─────────────────────────────────────────────────────────────────────────────

it('cenário 17 — d2 default (flag false) retorna mode=legacy preservando ADR 0155', function () {
    // Garante flag false (default canônico Fase 1 ADR 0157)
    config(['governance.d2_hardened' => false]);

    $service = app(ModuleGradeService::class);
    $grade = $service->gradeModule('Governance');

    $d2 = $grade['dimensions']['pest_coverage'];
    expect($d2)->toHaveKey('mode');
    expect($d2['mode'])->toBe('legacy', 'default flag=false → modo legacy preservado (backward-compat ADR 0157)');

    // Score continua válido (0-20)
    expect($d2['score'])->toBeInt()->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(20);
    expect(array_column($d2['breakdown'], 'key'))->toBe(['D2.a', 'D2.b', 'D2.c']);
});

// ─────────────────────────────────────────────────────────────────────────────
// CENÁRIO 18 — D8.a detecta throttle declarado em ARRAY de middleware
//
// Forma canônica UltimatePOS: Route::middleware(['web', ..., 'throttle:60,1'])
// (multi-line). Antes do fix 2026-07-05, só ->middleware('throttle:...') como
// primeiro arg string pontuava — Compras tinha throttle REAL (audit sênior
// 2026-05-25 Gap #3) e D8.a devolvia 0 (falso-negativo).
// ─────────────────────────────────────────────────────────────────────────────

it('cenário 18 — D8.a detecta throttle em array de middleware (forma UltimatePOS)', function () {
    $fake = createFakeModule();
    $fakeName = '__GovernanceTestFake__';

    $routesDir = $fake['moduleDir'] . '/Routes';
    if (! is_dir($routesDir)) {
        @mkdir($routesDir, 0755, true);
    }
    $routesFile = $routesDir . '/web.php';

    // Espelha Modules/Compras/Routes/web.php — throttle no MEIO do array, multi-line
    $routesContent = <<<'PHP'
<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'SetSessionData', 'language', 'timezone',
    'AdminSidebarMenu', 'CheckUserLogin', 'throttle:60,1'])
    ->prefix('fake')
    ->group(function () {
        Route::get('/', fn () => 'ok');
    });
PHP;
    file_put_contents($routesFile, $routesContent);
    registerFileRestoreSafetyNet($routesFile, null);

    try {
        $service = app(ModuleGradeService::class);
        $grade = $service->gradeModule($fakeName);

        $d8a = collect($grade['dimensions']['security']['breakdown'])->firstWhere('key', 'D8.a');
        expect($d8a)->not->toBeNull();
        expect($d8a['score'])->toBe(3, 'D8.a → 3 quando throttle declarado em array de middleware (forma canônica UltimatePOS)');
        expect($d8a['evidence'])->toContain('throttle');
    } finally {
        @unlink($routesFile);
        @rmdir($routesDir);
        markFileRestored($routesFile);
        cleanupFakeModule($fake);
    }
});

// ─────────────────────────────────────────────────────────────────────────────
// CENÁRIO 19 — D8.a continua 0 em rota SEM throttle (anti-falso-positivo)
//
// Garante que o regex novo de array não pontua qualquer middleware([...]) —
// só quando a string 'throttle:'/'throttle.' aparece DENTRO do array.
// ─────────────────────────────────────────────────────────────────────────────

it('cenário 19 — D8.a NÃO pontua array de middleware sem throttle', function () {
    $fake = createFakeModule();
    $fakeName = '__GovernanceTestFake__';

    $routesDir = $fake['moduleDir'] . '/Routes';
    if (! is_dir($routesDir)) {
        @mkdir($routesDir, 0755, true);
    }
    $routesFile = $routesDir . '/web.php';

    $routesContent = <<<'PHP'
<?php

use Illuminate\Support\Facades\Route;

// Comentário mencionando Throttle 60/min NÃO deve pontuar — só código real conta.
Route::middleware(['web', 'auth', 'SetSessionData'])
    ->prefix('fake')
    ->group(function () {
        Route::get('/', fn () => 'ok');
    });
PHP;
    file_put_contents($routesFile, $routesContent);
    registerFileRestoreSafetyNet($routesFile, null);

    try {
        $service = app(ModuleGradeService::class);
        $grade = $service->gradeModule($fakeName);

        $d8a = collect($grade['dimensions']['security']['breakdown'])->firstWhere('key', 'D8.a');
        expect($d8a)->not->toBeNull();
        expect($d8a['score'])->toBe(0, 'D8.a → 0 quando rotas existem mas nenhuma tem throttle');
    } finally {
        @unlink($routesFile);
        @rmdir($routesDir);
        markFileRestored($routesFile);
        cleanupFakeModule($fake);
    }
});

// ─────────────────────────────────────────────────────────────────────────────
// CENÁRIO 20 — D4.d detecta OtelHelper::spanBiz e Spatie\Activitylog (paridade D9.a)
//
// D9.a já detectava a facade canônica (ADR 0156 §Errata 1); D4.d usava regex
// própria SEM OtelHelper nem Spatie\Activitylog → módulo com telemetry real em
// TODO método (caso Compras: OtelHelper::spanBiz + Activity::query timeline)
// pontuava 0 no D4.d e 4/4 no D9.a — inconsistência interna da rubrica.
// ─────────────────────────────────────────────────────────────────────────────

it('cenário 20 — D4.d detecta OtelHelper::spanBiz invocado em Service (paridade com D9.a)', function () {
    $fake = createFakeModule();
    $fakeName = '__GovernanceTestFake__';

    $servicesDir = $fake['moduleDir'] . '/Services';
    if (! is_dir($servicesDir)) {
        @mkdir($servicesDir, 0755, true);
    }
    $fakeService = $servicesDir . '/FakeAuditTelemetryService.php';

    // Espelha Modules/Compras/Services/ComprasService.php — OtelHelper::spanBiz
    // invocado + Activity model do Spatie\Activitylog (timeline drawer).
    $fakeServiceContent = <<<'PHP'
<?php

namespace Modules\__GovernanceTestFake__\Services;

use Spatie\Activitylog\Models\Activity;

class FakeAuditTelemetryService
{
    public function run(): bool
    {
        return \App\Util\OtelHelper::spanBiz('test.fake.action', fn () => true);
    }
}
PHP;
    file_put_contents($fakeService, $fakeServiceContent);
    registerFileRestoreSafetyNet($fakeService, null);

    try {
        $service = app(ModuleGradeService::class);
        $grade = $service->gradeModule($fakeName);

        $d4d = collect($grade['dimensions']['architecture']['breakdown'])->firstWhere('key', 'D4.d');
        expect($d4d)->not->toBeNull();
        expect($d4d['score'])->toBe(4, 'D4.d → 4 quando Service invoca OtelHelper::spanBiz / usa Spatie\Activitylog');
    } finally {
        @unlink($fakeService);
        @rmdir($servicesDir);
        markFileRestored($fakeService);
        cleanupFakeModule($fake);
    }
});
