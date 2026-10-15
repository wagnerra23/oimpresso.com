<?php

use Modules\Producao\Http\Controllers\InstallController;

/*
 * Esqueleto (ADR 0431): só as rotas Install 1-clique (ADR 0024 /
 * BaseModuleInstallController). Sem elas o helper action() em
 * Install/ModulesController vira '#' e o botão "Install" de /manage-modules não
 * faz nada. Rotas de negócio entram nas threads seguintes do playbook.
 */
Route::middleware(['web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu'])
    ->prefix('producao')
    ->group(function () {
        Route::post('install',           [InstallController::class, 'install']);
        Route::get('install',           [InstallController::class, 'index']);
        Route::match(['get', 'post'], 'install/uninstall', [InstallController::class, 'uninstall']);
        Route::match(['get', 'post'], 'install/update',    [InstallController::class, 'update']);
    });
