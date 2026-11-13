<?php

use Illuminate\Support\Facades\Route;
use Modules\Associacoes\Http\Controllers\AssociadasController;
use Modules\Associacoes\Http\Controllers\InstallController;
use Modules\Associacoes\Http\Middleware\AssociacoesLiberado;

/*
 * Rotas Install 1-clique (ADR 0024). Ficam fora do gate do pacote: instalar acontece antes
 * de qualquer empresa ter o pacote. As telas entram nas ondas 3 a 5 da ADR 0451.
 */
Route::middleware(['web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu'])
    ->prefix('associacoes')
    ->group(function () {
        Route::post('install', [InstallController::class, 'install']);
        Route::get('install', [InstallController::class, 'index']);
        Route::match(['get', 'post'], 'install/uninstall', [InstallController::class, 'uninstall']);
        Route::match(['get', 'post'], 'install/update', [InstallController::class, 'update']);
    });

/*
 * Telas do módulo (ADR 0451). Todas atrás do pacote + `associacoes.access` (AssociacoesLiberado);
 * o alcance por associação (D4) é aplicado em cada consulta por AssociacoesAcesso::restringir().
 * Cada onda escreve só no seu bloco, para sessões paralelas não colidirem.
 */
Route::middleware(['web', 'SetSessionData', 'auth', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin', AssociacoesLiberado::class])
    ->prefix('associacoes')
    ->name('associacoes.')
    ->group(function () {
        // ── onda 3 · associações e documentos (US-ASSOC-002) ──

        // ── onda 4 · associadas e consentimentos (US-ASSOC-003) ──
        Route::get('associadas', [AssociadasController::class, 'index'])->name('associadas.index');
        Route::get('associadas/contatos', [AssociadasController::class, 'contatos'])->name('associadas.contatos');
        Route::post('associadas', [AssociadasController::class, 'store'])->name('associadas.store');
        Route::post('associadas/{id}/desligar', [AssociadasController::class, 'desligar'])->whereNumber('id')->name('associadas.desligar');

        // ── onda 5 · painel da rede (US-ASSOC-004) ──
    });
