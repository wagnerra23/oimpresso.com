<?php

use Modules\ConsultaOs\Http\Controllers\ConsultaOsController;
use Modules\ConsultaOs\Http\Controllers\InstallController;
use Modules\ConsultaOs\Services\EmpresaPublica;

// Portal publico de consulta de OS — sem middleware auth, espelha
// Modules/Repair/Routes/web.php (rota /repair-status; o /post-repair-status saiu em 2026-10-27).
// D8.a Security — throttle:30,1 (30 req/min) em consulta pública anti-enumeration.
// Decisão [W] 2026-11-17 — "empresa na URL": a busca é /consulta-os/{codigo}/buscar e só olha
// aquela empresa. /consulta-os sem código não busca (a tela pede o link da empresa). O formato
// do código não casa `install`, então as rotas de instalação abaixo seguem alcançáveis.
Route::prefix('consulta-os')->name('consulta-os.')->group(function () {

    Route::get('/', [ConsultaOsController::class, 'index'])
        ->name('index')
        ->middleware('throttle:30,1');

    Route::get('/{empresa}', [ConsultaOsController::class, 'empresa'])
        ->name('empresa')
        ->where('empresa', EmpresaPublica::FORMATO)
        ->middleware('throttle:30,1');

    Route::get('/{empresa}/buscar', [ConsultaOsController::class, 'buscar'])
        ->name('buscar')
        ->where('empresa', EmpresaPublica::FORMATO)
        ->middleware('throttle:30,1');
});

// Rotas Install 1-click (ADR 0024 / BaseModuleInstallController).
// Sem essas rotas o action() helper em Install/ModulesController vira '#'
// e o botao "Install" da tela /manage-modules fica sem acao.
Route::middleware(['web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu'])
    ->prefix('consulta-os')
    ->group(function () {
        Route::post('install',           [InstallController::class, 'install']);
        Route::get('install',           [InstallController::class, 'index']);
        Route::match(['get', 'post'], 'install/uninstall', [InstallController::class, 'uninstall']);
        Route::match(['get', 'post'], 'install/update',    [InstallController::class, 'update']);
    });
