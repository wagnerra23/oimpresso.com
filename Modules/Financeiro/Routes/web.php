<?php

use Illuminate\Support\Facades\Route;
use Modules\Financeiro\Http\Controllers\DashboardController;
use Modules\Financeiro\Http\Controllers\InstallController;

/*
|--------------------------------------------------------------------------
| Web Routes — Financeiro
|--------------------------------------------------------------------------
| Padrão UltimatePOS (alinhado com Modules/Connector/Routes/web.php).
*/

// Install routes (acessadas via /manage-modules link "Install").
// Pattern reutilizado de Modules/Connector/Routes/web.php.
Route::middleware(['web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu'])
    ->prefix('financeiro')
    ->group(function () {
        Route::get('install', [InstallController::class, 'index']);
        Route::get('install/uninstall', [InstallController::class, 'uninstall']);
        Route::get('install/update', [InstallController::class, 'update']);
    });

// Rotas operacionais do módulo
Route::middleware(['web', 'auth', 'language', 'timezone', 'AdminSidebarMenu'])
    ->prefix('financeiro')
    ->name('financeiro.')
    ->group(function () {
        // Dashboard unificado (US-FIN-013)
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        // Rotas legadas — redirect 301 pra dashboard com filtro pré-aplicado (UI-0002)
        Route::get('/contas-receber', fn () => redirect()->route('financeiro.dashboard', ['tipo' => 'receber', 'status' => 'aberto'], 301));
        Route::get('/contas-pagar', fn () => redirect()->route('financeiro.dashboard', ['tipo' => 'pagar', 'status' => 'aberto'], 301));
    });
