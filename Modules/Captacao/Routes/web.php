<?php

use Illuminate\Support\Facades\Route;
use Modules\Captacao\Http\Controllers\InstallController;
use Modules\Captacao\Http\Middleware\CaptacaoLiberado;

/*
 * Rotas Install 1-clique (ADR 0024). Ficam fora do gate do pacote: instalar acontece antes
 * de qualquer empresa ter o pacote. As telas entram nas ondas 2 a 4 da ADR 0452.
 */
Route::middleware(['web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu'])
    ->prefix('captacao')
    ->group(function () {
        Route::post('install', [InstallController::class, 'install']);
        Route::get('install', [InstallController::class, 'index']);
        Route::match(['get', 'post'], 'install/uninstall', [InstallController::class, 'uninstall']);
        Route::match(['get', 'post'], 'install/update', [InstallController::class, 'update']);
    });

/*
 * Telas do módulo (ADR 0452). Todas atrás do pacote + `captacao.access` (CaptacaoLiberado).
 * Cada onda escreve só no seu bloco, para sessões paralelas não colidirem.
 */
Route::middleware(['web', 'SetSessionData', 'auth', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin', CaptacaoLiberado::class])
    ->prefix('captacao')
    ->name('captacao.')
    ->group(function () {
        // ── onda 2 · fontes e projetos ──

        // ── onda 3 · aportes ──

        // ── onda 4 · comprovantes e prestação de contas ──
    });
