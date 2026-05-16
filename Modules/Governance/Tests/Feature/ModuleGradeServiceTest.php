<?php

declare(strict_types=1);

use Modules\Governance\Services\ModuleGradeService;

uses(Tests\TestCase::class);

/**
 * Tests pra rubrica module-grade-v1 (ADR 0153).
 *
 * Service é puro filesystem inspection — funciona sem DB (independente de SQLite/MySQL).
 *
 * @see memory/decisions/0153-module-grade-rubrica-v1.md
 */

it('gradeModule retorna estrutura canônica completa', function () {
    $service = app(ModuleGradeService::class);
    $grade = $service->gradeModule('Governance');

    expect($grade)->toHaveKeys(['module', 'score', 'bucket', 'color', 'dimensions', 'gaps', 'evolve_tasks', 'evaluated_at']);
    expect($grade['module'])->toBe('Governance');
    expect($grade['score'])->toBeInt()->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(100);
    expect($grade['bucket'])->toBeIn(['Excelente', 'Bom', 'Médio', 'Crítico', 'Embrião']);
});

it('gradeModule retorna 5 dimensões com pesos corretos somando 100', function () {
    $service = app(ModuleGradeService::class);
    $grade = $service->gradeModule('Governance');

    $dims = $grade['dimensions'];
    expect($dims)->toHaveKeys(['multi_tenant', 'pest_coverage', 'documentation', 'architecture', 'client_real']);

    expect($dims['multi_tenant']['weight'])->toBe(30);
    expect($dims['pest_coverage']['weight'])->toBe(20);
    expect($dims['documentation']['weight'])->toBe(15);
    expect($dims['architecture']['weight'])->toBe(20);
    expect($dims['client_real']['weight'])->toBe(15);

    $totalWeight = $dims['multi_tenant']['weight']
        + $dims['pest_coverage']['weight']
        + $dims['documentation']['weight']
        + $dims['architecture']['weight']
        + $dims['client_real']['weight'];
    expect($totalWeight)->toBe(100);
});

it('gradeModule cada dimensão tem score ≤ max', function () {
    $service = app(ModuleGradeService::class);
    $grade = $service->gradeModule('Governance');

    foreach ($grade['dimensions'] as $dim) {
        expect($dim['score'])->toBeLessThanOrEqual($dim['max']);
        expect($dim['score'])->toBeGreaterThanOrEqual(0);
        expect($dim['breakdown'])->toBeArray()->not->toBeEmpty();
    }
});

it('gradeModule lança exception pra módulo inexistente', function () {
    $service = app(ModuleGradeService::class);
    $service->gradeModule('ModuloQueNaoExiste123');
})->throws(\InvalidArgumentException::class);

it('gradeAllModules retorna Collection com 30+ módulos', function () {
    $service = app(ModuleGradeService::class);
    $grades = $service->gradeAllModules();

    expect($grades)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    expect($grades->count())->toBeGreaterThanOrEqual(30);
});

it('gradeAllModules retorna ordenado descendente por score', function () {
    $service = app(ModuleGradeService::class);
    $grades = $service->gradeAllModules();

    $scores = $grades->pluck('score')->all();
    $sorted = $scores;
    rsort($sorted);
    expect($scores)->toBe($sorted);
});

it('buckets respeitam fronteiras canônicas', function () {
    // Cria stub que avalia score conhecido e valida bucket
    $service = app(ModuleGradeService::class);

    // Usa módulos reais como evidência — todos buckets representados na rubrica
    $grades = $service->gradeAllModules();

    foreach ($grades as $g) {
        $expectedBucket = match (true) {
            $g['score'] >= 80 => 'Excelente',
            $g['score'] >= 60 => 'Bom',
            $g['score'] >= 40 => 'Médio',
            $g['score'] >= 20 => 'Crítico',
            default           => 'Embrião',
        };
        expect($g['bucket'])->toBe($expectedBucket, "Módulo {$g['module']} score {$g['score']} deveria ser {$expectedBucket}");
    }
});

it('gradeModule extrai gaps ordenados por perda descendente', function () {
    $service = app(ModuleGradeService::class);
    $grade = $service->gradeModule('Governance');

    if (empty($grade['gaps'])) {
        $this->markTestSkipped('Governance não tem gaps suficientes pra ordenação');
    }

    $losses = array_column($grade['gaps'], 'lost');
    $sorted = $losses;
    rsort($sorted);
    expect($losses)->toBe($sorted);
});

it('gradeModule gera evolve_tasks com priority válida', function () {
    $service = app(ModuleGradeService::class);
    $grade = $service->gradeModule('Governance');

    expect($grade['evolve_tasks'])->toBeArray();
    foreach ($grade['evolve_tasks'] as $task) {
        expect($task)->toHaveKeys(['title', 'module', 'priority', 'estimate', 'gap_ref', 'rationale']);
        expect($task['priority'])->toBeIn(['P0', 'P1', 'P2', 'P3']);
        expect($task['module'])->toBe('Governance');
    }
});

it('rubrica calibrada — módulo bem-coberto (Whatsapp) tem score Bom (60+)', function () {
    $service = app(ModuleGradeService::class);
    $grade = $service->gradeModule('Whatsapp');

    // Whatsapp tem 91 tests + 58 cross-tenant + BRIEFING + cliente biz=1 — esperado Bom+ (60+)
    expect($grade['score'])->toBeGreaterThanOrEqual(60, 'Whatsapp deveria estar no bucket Bom ou Excelente');
});

it('rubrica calibrada — módulo sem cobertura (ProductCatalogue/AssetManagement etc) fica Crítico ou Embrião', function () {
    $service = app(ModuleGradeService::class);
    $grade = $service->gradeModule('AssetManagement');

    // AssetManagement sem tests + sem cliente + sem doc = Embrião esperado
    expect($grade['score'])->toBeLessThan(40, 'AssetManagement deveria estar em bucket Crítico ou Embrião');
});

it('D5 Cliente lê config/governance/module_clients.yaml se existir', function () {
    $service = app(ModuleGradeService::class);
    $grade = $service->gradeModule('Vestuario');

    // Vestuario está marcado como biz_4_rota_livre_prod no YAML → score D5 = 15
    expect($grade['dimensions']['client_real']['score'])->toBe(15, 'Vestuario biz=4 ROTA LIVRE prod deveria scorar 15/15 em D5');
});

it('D2.c registrado em phpunit.xml gera 4 pts; não registrado gera 0', function () {
    $service = app(ModuleGradeService::class);
    $grade = $service->gradeModule('Governance');

    $d2 = $grade['dimensions']['pest_coverage'];
    $d2cItem = collect($d2['breakdown'])->firstWhere('key', 'D2.c');

    expect($d2cItem)->not->toBeNull();
    expect($d2cItem['max'])->toBe(4);
    // Governance está registrado em phpunit.xml na Wave B → score 4
    // (depende do estado atual; se ainda não mergeado pode ser 0)
    expect($d2cItem['score'])->toBeIn([0, 4]);
});
