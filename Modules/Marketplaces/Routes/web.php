<?php

use Illuminate\Support\Facades\Route;
use Modules\Marketplaces\Http\Controllers\ContasController;
use Modules\Marketplaces\Http\Controllers\ContasNuvemshopController;
use Modules\Marketplaces\Http\Controllers\InstallController;
use Modules\Marketplaces\Http\Controllers\NotificacoesMercadoLivreController;
use Modules\Marketplaces\Http\Controllers\PedidosController;
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
        // Nuvemshop (ADR 0443): o controller recusa enquanto a flag MARKETPLACES_NUVEMSHOP_ATIVO estiver desligada.
        Route::get('contas/nuvemshop/conectar', [ContasNuvemshopController::class, 'conectar'])->name('contas.nuvemshop.conectar');
        Route::get('oauth/callback/nuvemshop', [ContasNuvemshopController::class, 'callback'])->name('oauth.nuvemshop.callback');
        Route::patch('contas/{conta}/local', [PedidosController::class, 'definirLocal'])->whereNumber('conta')->name('contas.local');
    });

/*
 * Notificações do Mercado Livre (US-MKT-006). Pública e sem CSRF (o prefixo /webhook/* já está
 * fora do VerifyCsrfToken): quem chama é o ML. O controller não confia no conteúdo — ver docblock.
 */
Route::middleware(['throttle:300,1'])
    ->post('/webhook/marketplaces/mercado-livre', [NotificacoesMercadoLivreController::class, 'receber'])
    ->name('marketplaces.webhook.mercado_livre');

/*
 * Tela de pedidos (US-MKT-008). Basta o pacote + `marketplaces.access`; escolher o local da conta
 * exige a permissão de conectar contas (grupo acima).
 */
Route::middleware(['web', 'SetSessionData', 'auth', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin',
    MarketplacesLiberado::class])
    ->prefix('marketplaces')
    ->name('marketplaces.')
    ->group(function () {
        Route::get('pedidos', [PedidosController::class, 'index'])->name('pedidos');
    });
