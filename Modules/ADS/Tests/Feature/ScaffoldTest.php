<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Nwidart\Modules\Facades\Module;

uses(Tests\TestCase::class);

/**
 * Scaffold ADS — sanity-check de instalação nWidart + rotas críticas (ADR 0011 / 0024).
 *
 * Garante que o módulo está registrado, descobrível, e expõe os 3 endpoints
 * de Install obrigatórios do padrão UltimatePOS modular.
 *
 * @see memory/requisitos/Infra/RUNBOOK-criar-modulo.md
 */

it('módulo ADS está registrado em nWidart', function () {
    $mod = Module::find('ADS');
    expect($mod)->not->toBeNull();
    expect($mod->getName())->toBe('ADS');
});

it('módulo ADS está habilitado (status enabled)', function () {
    $mod = Module::find('ADS');
    expect($mod)->not->toBeNull();
    expect($mod->isEnabled())->toBeTrue();
});

it('3 rotas Install canônicas registradas (ADR 0024)', function () {
    // Index + uninstall + update — botão "Install" no /manage-modules depende disso
    $rotas = ['ads/install', 'ads/install/uninstall', 'ads/install/update'];
    foreach ($rotas as $uri) {
        $achou = collect(Route::getRoutes())->contains(fn ($r) => $r->uri() === $uri);
        expect($achou)->toBeTrue("Rota {$uri} deve existir conforme ADR 0024");
    }
});

it('rota canônica Inbox decisões (ads.admin.decisoes.index) registrada', function () {
    expect(Route::has('ads.admin.decisoes.index'))->toBeTrue();
});

it('rota canônica Policy read-only (ads.admin.policy.index) registrada', function () {
    expect(Route::has('ads.admin.policy.index'))->toBeTrue();
});
