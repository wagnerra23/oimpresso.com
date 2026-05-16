<?php

declare(strict_types=1);

use Nwidart\Modules\Facades\Module;

uses(Tests\TestCase::class);

/**
 * Smoke tests das rotas principais do Modules/Crm.
 *
 * Garante que:
 *   1. Modulo Crm aparece registrado em nWidart
 *   2. Rotas resource principais foram registradas (follow-ups, leads, campaigns, proposals, contact-login)
 *   3. Stack de middlewares UltimatePOS aplica (web + auth + SetSessionData + AdminSidebarMenu + CheckUserLogin)
 *
 * Tests não autenticam — só validam registro/binding. Smoke routes autenticadas
 * exigiriam factory User+Business completa do schema UltimatePOS (out of scope desta wave).
 *
 * Refs: ADR 0093 multi-tenant scope, ADR 0011 padrao Jana/Repair, skill criar-modulo.
 *
 * @see Modules/Crm/Routes/web.php
 */

it('cenario 1: módulo Crm aparece registrado em nWidart', function () {
    $module = Module::find('Crm');
    expect($module)->not->toBeNull('Modules/Crm deveria estar registrado em nWidart');
    expect($module->getName())->toBe('Crm');
});

it('cenario 2: módulo Crm está ativo (alias=crm, active=1)', function () {
    $manifest = json_decode(file_get_contents(
        __DIR__.'/../../module.json'
    ), true);

    expect($manifest['active'])->toBe(1);
    expect($manifest['alias'])->toBe('crm');
});

it('cenario 3: rota nomeada follow-ups.index existe (Schedule resource)', function () {
    expect(\Route::has('follow-ups.index'))->toBeTrue(
        'Rota follow-ups.index deveria existir per Routes/web.php (Route::resource follow-ups)'
    );
});

it('cenario 4: rota nomeada leads.index existe (Lead resource)', function () {
    expect(\Route::has('leads.index'))->toBeTrue(
        'Rota leads.index deveria existir per Routes/web.php (Route::resource leads)'
    );
});

it('cenario 5: rota nomeada campaigns.index existe (Campaign resource)', function () {
    expect(\Route::has('campaigns.index'))->toBeTrue(
        'Rota campaigns.index deveria existir per Routes/web.php (Route::resource campaigns)'
    );
});

it('cenario 6: rota nomeada contact-login.index existe (ContactLogin resource)', function () {
    expect(\Route::has('contact-login.index'))->toBeTrue(
        'Rota contact-login.index deveria existir per Routes/web.php'
    );
});

it('cenario 7: rota nomeada proposals.index existe (Proposal resource)', function () {
    expect(\Route::has('proposals.index'))->toBeTrue(
        'Rota proposals.index deveria existir per Routes/web.php (Route::resource proposals)'
    );
});

it('cenario 8: stack de middlewares Crm inclui auth + SetSessionData + AdminSidebarMenu', function () {
    // Inspeciona uma das rotas resource do grupo crm/ — todas compartilham o mesmo stack.
    $route = \Route::getRoutes()->getByName('campaigns.index');
    expect($route)->not->toBeNull();

    $middlewares = $route->gatherMiddleware();

    // UltimatePOS stack padrão (rotas autenticadas backend)
    expect($middlewares)->toContain('auth');
    expect($middlewares)->toContain('SetSessionData');
    expect($middlewares)->toContain('AdminSidebarMenu');
    expect($middlewares)->toContain('CheckUserLogin');
});

it('cenario 9: rotas install/uninstall estão registradas (Wagner aprova superadmin)', function () {
    // Routes/web.php:37-40 — get install, post install, get install/uninstall, get install/update.
    $routes = collect(\Route::getRoutes())->map(fn ($r) => $r->uri())->toArray();

    expect(collect($routes))->toContain('crm/install');
    expect(collect($routes))->toContain('crm/install/uninstall');
});
