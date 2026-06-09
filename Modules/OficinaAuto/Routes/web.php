<?php

use App\Http\Controllers\ServiceOrderFsmActionController;
use Illuminate\Support\Facades\Route;
use Modules\OficinaAuto\Http\Controllers\DviInspectionController;
use Modules\OficinaAuto\Http\Controllers\InstallController;
use Modules\OficinaAuto\Http\Controllers\ProducaoOficinaController;
use Modules\OficinaAuto\Http\Controllers\Public\AprovacaoOsController;
use Modules\OficinaAuto\Http\Controllers\ServiceOrderController;
use Modules\OficinaAuto\Http\Controllers\ServiceOrderItemController;
use Modules\OficinaAuto\Http\Controllers\ServiceOrderPhotoController;
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
        // Produção · Oficina — Kanban de REPARO (5 etapas: Recepção → Diagnóstico →
        // Aguardando peças → Em execução → Pronto). Convergência visual landada no
        // PR #2417. As keys FSM internas seguem como dívida F3 (charter v4). Oficina =
        // reparo, não locação (ADR 0265 — order_type ∈ {manutencao, mecanica}).
        // ─────────────────────────────────────────────────────────────────────
        Route::get('producao-oficina',
            [ProducaoOficinaController::class, 'index'])
            ->name('oficinaauto.producao-oficina');

        // CRUD Vehicle
        Route::get('veiculos',                     [VehicleController::class, 'index'])->name('oficinaauto.vehicles.index');
        Route::get('veiculos/create',              [VehicleController::class, 'create'])->name('oficinaauto.vehicles.create');
        // Consulta de placa (AJAX) — digita placa → dados técnicos do veículo.
        // ANTES de veiculos/{vehicle} pra 'consulta-placa' não virar parâmetro.
        // Throttle 20/min anti-abuse (consulta externa pode ter custo por chamada).
        Route::post('veiculos/consulta-placa',     [VehicleController::class, 'consultaPlaca'])
            ->middleware('throttle:20,1')
            ->name('oficinaauto.vehicles.consulta-placa');
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
        // Board (Kanban) das OS de mecânica — fluxo real do carro ([W] 2026-06-02).
        // ANTES de {order} pra 'board' não ser capturado como parâmetro.
        Route::get('ordens-servico/board',          [ServiceOrderController::class, 'board'])->name('oficinaauto.orders.board');
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

        // US-OFICINA-041 — Gate de aprovação: envia orçamento pro cliente (status → orcamento,
        // Observer dispara WhatsApp link+PIN). Delta protótipo Cowork "Nova OS".
        Route::post('ordens-servico/{order}/enviar-aprovacao',
            [ServiceOrderController::class, 'enviarAprovacao'])
            ->middleware('throttle:30,1')
            ->name('oficinaauto.orders.enviar-aprovacao');

        // ─────────────────────────────────────────────────────────────────────
        // Gap 3 — Imprimir OS PDF profissional A4 (US-OFICINA-037).
        // AJAX-only endpoint que retorna {success, receipt:{html_content,print_title}}.
        // Frontend printServiceOrder.ts injeta HTML em IFRAME oculto + window.print()
        // (espelha pattern SellPosController::printInvoice).
        // Throttle 30/1 anti-abuse (mesmo cap dos endpoints de leitura críticos).
        // ─────────────────────────────────────────────────────────────────────
        Route::get('ordens-servico/{order}/print',
            [ServiceOrderController::class, 'printInvoice'])
            ->middleware('throttle:30,1')
            ->name('oficinaauto.orders.print');

        // ─────────────────────────────────────────────────────────────────────
        // Wave 1.3 US-OFICINA-027 — Items de OS (peça / mão-de-obra / terceiro).
        // Schema oficina_service_order_items em Wave 27 G1 (migration 2026-05-17).
        // Drawer Cowork seção "PEÇAS & MÃO DE OBRA" (Wave 2) consome estes endpoints.
        // Throttle 60/1 nas mutações (padrão módulo).
        // ─────────────────────────────────────────────────────────────────────
        Route::post('ordens-servico/{order}/items',
            [ServiceOrderItemController::class, 'store'])
            ->middleware('throttle:60,1')
            ->name('oficinaauto.orders.items.store');

        Route::put('ordens-servico/{order}/items/{item}',
            [ServiceOrderItemController::class, 'update'])
            ->middleware('throttle:60,1')
            ->name('oficinaauto.orders.items.update');

        Route::delete('ordens-servico/{order}/items/{item}',
            [ServiceOrderItemController::class, 'destroy'])
            ->middleware('throttle:60,1')
            ->name('oficinaauto.orders.items.destroy');

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

        // ─────────────────────────────────────────────────────────────────────
        // F3 OS-V2-5 — Gate (checklist de etapa) da próxima transição da OS.
        // Frontend ServiceOrderStageGate.tsx consome no drawer (entre Peças e
        // Pipeline FSM). O servidor enforça a mesma regra no fsm/execute (422).
        // ─────────────────────────────────────────────────────────────────────
        Route::get('service-orders/{order}/fsm/gate',
            [ServiceOrderFsmActionController::class, 'gate'])
            ->name('oficinaauto.service_orders.fsm.gate');

        // ─────────────────────────────────────────────────────────────────────
        // Wave 3 — DVI (Vistoria Digital) US-OFICINA-035.
        // CAPTERRA-FICHA Repair gap #3 — wedge competitivo vs RepairShopr/mHelpDesk.
        // UI consumirá via fetch JSON em Wave 3b (depende drawer ServiceOrderRichSheet PR #1624).
        // ─────────────────────────────────────────────────────────────────────
        Route::post('ordens-servico/{order}/dvi',
            [DviInspectionController::class, 'store'])
            ->middleware('throttle:60,1')
            ->name('oficinaauto.orders.dvi.store');

        Route::put('ordens-servico/{order}/dvi/{item}',
            [DviInspectionController::class, 'update'])
            ->middleware('throttle:60,1')
            ->name('oficinaauto.orders.dvi.update');

        Route::delete('ordens-servico/{order}/dvi/{item}',
            [DviInspectionController::class, 'destroy'])
            ->middleware('throttle:60,1')
            ->name('oficinaauto.orders.dvi.destroy');

        // US-OFICINA-040 — Converte item DVI reprovado/atenção em linha de orçamento
        // (delta protótipo Cowork "Nova OS" · botão "+ orçamento"). Cria ServiceOrderItem.
        Route::post('ordens-servico/{order}/dvi/{item}/to-orcamento',
            [DviInspectionController::class, 'toOrcamento'])
            ->middleware('throttle:60,1')
            ->name('oficinaauto.orders.dvi.to-orcamento');

        // ─────────────────────────────────────────────────────────────────────
        // Gap 1 (2026-05-26) — Upload foto/laudo item DVI via Modules/Arquivos.
        // Substitui placeholder V2 FOTOS no drawer ServiceOrderRichSheet.
        // Multi-tenant Tier 0 (ADR 0093) via ArquivosService::attach (session-derived
        // business_id). Throttle 30/1 (upload mais pesado que CRUD JSON normal).
        // Sub-vertical 4 mecânica pesada Martinho biz=164 (ADR 0194).
        // ─────────────────────────────────────────────────────────────────────
        Route::post('ordens-servico/{order}/dvi/{item}/photo',
            [DviInspectionController::class, 'uploadPhoto'])
            ->middleware('throttle:30,1')
            ->name('oficinaauto.orders.dvi.photo.upload');

        Route::delete('ordens-servico/{order}/dvi/{item}/photo/{arquivo}',
            [DviInspectionController::class, 'deletePhoto'])
            ->middleware('throttle:30,1')
            ->name('oficinaauto.orders.dvi.photo.delete');

        // ─────────────────────────────────────────────────────────────────────
        // F3 OS-V2-1 — Fotos & Laudo OS-level (anexo da própria ServiceOrder via
        // HasArquivos · distinto da foto POR item DVI acima). Entram no laudo A4
        // impresso ("Fotos da vistoria"). Protótipo Cowork aprovado [W] 2026-06-09:
        // zona drag&drop (vazio/enviando/preenchido) + lightbox com legenda editável.
        // Multi-tenant Tier 0 (ADR 0093) via ArquivosService + cross-owner guard.
        // ─────────────────────────────────────────────────────────────────────
        Route::get('ordens-servico/{order}/fotos',
            [ServiceOrderPhotoController::class, 'index'])
            ->name('oficinaauto.orders.fotos.index');

        Route::post('ordens-servico/{order}/fotos',
            [ServiceOrderPhotoController::class, 'store'])
            ->middleware('throttle:30,1')
            ->name('oficinaauto.orders.fotos.store');

        Route::patch('ordens-servico/{order}/fotos/{arquivo}',
            [ServiceOrderPhotoController::class, 'updateLabel'])
            ->middleware('throttle:60,1')
            ->name('oficinaauto.orders.fotos.update');

        Route::delete('ordens-servico/{order}/fotos/{arquivo}',
            [ServiceOrderPhotoController::class, 'destroy'])
            ->middleware('throttle:30,1')
            ->name('oficinaauto.orders.fotos.destroy');
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
