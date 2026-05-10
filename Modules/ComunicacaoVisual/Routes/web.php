<?php

use Modules\ComunicacaoVisual\Http\Controllers\InstallController;
use Modules\ComunicacaoVisual\Http\Controllers\OrcamentoController;

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

// Sprint 1 — US-COMVIS-001: API de cálculo m² + persistência de orçamentos.
// Middleware padrão UltimatePOS pra rotas admin com autenticação completa.
Route::middleware(['web', 'SetSessionData', 'auth', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin'])
    ->prefix('comunicacao-visual/api')
    ->group(function () {
        // Preview authoritative server-side (sem persistência)
        Route::post('calcular', [OrcamentoController::class, 'calcular'])
            ->name('comvis.api.calcular');

        // Persistência de orçamentos
        Route::post('orcamentos', [OrcamentoController::class, 'store'])
            ->name('comvis.api.orcamentos.store');

        // Consulta de orçamento por ID (multi-tenant: global scope filtra automaticamente)
        Route::get('orcamentos/{id}', [OrcamentoController::class, 'show'])
            ->name('comvis.api.orcamentos.show');
    });
