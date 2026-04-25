<?php

use Illuminate\Support\Facades\Route;
use Modules\NfeBrasil\Http\Controllers\InstallController;
use Modules\NfeBrasil\Http\Controllers\NfeBrasilController;

/*
|--------------------------------------------------------------------------
| Web Routes — NfeBrasil
|--------------------------------------------------------------------------
*/

// Install routes (acessadas via /manage-modules link "Install").
// Pattern reutilizado de Modules/Connector/Routes/web.php.
Route::middleware(['web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu'])
    ->prefix('nfebrasil')
    ->group(function () {
        Route::get('install', [InstallController::class, 'index']);
        Route::get('install/uninstall', [InstallController::class, 'uninstall']);
        Route::get('install/update', [InstallController::class, 'update']);
    });

// Rotas operacionais (placeholder — a expandir nas próximas sub-ondas)
Route::group([], function () {
    Route::resource('nfebrasil', NfeBrasilController::class)->names('nfebrasil');
});
