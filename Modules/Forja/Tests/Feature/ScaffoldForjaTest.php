<?php

declare(strict_types=1);

use Nwidart\Modules\Facades\Module;

uses(Tests\TestCase::class);

/**
 * Smoke test do scaffold Modules/Forja (US-PROJ-001).
 *
 * Renomeado ProjectMgmt→Forja em 2026-07-30 (PHP-only, padrão ADR 0088): o nome
 * nWidart passa a ser `Forja`, enquanto as rotas `project-mgmt.*` seguem legacy
 * por compat — é exatamente essa assimetria que os cenários abaixo travam.
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

it('cenário 1: módulo Forja aparece registrado em nWidart', function () {
    $module = Module::find('Forja');
    expect($module)->not->toBeNull('Modules/Forja deveria estar registrado');
    expect($module->getName())->toBe('Forja');
});

it('cenário 1b: nome antigo ProjectMgmt não resolve mais em nWidart', function () {
    // Controle-negativo do rename: se este falhar, o git mv foi desfeito ou
    // sobrou um module.json duplicado com o nome antigo.
    expect(Module::find('ProjectMgmt'))->toBeNull(
        'Modules/ProjectMgmt foi renomeado pra Forja em 2026-07-30 (ADR 0088)'
    );
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

it('cenário 9: ServiceProvider ForjaServiceProvider está carregado', function () {
    $providers = array_keys(app()->getLoadedProviders());
    $hasProvider = in_array(
        \Modules\Forja\Providers\ForjaServiceProvider::class,
        $providers,
        true
    );

    expect($hasProvider)->toBeTrue(
        'ForjaServiceProvider deveria estar registrado em providers carregados'
    );
});
