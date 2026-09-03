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

it('cenário 2: os nomes de rota das telas revogadas NÃO existem (Onda 11)', function () {
    // ADR 0367 D1 + PARIDADE §11: 7 das 8 telas de /project-mgmt saíram.
    // Este cenário era o inverso — afirmava que elas existiam. Invertido de
    // propósito: agora ele reprova se alguém as ressuscitar pelo nome.
    $revogadas = [
        'project-mgmt.index',
        'project-mgmt.board.index',
        'project-mgmt.backlog.index',
        'project-mgmt.my-work.index',
        'project-mgmt.triage.index',
        'project-mgmt.inbox.index',
        'project-mgmt.activity.index',
        'project-mgmt.burndown.index',
        'project-mgmt.search',
    ];

    foreach ($revogadas as $nome) {
        expect(\Route::has($nome))->toBeFalse(
            "Rota {$nome} foi revogada na Onda 11 e não deve voltar pelo nome"
        );
    }
});

it('cenário 3: o quarter view sobrevive (ADR 0367 D7)', function () {
    // D7 condiciona a saída a "o Gantt provar que substitui" — e a Onda 6 não
    // rodou. Enquanto isso, esta rota é a única de tela que fica no prefixo.
    expect(\Route::has('project-mgmt.roadmap.index'))->toBeTrue(
        'Rota project-mgmt.roadmap.index deveria existir (US-TR-203 · ADR 0367 D7)'
    );
});

it('cenário 4: a busca global mudou de prefixo, não morreu', function () {
    // Ela nunca foi tela: serve o ⌘K do AppShellV2 (CommandPalette.tsx).
    expect(\Route::has('forja.search'))->toBeTrue(
        'Rota forja.search deveria existir (PMG-002 Cmd+K, movida na Onda 11)'
    );
});

it('cenário 5: os 7 caminhos revogados respondem por redirect, não por 404', function () {
    // Medido: 113 citações de /project-mgmt/* em memory/**. O link velho tem
    // de levar a algum lugar — a rota some, o caminho não.
    foreach (['/project-mgmt/board', '/project-mgmt/backlog', '/project-mgmt/triage'] as $caminho) {
        expect(app('router')->getRoutes()->match(
            \Illuminate\Http\Request::create($caminho, 'GET')
        ))->not->toBeNull("Caminho {$caminho} deveria ter um 301 registrado");
    }
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
