<?php

use Illuminate\Support\Facades\Route;
use Modules\LaravelAI\Http\Controllers\InstallController;
use Modules\LaravelAI\Http\Controllers\LaravelAIController;

/*
|--------------------------------------------------------------------------
| Web Routes — LaravelAI
|--------------------------------------------------------------------------
*/

// Install routes (acessadas via /manage-modules link "Install").
Route::middleware(['web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu'])
    ->prefix('laravelai')
    ->group(function () {
        Route::get('install', [InstallController::class, 'index']);
        Route::get('install/uninstall', [InstallController::class, 'uninstall']);
        Route::get('install/update', [InstallController::class, 'update']);
    });

// Rotas operacionais (placeholder — a expandir nas próximas sub-ondas)
Route::group([], function () {
    Route::resource('laravelai', LaravelAIController::class)->names('laravelai');
});
