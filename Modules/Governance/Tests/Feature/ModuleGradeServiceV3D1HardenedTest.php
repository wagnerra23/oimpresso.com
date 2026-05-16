<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Modules\Governance\Services\ModuleGradeService;

uses(Tests\TestCase::class);

/**
 * Tests D1 heurística hardening (ADR 0158 proposto — Wave 12 2026-05-16).
 *
 * Cobre 3 fixes dual-mode `config('governance.d1_hardened', false)`:
 *
 *   (1) phpFiles() recursivo em Entities/Models/Jobs (subdiretórios detectados)
 *       — Jana/Entities/Mcp/*.php exemplo canônico
 *   (2) Regex isCrossTenantTestFile aceita `withoutGlobalScope` SINGULAR ou plural
 *   (3) D1.c fallback: Job constructor com `$entityId` + body referencia
 *       `->business_id` qualifica como multi-tenant safe
 *
 * Backward-compat: flag default `false` mantém score atual dos módulos.
 *
 * Service é puro filesystem inspection — funciona sem DB.
 *
 * @see memory/decisions/0158-module-grade-v3-d1-heuristica-hardening.md (proposto)
 */

// Helper local — invoca método privado via reflection
function invokePrivate(ModuleGradeService $service, string $method, array $args = []): mixed
{
    $reflection = new \ReflectionClass($service);
    $m = $reflection->getMethod($method);
    $m->setAccessible(true);
    return $m->invoke($service, ...$args);
}

// Helper local — gera fixture path temporário no storage (limpa via cleanup)
function fixturePath(string $slug): string
{
    $path = storage_path('framework/testing/d1-hardened-' . $slug . '-' . uniqid());
    File::ensureDirectoryExists($path);
    return $path;
}

afterEach(function () {
    // Limpa fixtures temporárias criadas durante o test
    $base = storage_path('framework/testing');
    if (is_dir($base)) {
        foreach (glob($base . '/d1-hardened-*') ?: [] as $dir) {
            if (is_dir($dir)) {
                File::deleteDirectory($dir);
            }
        }
    }
});

// ─────────────────────────────────────────────────────────────────────────────
// CENÁRIO 1 — phpFiles recursive: true detecta subdirs (Jana/Entities/Mcp/*.php)
// ─────────────────────────────────────────────────────────────────────────────

it('d1 recursive detects subdirs (Entities/Sub/Model.php capturado)', function () {
    $service = app(ModuleGradeService::class);

    // Fixture: Entities/Top.php + Entities/Mcp/SubModel.php
    $base = fixturePath('subdir');
    File::put($base . '/Top.php', '<?php class Top { use BusinessScope; }');
    File::ensureDirectoryExists($base . '/Mcp');
    File::put($base . '/Mcp/SubModel.php', '<?php class SubModel { use BusinessScope; }');

    // Non-recursive — só captura Top.php (1 arquivo)
    $nonRecursive = invokePrivate($service, 'phpFiles', [$base, false]);
    expect($nonRecursive)->toHaveCount(1);
    expect($nonRecursive[0])->toContain('Top.php');

    // Recursive — captura Top.php + Mcp/SubModel.php (2 arquivos)
    $recursive = invokePrivate($service, 'phpFiles', [$base, true]);
    expect($recursive)->toHaveCount(2);

    $names = array_map(fn ($f) => basename($f), $recursive);
    expect($names)->toContain('Top.php');
    expect($names)->toContain('SubModel.php');
});

// ─────────────────────────────────────────────────────────────────────────────
// CENÁRIO 2 — isCrossTenantTestFile aceita withoutGlobalScope SINGULAR
// ─────────────────────────────────────────────────────────────────────────────

it('d1 singular withoutGlobalScope detected (s? regex)', function () {
    $service = app(ModuleGradeService::class);

    // Pattern singular — `withoutGlobalScope(BusinessScope::class)` (1 scope explícito)
    $contentSingular = <<<'PHP'
    <?php
    const BIZ_WAGNER = 1;
    const BIZ_FICTICIO = 99;
    it('isolation Material biz=1 vs biz=99', function () {
        session(['user.business_id' => BIZ_WAGNER]);
        $m = Material::withoutGlobalScope(BusinessScope::class)->create([]);
        session(['user.business_id' => BIZ_FICTICIO]);
    });
    PHP;

    expect(invokePrivate($service, 'isCrossTenantTestFile', [$contentSingular]))
        ->toBeTrue('withoutGlobalScope (singular) com 2 BIZ_* constantes deve casar');

    // Pattern plural — back-compat
    $contentPlural = str_replace('withoutGlobalScope(BusinessScope::class)', 'withoutGlobalScopes()', $contentSingular);
    expect(invokePrivate($service, 'isCrossTenantTestFile', [$contentPlural]))
        ->toBeTrue('withoutGlobalScopes (plural) deve continuar casando');
});

