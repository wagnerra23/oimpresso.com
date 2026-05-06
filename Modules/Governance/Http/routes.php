<?php

use Illuminate\Support\Facades\Route;
use Modules\Governance\Http\Controllers\DashboardController;
use Modules\Governance\Http\Controllers\InstallController;
use Modules\Governance\Http\Controllers\DataController;

/*
|--------------------------------------------------------------------------
| Module Governance — Web Routes
|--------------------------------------------------------------------------
|
| ADR 0086 MVP — UI básica /governance lista pendências consolidadas.
| Rotas detalhadas (policies CRUD, audit filtrável, drift alerts) ficam
| pra próxima fase quando frontend for implementado.
|
*/

Route::middleware(['web', 'auth', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin'])
    ->prefix('governance')
    ->name('governance.')
    ->group(function () {
        // Dashboard consolidado
        Route::get('/', [DashboardController::class, 'index'])
            ->name('admin.dashboard');

        // Install hooks (ADR 0024)
        Route::get('install/install',   [InstallController::class, 'install'])
            ->name('install.install');
        Route::get('install/uninstall', [InstallController::class, 'uninstall'])
            ->name('install.uninstall');
        Route::get('install/update',    [InstallController::class, 'update'])
            ->name('install.update');
    });
