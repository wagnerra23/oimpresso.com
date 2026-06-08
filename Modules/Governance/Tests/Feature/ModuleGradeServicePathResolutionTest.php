<?php

declare(strict_types=1);

use Modules\Governance\Services\ModuleGradeService;

uses(Tests\TestCase::class);

/**
 * Regression test pra bug case-sensitivity detectado em prod (Hostinger Linux) 2026-05-15.
 *
 * Wagner reportou D3.c = "0 charters / 0 tsx" + D4.c = "0 tsx / 0 blade" pro módulo
 * Governance mesmo após PR #948 criar Dashboard.tsx + Dashboard.charter.md em
 * resources/js/Pages/governance/ (lowercase).
 *
 * Causa: Service procurava `resources/js/Pages/Governance/` (capitalizado) e Linux
 * é case-sensitive; Windows local não falha porque NTFS é case-insensitive.
 *
 * Fix: helper privado `resolveCaseInsensitivePagesPath()` tenta case exato →
 * lowercase → lcfirst → scandir+strcasecmp antes de retornar null.
 *
 * @see memory/decisions/0153-module-grade-rubrica-v1.md
 */

it('cenário 1 — Pages/governance/ resolve quando module name é Governance', function () {
    $service = app(ModuleGradeService::class);
    $grade = $service->gradeModule('Governance');

    // D3.c — Charter ratio
    $d3 = $grade['dimensions']['documentation']['breakdown'];
    $d3c = collect($d3)->firstWhere('key', 'D3.c');

    expect($d3c)->not->toBeNull();
    expect($d3c['evidence'])->not->toContain('0 tsx');  // não deve reportar 0 tsx
});

it('cenário 2 — Pages/Crm/ resolve quando module name é Crm (case exato preservado)', function () {
    $crmPagesPath = base_path('resources/js/Pages/Crm');
    if (! is_dir($crmPagesPath)) {
        // Crm pode não estar presente no fixture local — pular cenário gracefully
        expect(true)->toBeTrue();
        return;
    }

    $service = app(ModuleGradeService::class);
    $grade = $service->gradeModule('Crm');

    $d4 = $grade['dimensions']['architecture']['breakdown'];
    $d4c = collect($d4)->firstWhere('key', 'D4.c');

    // Crm deve detectar pelo menos 1 tsx se diretório existe com .tsx files
    expect($d4c)->not->toBeNull();
    expect($d4c['evidence'])->toContain('tsx');
});

it('cenário 3 — módulo sem Pages dir retorna 0 sem erro', function () {
    // Procura módulo que NÃO tem resources/js/Pages/<name>/
    $service = app(ModuleGradeService::class);

    // MemCofre tipicamente backend-only — sem Pages dir
    $modulesPath = base_path('Modules');
    $backendOnly = null;
    foreach (scandir($modulesPath) as $mod) {
        if ($mod === '.' || $mod === '..') continue;
        if (! is_dir($modulesPath . DIRECTORY_SEPARATOR . $mod)) continue;
        // Procura módulo sem Pages dir em nenhuma variante case
        $base = base_path('resources/js/Pages');
        $found = false;
        foreach (scandir($base) ?: [] as $entry) {
            if (strcasecmp($entry, $mod) === 0) {
                $found = true;
                break;
            }
        }
        if (! $found) {
            $backendOnly = $mod;
            break;
        }
    }

    if ($backendOnly === null) {
        // Todos módulos têm Pages — cenário não aplicável neste fixture
        expect(true)->toBeTrue();
        return;
    }

    $grade = $service->gradeModule($backendOnly);
    $d3c = collect($grade['dimensions']['documentation']['breakdown'])
        ->firstWhere('key', 'D3.c');

    expect($d3c)->not->toBeNull();
    expect($d3c['score'])->toBeInt();  // não lança exception
});

it('cenário 4 — Governance reporta charters > 0 após fix (D3.c não-zero quando charters existem)', function () {
    // Pré-requisito: Pages/governance/Dashboard.charter.md deve existir
    $charterPath = base_path('resources/js/Pages/governance/Dashboard.charter.md');
    if (! file_exists($charterPath)) {
        // Sem charter no fixture — pular
        expect(true)->toBeTrue();
        return;
    }

    $service = app(ModuleGradeService::class);
    $grade = $service->gradeModule('Governance');

    $d3c = collect($grade['dimensions']['documentation']['breakdown'])
        ->firstWhere('key', 'D3.c');

    // Antes do fix: "0 charters / 0 tsx (0%)" — depois deve refletir presença real
    expect($d3c['evidence'])->not->toStartWith('0 charters');
});

it('cenário 5 — Governance reporta tsx > 0 após fix (D4.c não-zero quando .tsx existem)', function () {
    // Pré-requisito: Pages/governance/Dashboard.tsx deve existir
    $tsxPath = base_path('resources/js/Pages/governance/Dashboard.tsx');
    if (! file_exists($tsxPath)) {
        expect(true)->toBeTrue();
        return;
    }

    $service = app(ModuleGradeService::class);
    $grade = $service->gradeModule('Governance');

    $d4c = collect($grade['dimensions']['architecture']['breakdown'])
        ->firstWhere('key', 'D4.c');

    expect($d4c)->not->toBeNull();
    // Evidence formato: "{$tsxCount} tsx / {$bladeCount} blade"
    expect($d4c['evidence'])->not->toStartWith('0 tsx');
});
