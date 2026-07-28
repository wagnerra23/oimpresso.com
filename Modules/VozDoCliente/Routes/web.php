<?php

use Modules\VozDoCliente\Http\Controllers\InstallController;
use Modules\VozDoCliente\Http\Controllers\SinalController;

/*
 * Voz do Cliente — canal DENTRO do login (decisão [W] 2026-07-28).
 *
 * A US-INFRA-002 escrita previa portal público com token por business; [W]
 * decidiu autenticado. Por isso a stack de middleware é a canônica do
 * UltimatePOS (mesma de TeamMcp/Repair) — `business_id` vem da sessão, não de
 * token, e o global scope da entidade protege a leitura.
 */
Route::middleware(['web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin'])
    ->prefix('voz-do-cliente')
    ->name('voz-do-cliente.')
    ->group(function () {

        // Caixa de triagem — só quem tem `vozdocliente.triar` (checado no controller).
        Route::get('/', [SinalController::class, 'index'])->name('index');

        // Gravação do sinal. Disponível de qualquer tela do ERP: quem relata manda
        // junto a URL onde a dor aconteceu, então o contexto não se perde.
        Route::post('/sinal', [SinalController::class, 'store'])->name('sinal.store');
    });

/*
 * Rotas Install 1-clique (ADR 0024 / BaseModuleInstallController). Sem elas o
 * helper action() em Install/ModulesController vira '#' e o botão "Install" da
 * tela /manage-modules aparece mas não faz nada.
 */
Route::middleware(['web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu'])
    ->prefix('voz-do-cliente')
    ->group(function () {
        Route::get('install',           [InstallController::class, 'index']);
        Route::get('install/uninstall', [InstallController::class, 'uninstall']);
        Route::get('install/update',    [InstallController::class, 'update']);
    });
