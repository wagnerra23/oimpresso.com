<?php

declare(strict_types=1);

use Modules\ComunicacaoVisual\Http\Controllers\DataController;

uses(Tests\TestCase::class);

/**
 * Smoke tests dos 3 hooks UltimatePOS do DataController — ComunicacaoVisual.
 *
 * Tests biz=1 (Wagner WR2) conforme ADR 0101 — nunca biz=4 (cliente ROTA LIVRE).
 *
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 * @see memory/requisitos/Infra/RUNBOOK-criar-modulo.md §4
 */

it('DataController::superadmin_package() retorna array com nome do pacote', function () {
    $controller = new DataController();
    $pacotes    = $controller->superadmin_package();

    expect($pacotes)->toBeArray();
    expect($pacotes)->not->toBeEmpty();
    expect($pacotes[0])->toHaveKey('name');
    expect($pacotes[0]['name'])->toBe('comunicacao_visual_module');
    expect($pacotes[0])->toHaveKey('label');
    expect($pacotes[0])->toHaveKey('default');
});

it('DataController::user_permissions() retorna pelo menos 6 permissões CV', function () {
    $controller  = new DataController();
    $permissions = $controller->user_permissions();

    expect($permissions)->toBeArray();
    expect(count($permissions))->toBeGreaterThanOrEqual(6);

    // Valida que as 6 permissões Sprint 1 estão presentes
    $valores = array_column($permissions, 'value');
    expect($valores)->toContain('comvis.orcamento.view');
    expect($valores)->toContain('comvis.orcamento.create');
    expect($valores)->toContain('comvis.material.manage');
    expect($valores)->toContain('comvis.os.view');
    expect($valores)->toContain('comvis.os.update_status');
    expect($valores)->toContain('comvis.apontamento.create');
});

it('DataController::user_permissions() cada item tem value, label e default', function () {
    $controller  = new DataController();
    $permissions = $controller->user_permissions();

    foreach ($permissions as $perm) {
        expect($perm)->toHaveKey('value');
        expect($perm)->toHaveKey('label');
        expect($perm)->toHaveKey('default');
    }
});
