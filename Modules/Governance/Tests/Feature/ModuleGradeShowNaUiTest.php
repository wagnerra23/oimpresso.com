<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

/**
 * Smoke test ADR 0154 v2 — Show.tsx mostra dimensões N/A justificadas.
 *
 * Valida que o componente Inertia React `governance/ModuleGrades/Show`
 * reconhece e renderiza o contrato N/A justificado (`na_justified`,
 * `na_reason`) nos níveis dimensão e sub-item do payload Grade.
 *
 * Por enquanto valida apenas o source .tsx (Pest browser tests cobrirão
 * renderização final). Quando o backend incluir `na_justified` no payload
 * do ModuleGradeService, esses tokens devem ser preservados na UI.
 *
 * @see memory/decisions/0154-na-justificado-rubrica-v2.md
 * @see resources/js/Pages/governance/ModuleGrades/Show.tsx
 */

it('Show.tsx existe', function () {
    $path = base_path('resources/js/Pages/governance/ModuleGrades/Show.tsx');
    expect(file_exists($path))->toBeTrue();
});

it('Show.tsx declara campos na_justified e na_reason na interface BreakdownItem', function () {
    $source = file_get_contents(base_path('resources/js/Pages/governance/ModuleGrades/Show.tsx'));
    expect($source)
        ->toContain('na_justified?: boolean')
        ->toContain('na_reason?: string');
});

it('Show.tsx detecta dimensão N/A via helper isDimensionNaJustified', function () {
    $source = file_get_contents(base_path('resources/js/Pages/governance/ModuleGrades/Show.tsx'));
    expect($source)->toContain('function isDimensionNaJustified');
});

it('Show.tsx renderiza badge verde N/A justificado quando dimensão é N/A', function () {
    $source = file_get_contents(base_path('resources/js/Pages/governance/ModuleGrades/Show.tsx'));
    expect($source)
        ->toContain('N/A justificado')
        ->toContain('bg-emerald-100');
});

it('Show.tsx mostra contador no header quando há dimensões N/A', function () {
    $source = file_get_contents(base_path('resources/js/Pages/governance/ModuleGrades/Show.tsx'));
    expect($source)
        ->toContain('naJustifiedCount')
        ->toContain('dimensões com N/A justificado');
});

it('Show.tsx exibe sub-item N/A com ícone verde ✓ N/A em vez de [score/max]', function () {
    $source = file_get_contents(base_path('resources/js/Pages/governance/ModuleGrades/Show.tsx'));
    // O ícone "✓ N/A" substitui o badge [score/max] padrão quando item.na_justified
    expect($source)
        ->toContain('✓ N/A')
        ->toContain('item.na_justified');
});

it('Show.tsx exibe razão N/A em italic verde (transparência ADR 0154 v2)', function () {
    $source = file_get_contents(base_path('resources/js/Pages/governance/ModuleGrades/Show.tsx'));
    // Razão verde-esmeralda em italic — anti-hook "verde silencioso"
    expect($source)
        ->toContain('text-emerald-700 italic');
});

it('Show.tsx mostra score N/A em vez de score/max quando dimensão é N/A', function () {
    $source = file_get_contents(base_path('resources/js/Pages/governance/ModuleGrades/Show.tsx'));
    // Cláusula ternária: dimNa ? 'N/A' : `${dim.score}/${dim.max}`
    expect($source)->toContain("dimNa ? 'N/A'");
});

it('Show.charter.md aceita ADR 0154 como referência', function () {
    $charterPath = base_path('resources/js/Pages/governance/ModuleGrades/Show.charter.md');
    expect(file_exists($charterPath))->toBeTrue();
    $charter = file_get_contents($charterPath);
    expect($charter)->toContain('0154');
});
