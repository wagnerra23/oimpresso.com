<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

/**
 * Tests pro `ScopedScorecardEvaluator` Service (Wave 21 — bucket-based scorecards v4).
 *
 * Estrutura esperada do Service (criado por Wave 21 Agent A):
 *   - loadScorecardForModule(string $module): array  — carrega YAML do bucket via _INDEX.md
 *   - evaluateScorecard(string $module, array $scorecard): array
 *       => ['module','bucket','core','bucket_dimensions','paired_violations','score_total','meta_bucket']
 *
 * Buckets canônicos esperados (memory/governance/buckets/_INDEX.md):
 *   - vertical_client_facing   → Vestuario, ComunicacaoVisual, OficinaAuto, Repair...
 *   - cross_cutting_infra      → Governance, Auditoria, TeamMcp, Superadmin...
 *   - ai_central               → Jana
 *   - functional_horizontal    → Crm, Financeiro, NfeBrasil, RecurringBilling, Whatsapp...
 *
 * Pre-conditions:
 *   - Service `Modules\Governance\Services\ScopedScorecardEvaluator` existe (Wave 21 Agent A)
 *   - YAMLs em `memory/scorecards/<bucket>.yaml`
 *   - Index em `memory/governance/buckets/_INDEX.md` ou similar
 *
 * Se faltarem pre-conditions, tests pulam graciosamente (markTestSkipped).
 *
 * @see memory/decisions/ scoped scorecards v4 (Wave 21)
 * @see Modules/Governance/Services/ScopedScorecardEvaluator.php
 */
beforeEach(function () {
    $evaluatorClass = 'Modules\\Governance\\Services\\ScopedScorecardEvaluator';
    if (! class_exists($evaluatorClass)) {
        $this->markTestSkipped('ScopedScorecardEvaluator ainda não criado (Wave 21 Agent A em andamento)');
    }

    $scorecardsDir = base_path('memory/scorecards');
    if (! is_dir($scorecardsDir)) {
        $this->markTestSkipped('memory/scorecards/ ainda não populado (Wave 21 dependency)');
    }

    $this->evaluator = app($evaluatorClass);
});

it('loadScorecardForModule(Vestuario) retorna bucket vertical_client_facing', function () {
    $scorecard = $this->evaluator->loadScorecardForModule('Vestuario');

    expect($scorecard)->toBeArray()->not->toBeEmpty();
    expect($scorecard)->toHaveKey('metadata');
    expect($scorecard['metadata'])->toHaveKey('bucket');
    expect($scorecard['metadata']['bucket'])->toBe('vertical_client_facing');
});

it('loadScorecardForModule(Governance) retorna bucket cross_cutting_infra', function () {
    $scorecard = $this->evaluator->loadScorecardForModule('Governance');

    expect($scorecard)->toBeArray()->not->toBeEmpty();
    expect($scorecard['metadata']['bucket'] ?? null)->toBe('cross_cutting_infra');
});

it('loadScorecardForModule(Jana) retorna bucket ai_central', function () {
    $scorecard = $this->evaluator->loadScorecardForModule('Jana');

    expect($scorecard)->toBeArray()->not->toBeEmpty();
    expect($scorecard['metadata']['bucket'] ?? null)->toBe('ai_central');
});

it('loadScorecardForModule(Crm) retorna bucket functional_horizontal', function () {
    $scorecard = $this->evaluator->loadScorecardForModule('Crm');

    expect($scorecard)->toBeArray()->not->toBeEmpty();
    expect($scorecard['metadata']['bucket'] ?? null)->toBe('functional_horizontal');
});

it('loadScorecardForModule(NaoExiste) retorna array vazio (gracefully)', function () {
    $scorecard = $this->evaluator->loadScorecardForModule('NaoExisteXyz123');

    expect($scorecard)->toBeArray()->toBeEmpty();
});

