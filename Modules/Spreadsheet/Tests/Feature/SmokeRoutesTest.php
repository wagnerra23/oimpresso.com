<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

uses(Tests\TestCase::class);

/**
 * Smoke test das rotas principais do Modules/Spreadsheet.
 *
 * Verifica que o RouteServiceProvider do módulo registra as rotas declaradas
 * em Modules/Spreadsheet/Routes/web.php sem erro de boot.
 *
 * Rotas declaradas (prefix `spreadsheet`):
 *   - GET    spreadsheet/get-sheet/{id}/share
 *   - POST   spreadsheet/post-share-sheet
 *   - Route::resource('sheets') → spreadsheet/sheets.{index,create,store,show,update,destroy}
 *   - GET    spreadsheet/install
 *   - POST   spreadsheet/install
 *   - GET    spreadsheet/install/uninstall
 *   - GET    spreadsheet/install/update
 *   - POST   spreadsheet/add-folder
 *   - POST   spreadsheet/move-to-folder
 *
 * @see Modules/Spreadsheet/Routes/web.php
 */

it('rota nomeada sheets.index existe (Route::resource)', function () {
    expect(Route::has('sheets.index'))->toBeTrue(
        'Route::resource(sheets) deveria gerar nome auto sheets.index'
    );
});

it('rota nomeada sheets.store existe (Route::resource)', function () {
    expect(Route::has('sheets.store'))->toBeTrue(
        'Route::resource(sheets) deveria gerar nome auto sheets.store'
    );
});

it('rota nomeada sheets.show existe (Route::resource)', function () {
    expect(Route::has('sheets.show'))->toBeTrue(
        'Route::resource(sheets) deveria gerar nome auto sheets.show'
    );
});

it('rota GET spreadsheet/install é resolvível pela URL', function () {
    $route = Route::getRoutes()->match(
        Illuminate\Http\Request::create('/spreadsheet/install', 'GET')
    );

    expect($route)->not->toBeNull();
    expect($route->getActionName())
        ->toBe('Modules\\Spreadsheet\\Http\\Controllers\\InstallController@index');
});

it('rota POST spreadsheet/post-share-sheet é resolvível pela URL', function () {
    $route = Route::getRoutes()->match(
        Illuminate\Http\Request::create('/spreadsheet/post-share-sheet', 'POST')
    );

    expect($route)->not->toBeNull();
    expect($route->getActionName())->toContain('SpreadsheetController');
    expect($route->getActionName())->toContain('postShareSpreadsheet');
});
