<?php

use Illuminate\Support\Facades\Route;
use Modules\RecurringBilling\Http\Controllers\ConfiguracoesController;
use Modules\RecurringBilling\Http\Controllers\InstallController;
use Modules\RecurringBilling\Http\Controllers\InterWebhookController;
use Modules\RecurringBilling\Http\Controllers\InvoiceController;
use Modules\RecurringBilling\Http\Controllers\PlanController;
use Modules\RecurringBilling\Http\Controllers\RecurringBillingController;
use Modules\RecurringBilling\Http\Controllers\SubscriptionEventController;
use Modules\RecurringBilling\Http\Controllers\SubscriptionFavoriteController;
use Modules\RecurringBilling\Http\Controllers\SubscriptionNoteController;

/*
|--------------------------------------------------------------------------
| Web Routes — RecurringBilling
|--------------------------------------------------------------------------
*/

// Install routes (acessadas via /manage-modules link "Install").
Route::middleware(['web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu'])
    ->prefix('recurringbilling')
    ->group(function () {
        Route::get('install', [InstallController::class, 'index']);
        Route::get('install/uninstall', [InstallController::class, 'uninstall']);
        Route::get('install/update', [InstallController::class, 'update']);
    });

// Rota canônica nova (v9,75 Ondas 3+4+5 — Inertia Page Cobrança Recorrente).
// Onda 7 v9,75 — Page Inertia Faturas (reusa InvoiceController existente).
// Skill `sidebar-menu-arch`: DataController.modifyAdminMenu aponta pra esta rota nomeada.
Route::middleware(['web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu'])
    ->prefix('recurring-billing')
    ->group(function () {
        Route::get('/', [RecurringBillingController::class, 'index'])
            ->name('recurring-billing.index');

        // Onda 3 v9,75 — store/cancelar/pausar/reativar Subscription.
        Route::post('/', [RecurringBillingController::class, 'store'])
            ->name('recurring-billing.store');
        Route::post('/{id}/cancelar', [RecurringBillingController::class, 'cancelar'])
            ->whereNumber('id')
            ->name('recurring-billing.cancelar');
        Route::post('/{id}/pausar', [RecurringBillingController::class, 'pausar'])
            ->whereNumber('id')
            ->name('recurring-billing.pausar');
        Route::post('/{id}/reativar', [RecurringBillingController::class, 'reativar'])
            ->whereNumber('id')
            ->name('recurring-billing.reativar');

        // Onda 9 v9,75 — Notes + Favorites por usuário (substitui localStorage Cowork).
        Route::post('/{subscriptionId}/notes', [SubscriptionNoteController::class, 'store'])
            ->whereNumber('subscriptionId')
            ->name('recurring-billing.notes.store');
        Route::delete('/{subscriptionId}/notes/{noteId}', [SubscriptionNoteController::class, 'destroy'])
            ->whereNumber('subscriptionId')->whereNumber('noteId')
            ->name('recurring-billing.notes.destroy');
        Route::post('/{subscriptionId}/notes/{noteId}/pin', [SubscriptionNoteController::class, 'togglePin'])
            ->whereNumber('subscriptionId')->whereNumber('noteId')
            ->name('recurring-billing.notes.pin');
        Route::post('/{subscriptionId}/favorite', [SubscriptionFavoriteController::class, 'toggle'])
            ->whereNumber('subscriptionId')
            ->name('recurring-billing.favorite');

        // Onda 16 v9,75 — Timeline append-only (events + notes humanas no mesmo stream).
        Route::get('/{subscriptionId}/events', [SubscriptionEventController::class, 'index'])
            ->whereNumber('subscriptionId')
            ->name('recurring-billing.events.index');
        Route::post('/{subscriptionId}/events', [SubscriptionEventController::class, 'store'])
            ->whereNumber('subscriptionId')
            ->name('recurring-billing.events.store');

        // Onda 20 v9,75 — Reenviar NFe wire (delega pra NfeBrasil canon endpoint).
        Route::post('/{subscriptionId}/reenviar-nfe', [RecurringBillingController::class, 'reenviarNfe'])
            ->whereNumber('subscriptionId')
            ->name('recurring-billing.reenviar-nfe');

        Route::get('/faturas', [InvoiceController::class, 'index'])
            ->name('recurring-billing.faturas.index');

        // Onda 8 v9,75 — Page Configurações (gateways · régua dunning · NFe auto · webhooks).
        Route::get('/configuracoes', [ConfiguracoesController::class, 'index'])
            ->name('recurring-billing.configuracoes.index');

        // Onda 6 — CRUD Planos (FQCN obrigatório, regra .claude/rules/routes.md).
        Route::prefix('planos')->group(function () {
            Route::get('/', [PlanController::class, 'index'])
                ->name('recurring-billing.planos.index');
            Route::get('/novo', [PlanController::class, 'create'])
                ->name('recurring-billing.planos.create');
            Route::post('/', [PlanController::class, 'store'])
                ->name('recurring-billing.planos.store');
            Route::get('/{id}/editar', [PlanController::class, 'edit'])
                ->whereNumber('id')
                ->name('recurring-billing.planos.edit');
            Route::put('/{id}', [PlanController::class, 'update'])
                ->whereNumber('id')
                ->name('recurring-billing.planos.update');
            Route::delete('/{id}', [PlanController::class, 'destroy'])
                ->whereNumber('id')
                ->name('recurring-billing.planos.destroy');
        });
    });

// Onda 10 v9,75 — CUTOVER: rotas legacy /recurringbilling REMOVIDAS, agora
// redireciona 301 (permanent) pra rota canônica /recurring-billing.
// Pré-canary G1 Martinho: pattern reusa "design literal copy" (ADR 0104 §5
// CUTOVER + canary 7d). Resource legacy fica como redirect-only.
Route::redirect('/recurringbilling', '/recurring-billing', 301);
Route::middleware(['web'])->group(function () {
    Route::get('/recurringbilling/{any}', function ($any) {
        return redirect('/recurring-billing/' . ltrim($any, '/'), 301);
    })->where('any', '.*');
});

// US-RB-042 — cancelamento de invoice via gateway + audit log + permissão
Route::middleware(['web', 'auth', 'SetSessionData'])
    ->prefix('financeiro')
    ->group(function () {
        Route::post('rb-invoices/{invoice}/cancelar', [InvoiceController::class, 'cancel'])
            ->name('rb-invoices.cancel');
    });

// US-RB-047 — webhook PIX Inter (público, validação shared secret no header).
// Wagner configura URL no Inter via PUT /webhooks/pix-recebidos. CSRF excluído
// no VerifyCsrfToken via /webhook/* (já existente pra Asaas).
// D8.a Security — throttle:60,1 (60 req/min). Defesa burst/replay storm.
Route::post('/webhooks/inter/pix/{businessId}', [InterWebhookController::class, 'handle'])
    ->where('businessId', '[0-9]+')
    ->middleware('throttle:60,1')
    ->name('webhooks.inter.pix');
