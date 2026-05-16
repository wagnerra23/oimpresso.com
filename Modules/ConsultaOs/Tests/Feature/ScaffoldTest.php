<?php

declare(strict_types=1);

use Nwidart\Modules\Facades\Module;

uses(Tests\TestCase::class);

/**
 * Smoke test do scaffold Modules/ConsultaOs (portal publico de consulta de OS).
 *
 * Garante que:
 *   1. Modulo aparece registrado em nWidart
 *   2. Rotas web /consulta-os foram registradas com nomes esperados
 *
 * Refs: ADR 0011 padrao Jana/Repair/Project, ADR 0024 install routes, skill criar-modulo.
 */

it('cenario 1: modulo ConsultaOs aparece registrado em nWidart', function () {
    $module = Module::find('ConsultaOs');
    expect($module)->not->toBeNull('Modules/ConsultaOs deveria estar registrado');
    expect($module->getName())->toBe('ConsultaOs');
});

it('cenario 2: rota nomeada consulta-os.index existe', function () {
    expect(\Route::has('consulta-os.index'))
        ->toBeTrue('Rota consulta-os.index deveria existir per Routes/web.php');
});

it('cenario 3: rota nomeada consulta-os.buscar existe', function () {
    expect(\Route::has('consulta-os.buscar'))
        ->toBeTrue('Rota consulta-os.buscar deveria existir');
});