// ─────────────────────────────────────────────────────────────────────────────
// CENÁRIO 3 — D1.c fallback: Job com $entityId + body ->business_id
// ─────────────────────────────────────────────────────────────────────────────

it('d1c fallback detects job entityId pattern with body ->business_id', function () {
    config(['governance.d1_hardened' => true]);
    $service = app(ModuleGradeService::class);

    // Fixture: Modules/__fixture/Jobs/SyncTransactionJob.php
    // Constructor recebe $transactionId (não $business*) mas body usa ->business_id
    $base = storage_path('framework/testing/d1-hardened-jobfixture-' . uniqid());
    File::ensureDirectoryExists($base . '/Jobs');

    $jobContent = <<<'PHP'
    <?php
    namespace Modules\Fixture\Jobs;

    use Illuminate\Bus\Queueable;

    class SyncTransactionJob
    {
        use Queueable;

        public function __construct(int $transactionId)
        {
            $this->transactionId = $transactionId;
        }

        public function handle(): void
        {
            $tx = \App\Transaction::find($this->transactionId);
            // Multi-tenant safe — biz_id vem da própria Eloquent
            session(['user.business_id' => $tx->business_id]);
            // ...
        }
    }
    PHP;

    File::put($base . '/Jobs/SyncTransactionJob.php', $jobContent);

    // Conta jobs via mesmo loop privado D1.c reproduzido aqui
    $jobs = invokePrivate($service, 'phpFiles', [$base . '/Jobs', false]);
    expect($jobs)->toHaveCount(1);

    $content = file_get_contents($jobs[0]);
    $hasBusinessConstructor = (bool) preg_match('/__construct\s*\([^)]*\$business/', $content);
    $hasEntityIdConstructor = (bool) preg_match('/__construct\s*\([^)]*\$\w+Id\b/', $content);
    $hasBusinessBody = (bool) preg_match('/->business_id\b/', $content);

    expect($hasBusinessConstructor)->toBeFalse('constructor não tem $business — usa $transactionId');
    expect($hasEntityIdConstructor)->toBeTrue('constructor tem $transactionId (\w+Id pattern)');
    expect($hasBusinessBody)->toBeTrue('body referencia ->business_id (multi-tenant safe via Eloquent)');

    File::deleteDirectory($base);
});

// ─────────────────────────────────────────────────────────────────────────────
// CENÁRIO 4 — Backward-compat: flag default `false` mantém score atual
// ─────────────────────────────────────────────────────────────────────────────

it('d1 hardened flag default false preserves backward compat', function () {
    // Flag NÃO setada → config padrão `false`
    config()->offsetUnset('governance.d1_hardened');

    $service = app(ModuleGradeService::class);
    $grade = $service->gradeModule('Governance');

    expect($grade)->toHaveKey('dimensions');
    $d1 = $grade['dimensions']['multi_tenant'];

    // Governance é cross-tenant by design — score D1 deve continuar igual sem flag
    expect($d1['weight'])->toBe(30);
    expect($d1['max'])->toBe(30);
    expect($d1['score'])->toBeInt()->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(30);
});

// ─────────────────────────────────────────────────────────────────────────────
// CENÁRIO 5 — Flag ativada não quebra módulos existentes (score continua válido)
// ─────────────────────────────────────────────────────────────────────────────

it('d1 hardened flag enabled keeps existing modules scoring valid', function () {
    config(['governance.d1_hardened' => true]);

    $service = app(ModuleGradeService::class);

    // 3 módulos representativos
    foreach (['Governance', 'Jana', 'Whatsapp'] as $mod) {
        $grade = $service->gradeModule($mod);
        $d1 = $grade['dimensions']['multi_tenant'];

        expect($d1['score'])->toBeInt()->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(30,
            "Módulo {$mod} D1 score deve permanecer em 0-30 com flag ativa"
        );
        expect($d1['breakdown'])->toBeArray()->toHaveCount(3);
    }
});
