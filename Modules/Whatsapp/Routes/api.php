<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Whatsapp — rotas API (webhooks Z-API e Meta Cloud)
|--------------------------------------------------------------------------
|
| Lote 2a: rotas declaradas mas controllers ainda não implementados.
| Lote 2c implementa MetaWebhookController + ZapiWebhookController + middlewares
| VerifyMetaSignature (HMAC SHA-256) + VerifyZapiSignature (Client-Token timing-safe).
|
| @see memory/requisitos/Whatsapp/SPEC.md US-WA-010, US-WA-010b
| @see memory/requisitos/Whatsapp/ARCHITECTURE.md §6 Middlewares
*/

// Webhooks ficarão aqui em Lote 2c:
// Route::post('/whatsapp/webhook/zapi/{business_uuid}', [ZapiWebhookController::class, 'handle'])
//     ->middleware('whatsapp.zapi.signature');
//
// Route::post('/whatsapp/webhook/meta/{business_uuid}', [MetaWebhookController::class, 'handle'])
//     ->middleware('whatsapp.meta.signature');
// Route::get('/whatsapp/webhook/meta/{business_uuid}', [MetaWebhookController::class, 'verify']);
