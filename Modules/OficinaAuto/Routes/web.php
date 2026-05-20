<?php

use App\Http\Controllers\ServiceOrderFsmActionController;
use Illuminate\Support\Facades\Route;
use Modules\OficinaAuto\Http\Controllers\InstallController;
use Modules\OficinaAuto\Http\Controllers\ProducaoOficinaController;
use Modules\OficinaAuto\Http\Controllers\Public\AprovacaoOsController;
use Modules\OficinaAuto\Http\Controllers\ServiceOrderController;
use Modules\OficinaAuto\Http\Controllers\VehicleController;

// ─────────────────────────────────────────────────────────────────────────────
// Rotas Install 1-click (ADR 0024 / BaseModuleInstallController).
//
// Sem essas 3 rotas, action() helper em Install/ModulesController vira '#'
// e o botão "Install" da tela /manage-modules fica sem ação.
// Incidente documentado: ConsultaOs 2026-05-04 (skill criar-modulo §Críticas).
// ─────────────────────────────────────────────────────────────────────────────
Route::middleware(['web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu'])
    ->prefix('oficina-auto')
    ->group(function () {
        Route::get('install',           [InstallController::class, 'index']);
        Route::get('install/uninstall', [InstallController::class, 'uninstall']);
        Route::get('install/update',    [InstallController::class, 'update']);
    });

