<?php

declare(strict_types=1);

use Modules\Governance\Services\ModuleGradeService;

uses(Tests\TestCase::class);

/**
 * Tests v2 da rubrica module-grade — suporte "N/A justificado" (ADR 0154 proposto).
 *
 * Service é puro filesystem inspection. Estes tests usam:
 *  - Governance (já tem `na_justified:` declarado no SPEC.md frontmatter — 3 entries)
 *  - AssetManagement (módulo sem `na_justified` — valida backward-compat v1)
 *  - SPEC sintético criado/removido em tearDown pra testar limit 3 (cenário 3)
 *
 * @see memory/decisions/0153-module-grade-rubrica-v1.md
 * @see memory/decisions/0154-na-justificado-rubrica-v2.md (proposto)
 */

beforeEach(function () {
    $this->tempSpecPath = base_path('memory/requisitos/Governance/SPEC.test-tmp.md');
});

afterEach(function () {
    if (isset($this->tempSpecPath) && file_exists($this->tempSpecPath)) {
        @unlink($this->tempSpecPath);
    }
});

// ─────────────────────────────────────────────────────────────────────────────
// CENÁRIO 1 — backward-compat: módulo SEM na_justified retorna scores v1
// ─────────────────────────────────────────────────────────────────────────────

it('cenário 1 — módulo sem na_justified declarado retorna scores v1 (backward-compat)', function () {
    $service = app(ModuleGradeService::class);
    $grade = $service->gradeModule('AssetManagement');

    // Estrutura canônica preservada
    expect($grade)->toHaveKeys(['module', 'score', 'bucket', 'color', 'dimensions', 'gaps', 'evolve_tasks', 'total_na_justified', 'evaluated_at']);
    expect($grade['total_na_justified'])->toBe(0, 'AssetManagement sem na_justified → total=0');

    // Cada dimensão sem na_justified aplicado
    foreach ($grade['dimensions'] as $dim) {
        expect($dim['na_justified'] ?? [])->toBeEmpty('Dimensão sem na_justified → array vazio');
    }

    // Score continua subindo conforme rubrica original (não inflou artificialmente)
    expect($grade['score'])->toBeInt()->toBeLessThan(80, 'AssetManagement não tem cobertura → score <80');
});

// ─────────────────────────────────────────────────────────────────────────────
// CENÁRIO 2 — Governance com 3 N/A declarados retorna score Excelente 80+
// ─────────────────────────────────────────────────────────────────────────────

it('cenário 2 — Governance com 3 N/A declarados sobe pro bucket Bom+ com score boostado', function () {
    $service = app(ModuleGradeService::class);
    $grade = $service->gradeModule('Governance');

    // SPEC.md Governance declara 3 entries: D1.a, D4.b, D5 (full dim)
    expect($grade['total_na_justified'])->toBeGreaterThanOrEqual(3, 'Governance declara 3 N/A no SPEC.md');

    // D1.a deve estar marcado N/A com max=10
    $d1aBreakdown = collect($grade['dimensions']['multi_tenant']['breakdown'])->firstWhere('key', 'D1.a');
    expect($d1aBreakdown)->not->toBeNull();
    expect($d1aBreakdown['score'])->toBe(10, 'D1.a N/A → score = max (10)');
    expect($d1aBreakdown['na_justified'] ?? false)->toBeTrue();

    // D4.b N/A
    $d4bBreakdown = collect($grade['dimensions']['architecture']['breakdown'])->firstWhere('key', 'D4.b');
    expect($d4bBreakdown['score'])->toBe(5, 'D4.b N/A → score = max (5)');
    expect($d4bBreakdown['na_justified'] ?? false)->toBeTrue();

    // D5 dimensão inteira N/A → score=max (15)
    expect($grade['dimensions']['client_real']['score'])->toBe(15, 'D5 dimensão inteira N/A → 15/15');

    // Score sobe significativamente — Governance + 3 N/A justificados deve bater bucket Bom+ (60+).
    // Ideal: Excelente (80+), mas evita fragilidade caso cobertura code drift.
    expect($grade['score'])->toBeGreaterThanOrEqual(60, 'Governance + 3 N/A → bucket Bom ou superior');
    expect($grade['bucket'])->toBeIn(['Bom', 'Excelente']);
});

// ─────────────────────────────────────────────────────────────────────────────
// CENÁRIO 3 — módulo declara 5 N/A → Service ignora excedentes (limit 3)
// ─────────────────────────────────────────────────────────────────────────────