it('evaluateScorecard(Vestuario) retorna estrutura canônica completa', function () {
    $scorecard = $this->evaluator->loadScorecardForModule('Vestuario');

    if (empty($scorecard)) {
        $this->markTestSkipped('Scorecard vestuario YAML ainda não populado');
    }

    $result = $this->evaluator->evaluateScorecard('Vestuario', $scorecard);

    expect($result)->toBeArray();
    expect($result)->toHaveKeys([
        'module',
        'bucket',
        'core',
        'bucket_dimensions',
        'paired_violations',
        'score_total',
        'meta_bucket',
    ]);
    expect($result['module'])->toBe('Vestuario');
});

it('score_total respeita range 0-100 (nunca negativo, nunca >100)', function () {
    foreach (['Vestuario', 'Governance', 'Jana', 'Crm'] as $module) {
        $scorecard = $this->evaluator->loadScorecardForModule($module);
        if (empty($scorecard)) {
            continue;
        }

        $result = $this->evaluator->evaluateScorecard($module, $scorecard);

        expect($result['score_total'])
            ->toBeNumeric()
            ->toBeGreaterThanOrEqual(0)
            ->toBeLessThanOrEqual(100);
    }
});

it('detection file_exists funciona pra arquivo existente (Modules/Vestuario/module.json)', function () {
    if (! method_exists($this->evaluator, 'detectFileExists')) {
        $this->markTestSkipped('Service não expõe detectFileExists (pode ser private — OK)');
    }

    expect($this->evaluator->detectFileExists('Modules/Vestuario/module.json'))->toBeTrue();
    expect($this->evaluator->detectFileExists('Modules/Vestuario/nao_existe.json'))->toBeFalse();
});

it('detection grep funciona pra string presente em código', function () {
    if (! method_exists($this->evaluator, 'detectGrep')) {
        $this->markTestSkipped('Service não expõe detectGrep (pode ser private — OK)');
    }

    // HasBusinessScope (ou similar) DEVE existir em algum Entity do Vestuario
    $found = $this->evaluator->detectGrep(
        'Modules/Vestuario/**/*.php',
        'business_id'
    );
    expect($found)->toBeTrue();
});

it('detection ratio calcula numerator/denominator vs coverage_target', function () {
    if (! method_exists($this->evaluator, 'detectRatio')) {
        $this->markTestSkipped('Service não expõe detectRatio (pode ser private — OK)');
    }

    // ratio 0.6 vs target 0.5 → pass
    expect($this->evaluator->detectRatio(6, 10, 0.5))->toBeTrue();
    // ratio 0.3 vs target 0.5 → fail
    expect($this->evaluator->detectRatio(3, 10, 0.5))->toBeFalse();
    // divisão por zero → false (não crash)
    expect($this->evaluator->detectRatio(0, 0, 0.5))->toBeFalse();
});

it('detection file_age retorna true se arquivo modificado ≤ N dias', function () {
    if (! method_exists($this->evaluator, 'detectFileAge')) {
        $this->markTestSkipped('Service não expõe detectFileAge (pode ser private — OK)');
    }

    // composer.json certamente existe e foi modificado em algum momento
    expect($this->evaluator->detectFileAge('composer.json', 36500))->toBeTrue();
    // arquivo inexistente → false
    expect($this->evaluator->detectFileAge('nao_existe_xyz.json', 30))->toBeFalse();
});

it('pass-through pra tipos detection não-implementados (placeholder Wave 23)', function () {
    // Wave 23 vai implementar mais tipos (ex: 'sql_query', 'mcp_tool_call', 'cron_running').
    // Service v1 (Wave 21) deve aceitar sem crash — score 0 ou pass-through neutro.
    $scorecard = $this->evaluator->loadScorecardForModule('Vestuario');

    if (empty($scorecard)) {
        $this->markTestSkipped('Scorecard vestuario YAML ainda não populado');
    }

    // Não deve throw exception mesmo se YAML mencionar detection desconhecida
    $result = $this->evaluator->evaluateScorecard('Vestuario', $scorecard);

    expect($result)->toBeArray();
    expect($result['score_total'])->toBeNumeric();
});
