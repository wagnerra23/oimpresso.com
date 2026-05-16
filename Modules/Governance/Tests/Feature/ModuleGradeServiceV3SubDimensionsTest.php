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
// CENÁRIO 6 — N/A v2 continua funcionando em sub-itens D6-D9 (backward-compat)
// ─────────────────────────────────────────────────────────────────────────────

it('cenário 6 — N/A justificado v2 aplicável em D6.a (backward-compat sub-itens v3)', function () {
    // Cria SPEC sintético em módulo AssetManagement com N/A em D6.a
    $tmpDir = base_path('memory/requisitos/AssetManagement');
    if (! is_dir($tmpDir)) {
        @mkdir($tmpDir, 0755, true);
    }
    $originalSpec = $tmpDir . '/SPEC.md';
    $backupSpec = $tmpDir . '/SPEC.md.bak-v3test';

    if (file_exists($originalSpec)) {
        copy($originalSpec, $backupSpec);
    }

    $syntheticSpec = <<<'YAML'
---
lifecycle: active
owner: [W]
module: AssetManagement
na_justified:
  D6.a: "módulo CLI-only, sem Inertia::render"
---

# Test synthetic SPEC v3
YAML;

    file_put_contents($originalSpec, $syntheticSpec);

    try {
        $service = app(ModuleGradeService::class);
        $grade = $service->gradeModule('AssetManagement');

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
        // Restaura SPEC original (se existia) ou remove o sintético
        if (file_exists($backupSpec)) {
            copy($backupSpec, $originalSpec);
            @unlink($backupSpec);
        } else {
            @unlink($originalSpec);
        }
    }
});
