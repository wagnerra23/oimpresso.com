<?php

declare(strict_types=1);

use Nwidart\Modules\Facades\Module;

uses(Tests\TestCase::class);

/**
 * Smoke test do scaffold Modules/ProjectMgmt (US-PROJ-001).
 *
 * Garante que:
 *   1. Módulo aparece registrado em nWidart
 *   2. ServiceProvider carrega sem erro
 *   3. Rotas web /project-mgmt/* foram registradas
 *   4. Rotas de instalação 1-clique existem (ADR 0024 — sem elas o
 *      botão Install em /manage-modules fica sem ação)
 *
 * Refs: ADR 0070 (Jira-style tasks), ADR 0100 (UI redesign), ADR 0011
 * (padrão Jana/Repair/Project), skill criar-modulo.
 */

it('cenário 1: módulo ProjectMgmt aparece registrado em nWidart', function () {
    $module = Module::find('ProjectMgmt');
    expect($module)->not->toBeNull('Modules/ProjectMgmt deveria estar registrado');
    expect($module->getName())->toBe('ProjectMgmt');
});

it('cenário 2: rota nomeada project-mgmt.index existe', function () {
    expect(\Route::has('project-mgmt.index'))->toBeTrue(
        'Rota project-mgmt.index deveria existir per Http/routes.php'
    );
});

it('cenário 3: rota nomeada project-mgmt.board.index existe', function () {
    expect(\Route::has('project-mgmt.board.index'))->toBeTrue(
        'Rota project-mgmt.board.index deveria existir (Kanban — US-TR-201)'
    );
});

it('cenário 4: rota nomeada project-mgmt.backlog.index existe', function () {
    expect(\Route::has('project-mgmt.backlog.index'))->toBeTrue(
        'Rota project-mgmt.backlog.index deveria existir (US-TR-202)'
    );
});

it('cenário 5: rota nomeada project-mgmt.roadmap.index existe', function () {
    expect(\Route::has('project-mgmt.roadmap.index'))->toBeTrue(
        'Rota project-mgmt.roadmap.index deveria existir (US-TR-203)'
    );
});

it('cenário 6: rota nomeada project-mgmt.my-work.index existe', function () {
    expect(\Route::has('project-mgmt.my-work.index'))->toBeTrue(
        'Rota project-mgmt.my-work.index deveria existir (US-TR-204)'
    );
});

it('cenário 7: rota nomeada project-mgmt.search existe', function () {
    expect(\Route::has('project-mgmt.search'))->toBeTrue(
        'Rota project-mgmt.search deveria existir (PMG-002 Cmd+K)'
    );
});

it('cenário 8: rotas de instalação 1-clique existem (ADR 0024)', function () {
    expect(\Route::has('project-mgmt.install.index'))->toBeTrue('install.index missing');
    expect(\Route::has('project-mgmt.install.run'))->toBeTrue('install.run missing');
    expect(\Route::has('project-mgmt.install.uninstall'))->toBeTrue('install.uninstall missing');
});

it('cenário 9: ServiceProvider ProjectMgmtServiceProvider está carregado', function () {
    $providers = array_keys(app()->getLoadedProviders());
    $hasProvider = in_array(
        \Modules\ProjectMgmt\Providers\ProjectMgmtServiceProvider::class,
        $providers,
        true
    );

    expect($hasProvider)->toBeTrue(
        'ProjectMgmtServiceProvider deveria estar registrado em providers carregados'
    );
});
