<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
    |--------------------------------------------------------------------------
    | API Routes
    |--------------------------------------------------------------------------
    |
    | Here is where you can register API routes for your application. These
    | routes are loaded by the RouteServiceProvider within a group which
    | is assigned the "api" middleware group. Enjoy building your API!
    |
*/

Route::middleware(['auth:sanctum'])->prefix('v1')->name('api.')->group(function () {
    Route::get('recurringbilling', fn (Request $request) => $request->user())->name('recurringbilling');
});

// Webhook Asaas — sem auth (chamado pelo Asaas externamente).
// D8.a Security — throttle:60,1 (60 req/min). Shared secret no header valida
// autenticidade; throttle defende contra burst/replay storm do gateway.
Route::post(
    'webhooks/asaas/{businessId}',
    [\Modules\RecurringBilling\Http\Controllers\AsaasWebhookController::class, 'handle']
)->middleware('throttle:60,1')->name('webhooks.asaas');