// ─────────────────────────────────────────────────────────────────────────────
// V0 — CRUD Vehicle + ServiceOrder (US-OFICINA-001).
// Stack canônica UltimatePOS admin (skill criar-modulo §Críticas).
// ─────────────────────────────────────────────────────────────────────────────
Route::middleware(['web', 'SetSessionData', 'auth', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin'])
    ->prefix('oficina-auto')
    ->group(function () {
        // ─────────────────────────────────────────────────────────────────────
        // Produção · Oficina — Kanban estado das caçambas (Martinho 13/maio 2026).
        // Espelha 1:1 prototipo-ui/prototipos/producao-oficina/F1.html adaptado
        // pra caçambas (5 colunas: disponivel/locada/aguardando/manutencao/pronta).
        // ─────────────────────────────────────────────────────────────────────
        Route::get('producao-oficina',
            [ProducaoOficinaController::class, 'index'])
            ->name('oficinaauto.producao-oficina');

        // CRUD Vehicle
        Route::get('veiculos',                     [VehicleController::class, 'index'])->name('oficinaauto.vehicles.index');
        Route::get('veiculos/create',              [VehicleController::class, 'create'])->name('oficinaauto.vehicles.create');
        // D8 Security Wave 15: throttle 60 req/min nas mutações autenticadas (anti-bot+anti-abuse).
        Route::post('veiculos',                    [VehicleController::class, 'store'])
            ->middleware('throttle:60,1')
            ->name('oficinaauto.vehicles.store');
        Route::get('veiculos/{vehicle}',           [VehicleController::class, 'show'])->name('oficinaauto.vehicles.show');
        Route::get('veiculos/{vehicle}/edit',      [VehicleController::class, 'edit'])->name('oficinaauto.vehicles.edit');
        Route::put('veiculos/{vehicle}',           [VehicleController::class, 'update'])
            ->middleware('throttle:60,1')
            ->name('oficinaauto.vehicles.update');
        Route::delete('veiculos/{vehicle}',        [VehicleController::class, 'destroy'])
            ->middleware('throttle:30,1')
            ->name('oficinaauto.vehicles.destroy');

        // CRUD ServiceOrder (status livre V0; FSM em US-OFICINA-003)
        Route::get('ordens-servico',                [ServiceOrderController::class, 'index'])->name('oficinaauto.orders.index');
        Route::get('ordens-servico/create',         [ServiceOrderController::class, 'create'])->name('oficinaauto.orders.create');
        Route::post('ordens-servico',               [ServiceOrderController::class, 'store'])
            ->middleware('throttle:60,1')
            ->name('oficinaauto.orders.store');
        Route::get('ordens-servico/{order}',        [ServiceOrderController::class, 'show'])->name('oficinaauto.orders.show');
        Route::get('ordens-servico/{order}/edit',   [ServiceOrderController::class, 'edit'])->name('oficinaauto.orders.edit');
        Route::put('ordens-servico/{order}',        [ServiceOrderController::class, 'update'])
            ->middleware('throttle:60,1')
            ->name('oficinaauto.orders.update');
        Route::delete('ordens-servico/{order}',     [ServiceOrderController::class, 'destroy'])
            ->middleware('throttle:30,1')
            ->name('oficinaauto.orders.destroy');

        // ─────────────────────────────────────────────────────────────────────
        // Hotfix Wave 7+ — drawer ServiceOrderSheet.fetchData chama URL inglês
        // /service-orders/{id} esperando JSON (Accept-aware show). Alias necessário
        // pra evitar 404 quando user clica row na ServiceOrders Index.
        // ─────────────────────────────────────────────────────────────────────
        Route::get('service-orders/{order}',
            [ServiceOrderController::class, 'show'])
            ->name('oficinaauto.service_orders.show.json');

        // ─────────────────────────────────────────────────────────────────────
        // Wave 7-A — FSM action endpoints (espelha SaleFsmActionController).
        // Frontend Wave 7-B (FsmActionPanel.tsx ServiceOrder variant) consome.
        // ADR 0143 (FSM Pipeline LIVE prod biz=1) + ADR 0137 (OficinaAuto vertical).
        // ─────────────────────────────────────────────────────────────────────
        Route::get('service-orders/{order}/fsm/actions',
            [ServiceOrderFsmActionController::class, 'actions'])
            ->name('oficinaauto.service_orders.fsm.actions');

        // D8 Security Wave 15: FSM execute é side-effect crítico (reserva estoque, cancela NFe,
        // dispara WhatsApp). Throttle 60 req/min/IP previne abuse de actions repetidas.
        Route::post('service-orders/{order}/fsm/execute',
            [ServiceOrderFsmActionController::class, 'execute'])
            ->middleware('throttle:60,1')
            ->name('oficinaauto.service_orders.fsm.execute');

        Route::post('service-orders/{order}/fsm/start-pipeline',
            [ServiceOrderFsmActionController::class, 'startPipeline'])
            ->middleware('throttle:30,1')
            ->name('oficinaauto.service_orders.fsm.start-pipeline');

        // ─────────────────────────────────────────────────────────────────────
        // Wave 7-C — Timeline FSM auditável (gap #1 estado-da-arte FSM screen).
        // Frontend ServiceOrderTimeline.tsx consome via drawer ServiceOrderSheet.
        // Espelha GET /api/sells/{id}/history (SaleHistoryController).
        // ─────────────────────────────────────────────────────────────────────
        Route::get('service-orders/{order}/history',
            [ServiceOrderFsmActionController::class, 'history'])
            ->name('oficinaauto.service_orders.history');
    });

// ─────────────────────────────────────────────────────────────────────────────
// Rotas PÚBLICAS — Aprovação OS via WhatsApp link + PIN (US-OFICINA-006).
//
// Cliente final (NÃO User do sistema) acessa sem auth via token HMAC assinado
// + PIN 4 dígitos enviado out-of-band. Multi-tenant Tier 0 garantido pelo
// token carregar business_id assinado (AprovacaoOsService::validarToken).
//
// Throttle 30 req/min/IP anti-bruteforce. Lockout adicional 5 tentativas PIN.
//
// @see Modules/OficinaAuto/Http/Controllers/Public/AprovacaoOsController.php
// @see resources/js/Pages/OficinaAuto/AprovacaoPublica.charter.md
// ─────────────────────────────────────────────────────────────────────────────
Route::middleware(['web', 'throttle:30,1'])->group(function () {
    Route::get('/aprovar-os/{token}',  [AprovacaoOsController::class, 'show'])
        ->name('oficinaauto.aprovacao.show');
    Route::post('/aprovar-os/{token}', [AprovacaoOsController::class, 'submit'])
        ->name('oficinaauto.aprovacao.submit');
});
