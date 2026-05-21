<?php

use Illuminate\Support\Facades\Route;
use Modules\Compras\Http\Controllers\ComprasController;
use Modules\Compras\Http\Controllers\InstallController;

/*
|--------------------------------------------------------------------------
| Web Routes — Compras
|--------------------------------------------------------------------------
|
| Wave 1 scaffold US-COM-001 — apenas Index + Install endpoints.
| Resource completo (create/store/show/edit/update/destroy) + importar-dfe
| entram nas Waves 3 e 6.
|
| Middleware canônico UltimatePOS: ['web','auth','SetSessionData','language',
| 'timezone','AdminSidebarMenu','CheckUserLogin'] — NÃO usar 'tenant' fantasma
| (LICOES_F3_FINANCEIRO_REJEITADO).
*/

// Install routes — acessadas via /manage-modules link "Install".
Route::middleware(['web', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu'])
    ->prefix('compras')
    ->group(function () {
        Route::get('install', [InstallController::class, 'index']);
        Route::get('install/uninstall', [InstallController::class, 'uninstall']);
        Route::get('install/update', [InstallController::class, 'update']);
    });

// Rotas operacionais.
Route::middleware(['web', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin'])
    ->prefix('compras')
    ->name('compras.')
    ->group(function () {
        Route::get('/', [ComprasController::class, 'index'])->name('index');
        Route::get('/{id}/detalhe', [ComprasController::class, 'show'])
            ->where('id', '[0-9]+')
            ->name('show');
    });
