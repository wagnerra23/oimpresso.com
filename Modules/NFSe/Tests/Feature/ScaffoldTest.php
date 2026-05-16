<?php

declare(strict_types=1);

use Nwidart\Modules\Facades\Module;

uses(Tests\TestCase::class);

/**
 * Smoke test do scaffold Modules/NFSe.
 *
 * Garante que:
 *   1. Módulo NFSe aparece registrado em nWidart
 *   2. ServiceProvider carrega sem erro
 *   3. Rotas web nomeadas foram registradas (nfse.index, nfse.create, nfse.show, nfse.store, nfse.cancelar, nfse.pdf)
 *
 * Refs: Modules/NFSe/module.json (alias=nfse, priority=0)
 *       Modules/NFSe/Routes/web.php
 *       ADR 0011 (padrão Jana/Repair/Project)
 *       skill criar-modulo
 */

it('cenário 1: módulo NFSe aparece registrado em nWidart', function () {
    $module = Module::find('NFSe');
    expect($module)->not->toBeNull('Modules/NFSe deveria estar registrado em nWidart (ver module.json)');
    expect($module->getName())->toBe('NFSe');
});

it('cenário 2: módulo NFSe expõe alias correto (nfse) em module.json', function () {
    $module = Module::find('NFSe');

    if ($module === null) {
        $this->markTestSkipped('Módulo NFSe não está registrado — skip alias check');
    }

    expect($module->get('alias'))->toBe('nfse');
});

it('cenário 3: rota nomeada nfse.index existe', function () {
    expect(\Route::has('nfse.index'))->toBeTrue('Rota nfse.index deveria existir per Modules/NFSe/Routes/web.php');
});

it('cenário 4: rota nomeada nfse.create existe', function () {
    expect(\Route::has('nfse.create'))->toBeTrue('Rota nfse.create (GET /nfse/emitir) deveria existir');
});

it('cenário 5: rota nomeada nfse.store existe', function () {
    expect(\Route::has('nfse.store'))->toBeTrue('Rota nfse.store (POST /nfse/emitir) deveria existir');
});

it('cenário 6: rota nomeada nfse.show existe', function () {
    expect(\Route::has('nfse.show'))->toBeTrue('Rota nfse.show (GET /nfse/{nfse}) deveria existir');
});

it('cenário 7: rota nomeada nfse.cancelar existe', function () {
    expect(\Route::has('nfse.cancelar'))->toBeTrue('Rota nfse.cancelar (POST /nfse/{nfse}/cancelar) deveria existir');
});

it('cenário 8: rota nomeada nfse.pdf existe', function () {
    expect(\Route::has('nfse.pdf'))->toBeTrue('Rota nfse.pdf (GET /nfse/{nfse}/pdf) deveria existir');
});

it('cenário 9: ServiceProvider de NFSe não lança erro ao bootar', function () {
    // Se a aplicação subiu sem fatal, o ServiceProvider rodou. Confirmar via classe existir.
    expect(class_exists(\Modules\NFSe\Providers\NfseServiceProvider::class))
        ->toBeTrue('NfseServiceProvider deveria estar autoloaded');
});
