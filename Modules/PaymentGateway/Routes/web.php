<?php

use Illuminate\Support\Facades\Route;
use Modules\PaymentGateway\Http\Controllers\InstallController;
use Modules\PaymentGateway\Http\Controllers\Settings\PaymentGatewaysCnabRetornoController;
use Modules\PaymentGateway\Http\Controllers\Settings\PaymentGatewaysController;
use Modules\PaymentGateway\Http\Controllers\Webhooks\AsaasWebhookController;
use Modules\PaymentGateway\Http\Controllers\Webhooks\BcbPixWebhookController;
use Modules\PaymentGateway\Http\Controllers\Webhooks\C6WebhookController;
use Modules\PaymentGateway\Http\Controllers\Webhooks\InterPixWebhookController;
use Modules\PaymentGateway\Http\Controllers\Webhooks\InterWebhookController;
use Modules\PaymentGateway\Http\Controllers\Webhooks\PagarmeWebhookController;
use Modules\PaymentGateway\Http\Controllers\Webhooks\SicoobApiWebhookController;

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

// ─── Settings UI (Onda 4d.3 F3 Tela 2) ───────────────────────────────────
// Persona Wagner / superadmin / owner. Stack canon UPOS auth.
// Charter: resources/js/Pages/Settings/PaymentGateways/Index.charter.md
Route::middleware(['web', 'auth', 'language', 'timezone', 'AdminSidebarMenu'])
    ->prefix('settings')
    ->name('settings.')
    ->group(function () {
        Route::get('payment-gateways', [PaymentGatewaysController::class, 'index'])
            ->name('payment-gateways.index');

        // Onda 5 (2026-05-19) — completa wizard SheetNovoGateway (UI F3 PR #1135).
        Route::post('payment-gateways', [PaymentGatewaysController::class, 'store'])
            ->name('payment-gateways.store');

        Route::put('payment-gateways/{credentialId}', [PaymentGatewaysController::class, 'update'])
            ->whereNumber('credentialId')
            ->name('payment-gateways.update');

        Route::delete('payment-gateways/{credentialId}', [PaymentGatewaysController::class, 'destroy'])
            ->whereNumber('credentialId')
            ->name('payment-gateways.destroy');

        Route::post('payment-gateways/health-check', [PaymentGatewaysController::class, 'healthCheck'])
            ->name('payment-gateways.health-check.all');

        Route::post('payment-gateways/{credentialId}/health-check', [PaymentGatewaysController::class, 'healthCheck'])
            ->whereNumber('credentialId')
            ->name('payment-gateways.health-check');

        Route::post('payment-gateways/{credentialId}/toggle', [PaymentGatewaysController::class, 'toggle'])
            ->whereNumber('credentialId')
            ->name('payment-gateways.toggle');

        // Onda 4e.UI (gap P0 estado-da-arte 2026-05-23): audit trail per credential.
        Route::get('payment-gateways/{credentialId}/history', [PaymentGatewaysController::class, 'history'])
            ->whereNumber('credentialId')
            ->name('payment-gateways.history');

        // Onda 4e.UI #2 (gap P0 estado-da-arte 2026-05-23): webhook events per credential.
        Route::get('payment-gateways/{credentialId}/webhook-events', [PaymentGatewaysController::class, 'webhookEvents'])
            ->whereNumber('credentialId')
            ->name('payment-gateways.webhook-events');

        // Onda 4e gap #3 (audit 2026-05-23): quota tracking MVP — count
        // cobrancas/mês per credencial agrupado por tipo. Sem contador persistido.
        Route::get('payment-gateways/{credentialId}/quota', [PaymentGatewaysController::class, 'quota'])
            ->whereNumber('credentialId')
            ->name('payment-gateways.quota');

        // Onda 4f.0 — Fundação CNAB compartilhada (ADR 0170-drivers-separados).
        // Upload manual de arquivo retorno CNAB (240/400) → Job CnabRetornoProcessor.
        // Tela: histórico de uploads + form upload.
        Route::get('payment-gateways/{credentialId}/cnab-retorno', [PaymentGatewaysCnabRetornoController::class, 'index'])
            ->whereNumber('credentialId')
            ->name('payment-gateways.cnab-retorno.index');

        Route::post('payment-gateways/{credentialId}/cnab-retorno', [PaymentGatewaysCnabRetornoController::class, 'store'])
            ->whereNumber('credentialId')
            ->name('payment-gateways.cnab-retorno.store');
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

        // Onda 4e — driver Pagar.me v5 (Stone group)
        // HMAC signature X-Hub-Signature-256 validada no controller.
        Route::post('pagarme/{businessId}', [PagarmeWebhookController::class, 'handle'])
            ->whereNumber('businessId')
            ->name('paymentgateway.webhooks.pagarme');

        // Onda 4f.sicoob_api — US-FIN-044 PR4. HMAC `x-sicoob-signature`
        // raw body. Eventos cobranca.liquidada/vencida/cancelada.
        Route::post('sicoob-api/{businessId}', [SicoobApiWebhookController::class, 'handle'])
            ->whereNumber('businessId')
            ->name('paymentgateway.webhooks.sicoob-api');
    });

// ─── Webhook PIX Inter US-FIN-032 (Onda 26) ──────────────────────────────
// Endpoint dedicado /webhooks/inter/{credentialId} com HMAC signature
// obrigatória (header x-inter-signature) + idempotência por (cred_id, txid)
// + Job worker enfileirado pra resolver cobranca → titulo.
// SEM auth — chamado pelo Inter externamente.
Route::middleware(['web'])
    ->group(function () {
        Route::post('webhooks/inter/{credentialId}', [InterPixWebhookController::class, 'handle'])
            ->whereNumber('credentialId')
            ->name('paymentgateway.webhooks.inter.pix');
    });
