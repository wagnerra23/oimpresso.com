<?php

use Illuminate\Support\Facades\Route;
use Modules\Whatsapp\Http\Controllers\Api\MetaWebhookController;
use Modules\Whatsapp\Http\Controllers\Api\WhatsmeowWebhookController;
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

// D8.a Security — throttle:60,1 (60 req/min) em webhooks externos públicos.
// HMAC + signature já protegem autenticidade; throttle adiciona defesa contra
// flood/replay storm caso provider externo (Meta/Z-API/Baileys daemon) entre em loop.
Route::group(['prefix' => 'whatsapp/webhook'], function () {
    // Meta Cloud — GET é challenge (verify_token); POST é evento real
    Route::get('/meta/{business_uuid}', [MetaWebhookController::class, 'verify'])
        ->middleware(['throttle:60,1', 'whatsapp.meta.signature'])
        ->name('whatsapp.webhook.meta.verify');

    Route::post('/meta/{business_uuid}', [MetaWebhookController::class, 'handle'])
        ->middleware(['throttle:60,1', 'whatsapp.meta.signature'])
        ->name('whatsapp.webhook.meta.handle');

    // Z-API — só POST (sem GET challenge; auth via Client-Token header)
    Route::post('/zapi/{business_uuid}', [ZapiWebhookController::class, 'handle'])
        ->middleware(['throttle:60,1', 'whatsapp.zapi.signature'])
        ->name('whatsapp.webhook.zapi.handle');

    // Rota Baileys `/baileys/{business_uuid}` REMOVIDA 2026-11-05 (auditoria
    // Whatsapp, lote 0; ADR 0202 confirmada por [W]): o middleware dela
    // (`whatsapp.baileys.signature`) já não existia — a rota respondia 500.

    // ADR 0204 (2026-05-27) — Whatsmeow Go daemon WuzAPI CT 100.
    // Substituto não-oficial Baileys. Mesma assinatura business_uuid no path
    // pra preservar multi-tenant Tier 0 (ADR 0093). Middleware
    // whatsapp.whatsmeow.signature valida HMAC global do daemon.
    Route::post('/whatsmeow/{business_uuid}', [WhatsmeowWebhookController::class, 'handle'])
        ->middleware(['throttle:60,1', 'whatsapp.whatsmeow.signature'])
        ->name('whatsapp.webhook.whatsmeow.handle');
});

// Omnichannel webhook Baileys `/atendimento/channels/baileys/{channel_uuid}`
// REMOVIDO 2026-11-05 (auditoria Whatsapp, lote 0; ADR 0202 confirmada por [W]):
// o daemon Baileys foi descomissionado e a rota aceitava requisição sem
// autenticação. O ChannelBaileysWebhookController continua no código porque o
// PersistHistorySyncBatchJob o usa como serviço; o middleware
// `whatsapp.baileys.hmac` segue registrado e fail-closed.
