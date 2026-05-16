<?php

use Modules\ConsultaOs\Http\Controllers\ConsultaOsController;
use Modules\ConsultaOs\Http\Controllers\InstallController;

// Portal publico de consulta de OS — sem middleware auth, espelha
// Modules/Repair/Routes/web.php (rotas /repair-status e /post-repair-status).
// D8.a Security — throttle:30,1 (30 req/min) em consulta pública anti-enumeration.
Route::prefix('consulta-os')->name('consulta-os.')->group(function () {

    Route::get('/', [ConsultaOsController::class, 'index'])
        ->name('index')
        ->middleware('throttle:30,1');

    Route::get('/buscar', [ConsultaOsController::class, 'buscar'])
        ->name('buscar')
        ->middleware('throttle:30,1');
});

// Rotas Install 1-click (ADR 0024 / BaseModuleInstallController).
// Sem essas rotas o action() helper em Install/ModulesController vira '#'
// e o botao "Install" da tela /manage-modules fica sem acao.
Route::middleware(['web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu'])
    ->prefix('consulta-os')
    ->group(function () {
        Route::get('install',           [InstallController::class, 'index']);
        Route::get('install/uninstall', [InstallController::class, 'uninstall']);
        Route::get('install/update',    [InstallController::class, 'update']);
    });
