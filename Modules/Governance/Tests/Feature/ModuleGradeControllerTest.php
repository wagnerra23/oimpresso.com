<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

/**
 * Tests pra ModuleGradeController (rotas Inertia /governance/module-grades).
 *
 * Smoke test — apenas valida que rotas existem, middleware auth aplicado,
 * status <500. NÃO testa renderização Inertia completa (Pest browser tests
 * cobrem isso em outra suite).
 *
 * @see memory/decisions/0153-module-grade-rubrica-v1.md
 * @see Modules/Governance/Http/Controllers/ModuleGradeController.php
 */

it('rota nomeada governance.module-grades.index existe', function () {
    expect(\Route::has('governance.module-grades.index'))->toBeTrue();
});

it('rota nomeada governance.module-grades.show existe', function () {
    expect(\Route::has('governance.module-grades.show'))->toBeTrue();
});

it('GET /governance/module-grades sem auth redireciona ou bloqueia', function () {
    $response = $this->get('/governance/module-grades');
    expect($response->status())->toBeIn([302, 401, 403])
        ->and($response->status())->not->toBe(200);
});

it('GET /governance/module-grades/{name} sem auth redireciona ou bloqueia', function () {
    $response = $this->get('/governance/module-grades/Governance');
    expect($response->status())->toBeIn([302, 401, 403])
        ->and($response->status())->not->toBe(200);
});

it('Rota show param name aceita apenas A-Za-z0-9_-', function () {
    $route = \Route::getRoutes()->getByName('governance.module-grades.show');
    expect($route)->not->toBeNull();

    // Constraint declarado via ->where('name', '[A-Za-z0-9_-]+')
    $wheres = $route->wheres ?? [];
    expect($wheres['name'] ?? null)->toBe('[A-Za-z0-9_-]+');
});

it('Controller usa Inertia::defer em props caras (D-14 lição)', function () {
    $controllerPath = base_path('Modules/Governance/Http/Controllers/ModuleGradeController.php');
    expect(file_exists($controllerPath))->toBeTrue();

    $source = file_get_contents($controllerPath);
    // Pest toContain aceita apenas string OR string... (variadic) — todos têm que aparecer
    expect($source)->toContain('Inertia::defer');
});
