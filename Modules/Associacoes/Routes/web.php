<?php

use Illuminate\Support\Facades\Route;
use Modules\Associacoes\Http\Controllers\AssociacoesController;
use Modules\Associacoes\Http\Controllers\DocumentosAssociacaoController;
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
        Route::get('lista', [AssociacoesController::class, 'index'])->name('lista');
        Route::post('lista', [AssociacoesController::class, 'store'])->name('store');
        Route::put('{id}', [AssociacoesController::class, 'update'])->whereNumber('id')->name('update');
        Route::post('{id}/diretoria', [AssociacoesController::class, 'diretoria'])->whereNumber('id')->name('diretoria');
        Route::post('{id}/documentos', [DocumentosAssociacaoController::class, 'store'])->whereNumber('id')->name('documentos.store');
        Route::post('documentos/{documento}/tirar', [DocumentosAssociacaoController::class, 'tirar'])->whereNumber('documento')->name('documentos.tirar');
        Route::get('documentos/{documento}/arquivo', [DocumentosAssociacaoController::class, 'arquivo'])->whereNumber('documento')->name('documentos.arquivo');

        // ── onda 4 · associadas e consentimentos (US-ASSOC-003) ──

        // ── onda 5 · painel da rede (US-ASSOC-004) ──
    });
