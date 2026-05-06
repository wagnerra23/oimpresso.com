<?php

use Illuminate\Support\Facades\Route;
use Modules\RecurringBilling\Http\Controllers\InstallController;
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
