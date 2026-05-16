<?php

use Illuminate\Support\Facades\Route;
use Modules\RecurringBilling\Http\Controllers\InstallController;
use Modules\RecurringBilling\Http\Controllers\InterWebhookController;
use Modules\RecurringBilling\Http\Controllers\InvoiceController;
use Modules\RecurringBilling\Http\Controllers\RecurringBillingController;

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

// Rotas operacionais (placeholder — a expandir nas próximas sub-ondas)
Route::group([], function () {
    Route::resource('recurringbilling', RecurringBillingController::class)->names('recurringbilling');
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
