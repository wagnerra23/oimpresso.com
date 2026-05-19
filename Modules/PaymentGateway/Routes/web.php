<?php

use Illuminate\Support\Facades\Route;
use Modules\PaymentGateway\Http\Controllers\InstallController;
use Modules\PaymentGateway\Http\Controllers\Webhooks\AsaasWebhookController;
use Modules\PaymentGateway\Http\Controllers\Webhooks\BcbPixWebhookController;
use Modules\PaymentGateway\Http\Controllers\Webhooks\C6WebhookController;
use Modules\PaymentGateway\Http\Controllers\Webhooks\InterWebhookController;

/*
|--------------------------------------------------------------------------
| Web Routes — PaymentGateway
|--------------------------------------------------------------------------
|
| ADR 0170:
|   Onda 1 — Install routes (autenticadas, prefix paymentgateway/)
|   Onda 3 — Webhooks endpoints SEM auth (chamados externamente)
|            Rotas novas em /paymentgateway/webhooks/* paralelas às de RB.
|            Cutover real (DNS/proxy) fica pra Onda 3.5.
|
*/

// ─── Install (Onda 1) ────────────────────────────────────────────────────
Route::middleware(['web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu'])
    ->prefix('paymentgateway')
    ->group(function () {
        Route::get('install', [InstallController::class, 'index']);
        Route::get('install/uninstall', [InstallController::class, 'uninstall']);
        Route::get('install/update', [InstallController::class, 'update']);
    });

// ─── Webhooks (Onda 3) ───────────────────────────────────────────────────
// SEM 'auth'/'authh' — chamados externamente pelo gateway.
// HMAC validation chega na Onda 4 quando drivers reais existirem.
Route::middleware(['web'])
    ->prefix('paymentgateway/webhooks')
    ->group(function () {
        Route::post('inter/{businessId}', [InterWebhookController::class, 'handle'])
            ->whereNumber('businessId')
            ->name('paymentgateway.webhooks.inter');

        Route::post('c6/{businessId}', [C6WebhookController::class, 'handle'])
            ->whereNumber('businessId')
            ->name('paymentgateway.webhooks.c6');

        Route::post('asaas/{businessId}', [AsaasWebhookController::class, 'handle'])
            ->whereNumber('businessId')
            ->name('paymentgateway.webhooks.asaas');

        Route::post('bcb-pix/{businessId}', [BcbPixWebhookController::class, 'handle'])
            ->whereNumber('businessId')
            ->name('paymentgateway.webhooks.bcb-pix');
    });
