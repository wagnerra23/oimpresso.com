<?php

use Modules\ComunicacaoVisual\Http\Controllers\AcabamentoController;
use Modules\ComunicacaoVisual\Http\Controllers\ApontamentoController;
use Modules\ComunicacaoVisual\Http\Controllers\InstalacaoCatalogoController;
use Modules\ComunicacaoVisual\Http\Controllers\InstallController;
use Modules\ComunicacaoVisual\Http\Controllers\OrcamentoController;
use Modules\ComunicacaoVisual\Http\Controllers\SubstratoController;

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

        // Sprint 1 — US-COMVIS-004: Spool Plotter / Apontamento de Produção
        // ATENÇÃO: em-andamento registrado antes de {apontamento} pra evitar conflito de rota
        Route::get('apontamentos/em-andamento', [ApontamentoController::class, 'emAndamento'])
            ->name('comvis.api.apontamentos.em_andamento');

        Route::get('apontamentos', [ApontamentoController::class, 'index'])
            ->name('comvis.api.apontamentos.index');

        Route::post('apontamentos/iniciar', [ApontamentoController::class, 'iniciar'])
            ->name('comvis.api.apontamentos.iniciar');

        Route::post('apontamentos/{apontamento}/finalizar', [ApontamentoController::class, 'finalizar'])
            ->name('comvis.api.apontamentos.finalizar');

        Route::post('apontamentos/{apontamento}/cancelar', [ApontamentoController::class, 'cancelar'])
            ->name('comvis.api.apontamentos.cancelar');

        // Fase 2 §2.3 — CRUD catálogos canônicos cv_* (US-COMVIS-002 + US-COMVIS-007)
        Route::apiResource('substratos', SubstratoController::class)
            ->names([
                'index'   => 'comvis.api.substratos.index',
                'store'   => 'comvis.api.substratos.store',
                'show'    => 'comvis.api.substratos.show',
                'update'  => 'comvis.api.substratos.update',
                'destroy' => 'comvis.api.substratos.destroy',
            ]);

        Route::apiResource('acabamentos', AcabamentoController::class)
            ->names([
                'index'   => 'comvis.api.acabamentos.index',
                'store'   => 'comvis.api.acabamentos.store',
                'show'    => 'comvis.api.acabamentos.show',
                'update'  => 'comvis.api.acabamentos.update',
                'destroy' => 'comvis.api.acabamentos.destroy',
            ]);

        Route::apiResource('instalacoes-catalogo', InstalacaoCatalogoController::class)
            ->parameters(['instalacoes-catalogo' => 'instalacao_catalogo'])
            ->names([
                'index'   => 'comvis.api.instalacoes_catalogo.index',
                'store'   => 'comvis.api.instalacoes_catalogo.store',
                'show'    => 'comvis.api.instalacoes_catalogo.show',
                'update'  => 'comvis.api.instalacoes_catalogo.update',
                'destroy' => 'comvis.api.instalacoes_catalogo.destroy',
            ]);
    });