it('cenário 3 — módulo com 5 N/A declarados → Service aplica somente 3 (anti-gaming)', function () {
    // Cria SPEC sintético com 5 N/A em diretório temp de módulo real (AssetManagement)
    $tmpDir = base_path('memory/requisitos/AssetManagement');
    $originalSpec = $tmpDir . '/SPEC.md';
    $backupSpec = $tmpDir . '/SPEC.md.bak-v2test';

    // Backup do SPEC existente (se houver)
    if (file_exists($originalSpec)) {
        copy($originalSpec, $backupSpec);
    }

    $syntheticSpec = <<<'YAML'
---
lifecycle: active
owner: [W]
module: AssetManagement
na_justified:
  D1.a: "razão 1"
  D1.b: "razão 2"
  D1.c: "razão 3"
  D2.a: "razão 4 — deve ser ignorada"
  D2.b: "razão 5 — deve ser ignorada"
---

# Test synthetic SPEC
YAML;

    file_put_contents($originalSpec, $syntheticSpec);

    try {
        $service = app(ModuleGradeService::class);
        $grade = $service->gradeModule('AssetManagement');

        // Limite 3 enforced — total NÃO pode passar de 3
        expect($grade['total_na_justified'])->toBeLessThanOrEqual(3, 'Service ignora excedentes além de 3');

        // Primeiras 3 (D1.a, D1.b, D1.c) DEVEM estar aplicadas
        $d1a = collect($grade['dimensions']['multi_tenant']['breakdown'])->firstWhere('key', 'D1.a');
        expect($d1a['na_justified'] ?? false)->toBeTrue('D1.a primeira entry → aplicada');

        // D2.a e D2.b NÃO devem estar marcadas N/A
        $d2a = collect($grade['dimensions']['pest_coverage']['breakdown'])->firstWhere('key', 'D2.a');
        expect($d2a['na_justified'] ?? false)->toBeFalse('D2.a excedente → NÃO aplicada');
    } finally {
        // Restaura SPEC original
        if (file_exists($backupSpec)) {
            copy($backupSpec, $originalSpec);
            @unlink($backupSpec);
        } else {
            @unlink($originalSpec);
        }
    }
});

// ─────────────────────────────────────────────────────────────────────────────
// CENÁRIO 4 — evidence mostra "N/A justificado: {razão}"
// ─────────────────────────────────────────────────────────────────────────────

it('cenário 4 — evidence em sub-itens N/A mostra "N/A justificado: {razão}"', function () {
    $service = app(ModuleGradeService::class);
    $grade = $service->gradeModule('Governance');

    $d4b = collect($grade['dimensions']['architecture']['breakdown'])->firstWhere('key', 'D4.b');
    expect($d4b['evidence'])->toStartWith('N/A justificado:');
    expect($d4b['evidence'])->toContain('state machine', 'Evidence inclui razão do SPEC.md');

    $d1a = collect($grade['dimensions']['multi_tenant']['breakdown'])->firstWhere('key', 'D1.a');
    expect($d1a['evidence'])->toStartWith('N/A justificado:');
    expect($d1a['evidence'])->toContain('cross-tenant', 'Evidence D1.a contém razão BusinessScope cross-tenant');
});

// ─────────────────────────────────────────────────────────────────────────────
// CENÁRIO 5 — SPEC sem frontmatter na_justified continua funcionando v1
// ─────────────────────────────────────────────────────────────────────────────

it('cenário 5 — SPEC.md sem frontmatter na_justified continua funcionando v1', function () {
    $service = app(ModuleGradeService::class);

    // Vestuario tem SPEC.md sem na_justified declarado
    $grade = $service->gradeModule('Vestuario');

    // total_na_justified=0 (chave existe, valor zero) → backward-compat v1
    expect($grade)->toHaveKey('total_na_justified');
    expect($grade['total_na_justified'])->toBe(0);

    // Nenhuma dimensão tem na_justified aplicado
    foreach ($grade['dimensions'] as $dimKey => $dim) {
        expect($dim['na_justified'] ?? [])->toBeEmpty("Dimensão {$dimKey} sem na_justified → array vazio");
    }

    // Score continua subindo conforme rubrica v1 (sub-itens com scores reais, NÃO max forçado)
    foreach ($grade['dimensions'] as $dim) {
        foreach ($dim['breakdown'] as $sub) {
            expect($sub['na_justified'] ?? false)->toBeFalse('Sub-item v1 sem flag na_justified');
        }
    }
});
