<?php

use Inertia\Inertia;
use Modules\ComunicacaoVisual\Http\Controllers\ApontamentoController;
use Modules\ComunicacaoVisual\Http\Controllers\InstallController;
use Modules\ComunicacaoVisual\Http\Controllers\OrcamentoController;

// Hub stub Sprint 2 (Wagner 2026-05-26): rota raiz renderiza Index.tsx
// existente como hub "em construção" listando 4 áreas (Orçamentos/OS/
// Materiais/Apontamentos). Substitui o dropdown legacy do DataController
// que apontava pra URLs /comunicacao-visual/admin/* não existentes (404).
// Sprint 2 entrega telas Inertia próprias — até lá, hub stub +
// acesso direto via APIs /comunicacao-visual/api/*.
Route::middleware(['web', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin'])
    ->prefix('comunicacao-visual')
    ->group(function () {
        Route::get('/', function () {
            // Permission gate canon — qualquer perm de visualização CV libera o hub.
            if (! auth()->user()->can('superadmin')
                && ! auth()->user()->can('comvis.orcamento.view')
                && ! auth()->user()->can('comvis.os.view')) {
                abort(403, 'Sem permissão Comunicação Visual (comvis.orcamento.view ou comvis.os.view).');
            }

            return Inertia::render('ComunicacaoVisual/Index', [
                'bizName' => session('business.name', 'oimpresso'),
            ]);
        })->name('comunicacao-visual.index');
    });

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
// Wave 10 D8 Security: throttle:60,1 — proteção contra abuso de cálculo m² authoritative
// (endpoint stateless mas faz DB-write em /orcamentos e /apontamentos).
Route::middleware(['web', 'SetSessionData', 'auth', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin', 'throttle:60,1'])
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
    });
