<?php

use Modules\ComunicacaoVisual\Http\Controllers\InstallController;

// Rotas Install 1-click (ADR 0024 / BaseModuleInstallController).
// Sem essas rotas o action() helper em Install/ModulesController vira '#'
// e o botão "Install" da tela /manage-modules fica sem ação.
// Incidente documentado: ConsultaOs 2026-05-04 (RUNBOOK §troubleshooting).
Route::middleware(['web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu'])
    ->prefix('comunicacao-visual')
    ->group(function () {
        Route::get('install',           [InstallController::class, 'index']);
        Route::get('install/uninstall', [InstallController::class, 'uninstall']);
        Route::get('install/update',    [InstallController::class, 'update']);
    });

// Sprint 2+: rotas admin (Orçamentos, OS, Materiais, Apontamentos)
// entram aqui após sinal qualificado (ADR 0105) e piloto 2026-Q3.
// Route::middleware(['web', 'SetSessionData', 'auth', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin'])
//     ->prefix('comunicacao-visual')
//     ->group(function () {
//         Route::get('/admin/orcamentos', [OrcamentoController::class, 'index'])->name('comvis.orcamentos.index');
//     });
