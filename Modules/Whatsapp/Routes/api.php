<?php

use Illuminate\Support\Facades\Route;
use Modules\Whatsapp\Http\Controllers\Api\BaileysWebhookController;
use Modules\Whatsapp\Http\Controllers\Api\ChannelBaileysWebhookController;
use Modules\Whatsapp\Http\Controllers\Api\MetaWebhookController;
use Modules\Whatsapp\Http\Controllers\Api\ZapiWebhookController;

/*
|--------------------------------------------------------------------------
| Whatsapp — rotas API (webhooks Z-API e Meta Cloud)
|--------------------------------------------------------------------------
|
| Decisão arquitetural mãe: ADR 0096
|   - 2 receivers: /webhook/meta/{uuid} + /webhook/zapi/{uuid}
|   - Sprint 3: + /webhook/baileys/{uuid} (BaileysDriver custom)
|
| Webhooks SÃO PÚBLICOS (sem auth Sanctum) — autenticação é feita pelo
| middleware de verificação de assinatura (HMAC SHA-256 / Client-Token /
| api_key). Resposta sempre 200 (Meta retenta agressivo se ≠200).
|
| @see memory/requisitos/Whatsapp/SPEC.md US-WA-010 / US-WA-010b
| @see memory/requisitos/Whatsapp/ARCHITECTURE.md §6 Middlewares
*/

Route::group(['prefix' => 'whatsapp/webhook'], function () {
    // Meta Cloud — GET é challenge (verify_token); POST é evento real
    Route::get('/meta/{business_uuid}', [MetaWebhookController::class, 'verify'])
        ->middleware('whatsapp.meta.signature')
        ->name('whatsapp.webhook.meta.verify');

    Route::post('/meta/{business_uuid}', [MetaWebhookController::class, 'handle'])
        ->middleware('whatsapp.meta.signature')
        ->name('whatsapp.webhook.meta.handle');

    // Z-API — só POST (sem GET challenge; auth via Client-Token header)
    Route::post('/zapi/{business_uuid}', [ZapiWebhookController::class, 'handle'])
        ->middleware('whatsapp.zapi.signature')
        ->name('whatsapp.webhook.zapi.handle');

    // Baileys daemon Node próprio (Sprint 3 — ADR 0096 emenda 4)
    Route::post('/baileys/{business_uuid}', [BaileysWebhookController::class, 'handle'])
        ->middleware('whatsapp.baileys.signature')
        ->name('whatsapp.webhook.baileys.handle');
});

// Omnichannel webhook receiver (ADR 0135) — endereçado por channel_uuid
// (em vez de business_uuid legacy). Daemon CT 100 deve apontar
// WEBHOOK_BASE_URL pra: https://oimpresso.com/api/atendimento/channels/baileys
//
// US-WA-082: middleware `whatsapp.baileys.hmac` valida HMAC + replay
// window 5min + nonce não-repetido. Backward compat: daemon antigo sem
// headers passa direto (rollout gradual). API_KEY config no .env.
Route::group(['prefix' => 'atendimento/channels'], function () {
    Route::post('/baileys/{channel_uuid}', [ChannelBaileysWebhookController::class, 'handle'])
        ->where('channel_uuid', '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}')
        ->middleware('whatsapp.baileys.hmac')
        ->name('atendimento.channels.baileys.webhook');
});
