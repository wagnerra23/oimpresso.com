<?php

// Wave 10 D8 Security: throttle:200,1 nos webhooks Woocommerce.
// Webhooks recebem rajadas legítimas (vários /order /product events por minuto numa loja ativa)
// mas precisam de teto pra impedir flood/abuse. 200 req/min/IP é alto o suficiente
// pra sincronização normal e baixo o suficiente pra bloquear ataque.
//
// IMPORTANTE: signature HMAC SHA256 (header x-wc-webhook-signature) continua validada
// dentro do Controller (WoocommerceWebhookController::isValidWebhookRequest). Throttle
// é primeira linha — signature é segunda linha (zero confiança em payload).
Route::middleware(['throttle:200,1'])->group(function () {
    Route::post(
        '/webhook/order-created/{business_id}',
        [\Modules\Woocommerce\Http\Controllers\WoocommerceWebhookController::class, 'orderCreated']
    );
    Route::post(
        '/webhook/order-updated/{business_id}',
        [\Modules\Woocommerce\Http\Controllers\WoocommerceWebhookController::class, 'orderUpdated']
    );
    Route::post(
        '/webhook/order-deleted/{business_id}',
        [\Modules\Woocommerce\Http\Controllers\WoocommerceWebhookController::class, 'orderDeleted']
    );
    Route::post(
        '/webhook/order-restored/{business_id}',
        [\Modules\Woocommerce\Http\Controllers\WoocommerceWebhookController::class, 'orderRestored']
    );
});

// Wave 10 D8 Security: throttle:60,1 nas rotas admin Woocommerce.
// Stack middleware UltimatePOS herdado — usuário admin autenticado.
Route::middleware(['web', 'SetSessionData', 'auth', 'language', 'timezone', 'AdminSidebarMenu', 'throttle:60,1'])->prefix('woocommerce')->group(function () {
    Route::get('/install', [\Modules\Woocommerce\Http\Controllers\InstallController::class, 'index']);
    Route::get('/install/update', [\Modules\Woocommerce\Http\Controllers\InstallController::class, 'update']);
    Route::get('/install/uninstall', [\Modules\Woocommerce\Http\Controllers\InstallController::class, 'uninstall']);

    Route::get('/', [\Modules\Woocommerce\Http\Controllers\WoocommerceController::class, 'index']);
    Route::get('/api-settings', [\Modules\Woocommerce\Http\Controllers\WoocommerceController::class, 'apiSettings']);
    Route::post('/update-api-settings', [\Modules\Woocommerce\Http\Controllers\WoocommerceController::class, 'updateSettings']);
    Route::get('/sync-categories', [\Modules\Woocommerce\Http\Controllers\WoocommerceController::class, 'syncCategories']);
    Route::get('/sync-products', [\Modules\Woocommerce\Http\Controllers\WoocommerceController::class, 'syncProducts']);
    Route::get('/sync-log', [\Modules\Woocommerce\Http\Controllers\WoocommerceController::class, 'getSyncLog']);
    Route::get('/sync-orders', [\Modules\Woocommerce\Http\Controllers\WoocommerceController::class, 'syncOrders']);
    Route::post('/map-taxrates', [\Modules\Woocommerce\Http\Controllers\WoocommerceController::class, 'mapTaxRates']);
    Route::get('/view-sync-log', [\Modules\Woocommerce\Http\Controllers\WoocommerceController::class, 'viewSyncLog']);
    Route::get('/get-log-details/{id}', [\Modules\Woocommerce\Http\Controllers\WoocommerceController::class, 'getLogDetails']);
    Route::get('/reset-categories', [\Modules\Woocommerce\Http\Controllers\WoocommerceController::class, 'resetCategories']);
    Route::get('/reset-products', [\Modules\Woocommerce\Http\Controllers\WoocommerceController::class, 'resetProducts']);
});
