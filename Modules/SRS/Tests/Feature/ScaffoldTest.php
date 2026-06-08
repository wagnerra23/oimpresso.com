<?php

declare(strict_types=1);

use Nwidart\Modules\Facades\Module;

uses(Tests\TestCase::class);

/**
 * Smoke test do scaffold Modules/SRS (rename de MemCofre — PR #97 Fase 3.7).
 *
 * Garante que:
 *   1. Módulo aparece registrado em nWidart sob o nome canônico "SRS"
 *   2. ServiceProvider carrega sem erro
 *   3. Rotas web nomeadas estão registradas (memcofre.* legacy + srs.install.*)
 *
 * Refs: ADR 0011 padrão Jana/Repair/Project, skill criar-modulo,
 *       memory/proibicoes.md §"Não criar Modules/X/Tests/ sem registrar em phpunit.xml"
 */

it('cenário 1: módulo SRS aparece registrado em nWidart', function () {
    $module = Module::find('SRS');
    expect($module)->not->toBeNull('Modules/SRS deveria estar registrado em nWidart');
    expect($module->getName())->toBe('SRS');
});

it('cenário 2: rota nomeada memcofre.dashboard existe (legacy prefix preservado)', function () {
    expect(\Route::has('memcofre.dashboard'))->toBeTrue('Rota memcofre.dashboard deveria existir per Http/routes.php');
});

it('cenário 3: rota nomeada memcofre.ingest existe', function () {
    expect(\Route::has('memcofre.ingest'))->toBeTrue('Rota memcofre.ingest deveria existir');
});

it('cenário 4: rota nomeada memcofre.inbox existe', function () {
    expect(\Route::has('memcofre.inbox'))->toBeTrue('Rota memcofre.inbox deveria existir');
});

it('cenário 5: rota nomeada srs.install.index existe (3 rotas Install canônicas)', function () {
    expect(\Route::has('srs.install.index'))->toBeTrue('Rota srs.install.index deveria existir');
});

it('cenário 6: rota nomeada srs.install.run existe (POST install)', function () {
    expect(\Route::has('srs.install.run'))->toBeTrue('Rota srs.install.run deveria existir');
});

it('cenário 7: rota nomeada srs.install.uninstall existe', function () {
    expect(\Route::has('srs.install.uninstall'))->toBeTrue('Rota srs.install.uninstall deveria existir');
});

it('cenário 8: rota nomeada memcofre.chat existe (assistente IA)', function () {
    expect(\Route::has('memcofre.chat'))->toBeTrue('Rota memcofre.chat deveria existir');
});
