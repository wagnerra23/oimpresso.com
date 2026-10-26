<?php

use Illuminate\Support\Facades\Route;
use Modules\Marketplaces\Http\Controllers\ContasController;
use Modules\Marketplaces\Http\Controllers\InstallController;
use Modules\Marketplaces\Http\Middleware\MarketplacesLiberado;

/*
 * Rotas Install 1-clique (ADR 0024). Ficam fora do gate do pacote: instalar acontece antes
 * de qualquer empresa ter o pacote.
 */
Route::middleware(['web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu'])
    ->prefix('marketplaces')
    ->group(function () {
        Route::post('install', [InstallController::class, 'install']);
        Route::get('install', [InstallController::class, 'index']);
        Route::match(['get', 'post'], 'install/uninstall', [InstallController::class, 'uninstall']);
        Route::match(['get', 'post'], 'install/update', [InstallController::class, 'update']);
    });

/*
 * Conexão da conta do vendedor (US-MKT-003). Só com o pacote `marketplaces_module` da empresa e a
 * permissão de conectar contas; a empresa vem da sessão, nunca da URL.
 */
Route::middleware(['web', 'SetSessionData', 'auth', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin',
    MarketplacesLiberado::class.':marketplaces.account.connect'])
    ->prefix('marketplaces')
    ->name('marketplaces.')
    ->group(function () {
        Route::get('contas/mercado-livre/conectar', [ContasController::class, 'conectar'])->name('contas.mercado_livre.conectar');
        Route::get('oauth/callback/mercado-livre', [ContasController::class, 'callback'])->name('oauth.mercado_livre.callback');
    });
