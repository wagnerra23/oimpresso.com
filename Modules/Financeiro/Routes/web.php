<?php

use Illuminate\Support\Facades\Route;
use Modules\Financeiro\Http\Controllers\DashboardController;

/*
|--------------------------------------------------------------------------
| Web Routes — Financeiro
|--------------------------------------------------------------------------
| Padrão UltimatePOS: middleware stack admin (web, authh, auth, ...) é
| aplicado pelo RouteServiceProvider do core. Aqui apenas o `web` é setado
| via map; rotas acessíveis em /financeiro/...
*/

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
