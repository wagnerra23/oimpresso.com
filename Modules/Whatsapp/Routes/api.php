<?php

use Illuminate\Support\Facades\Route;
use Modules\Whatsapp\Http\Controllers\Api\BaileysWebhookController;
use Modules\Whatsapp\Http\Controllers\Api\ChannelBaileysWebhookController;
use Modules\Whatsapp\Http\Controllers\Api\EmailEntradaController;
use Modules\Whatsapp\Http\Controllers\Api\MetaGraphWebhookController;
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

    // Baileys daemon Node próprio (Sprint 3 — ADR 0096 emenda 4)
    Route::post('/baileys/{business_uuid}', [BaileysWebhookController::class, 'handle'])
        ->middleware(['throttle:60,1', 'whatsapp.baileys.signature'])
        ->name('whatsapp.webhook.baileys.handle');

    // ADR 0204 (2026-05-27) — Whatsmeow Go daemon WuzAPI CT 100.
    // Substituto não-oficial Baileys. Mesma assinatura business_uuid no path
    // pra preservar multi-tenant Tier 0 (ADR 0093). Middleware
    // whatsapp.whatsmeow.signature valida HMAC global do daemon.
    Route::post('/whatsmeow/{business_uuid}', [WhatsmeowWebhookController::class, 'handle'])
        ->middleware(['throttle:60,1', 'whatsapp.whatsmeow.signature'])
        ->name('whatsapp.webhook.whatsmeow.handle');
});

// Omnichannel webhook receiver (ADR 0135) — endereçado por channel_uuid
// (em vez de business_uuid legacy). Daemon CT 100 deve apontar
// WEBHOOK_BASE_URL pra: https://oimpresso.com/api/atendimento/channels/baileys
//
// US-WA-082: middleware `whatsapp.baileys.hmac` valida HMAC + replay
// window 5min + nonce não-repetido. Backward compat: daemon antigo sem
// headers passa direto (rollout gradual). API_KEY config no .env.
Route::group(['prefix' => 'atendimento/channels'], function () {
    // Ordem dos middlewares:
    //   1. otel.propagate (US-WA-083) — extrai traceparent ANTES de tudo
    //      pra logs de hmac/backpressure já carregarem trace_id
    //   2. hmac (US-WA-082) — rejeita 401 cedo se assinatura inválida (cheap)
    //   3. backpressure (US-WA-084) — só conta queue depth se hmac passou
    //                                  (evita SELECT pra atacante)
    Route::post('/baileys/{channel_uuid}', [ChannelBaileysWebhookController::class, 'handle'])
        ->where('channel_uuid', '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}')
        ->middleware([
            'throttle:60,1', // D8.a Security — defesa flood antes mesmo de hmac/backpressure
            'whatsapp.otel.propagate',
            'whatsapp.baileys.hmac',
            'whatsapp.baileys.backpressure',
        ])
        ->name('atendimento.channels.baileys.webhook');
});

// US-WA-329 fatia 2 — Instagram Direct e Messenger (Meta Graph) na mesma Caixa Unificada.
// Endereçado por channel_uuid; assinatura X-Hub-Signature-256 validada no controller com o
// app secret do canal. Atrás de whatsapp.omnicanal.meta_graph_enabled (desligado = 404).
Route::group(['prefix' => 'atendimento/channels/meta-graph'], function () {
    $uuid = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';
    Route::get('/{channel_uuid}', [MetaGraphWebhookController::class, 'verify'])
        ->where('channel_uuid', $uuid)->middleware('throttle:60,1')
        ->name('atendimento.channels.meta_graph.verify');
    Route::post('/{channel_uuid}', [MetaGraphWebhookController::class, 'handle'])
        ->where('channel_uuid', $uuid)->middleware('throttle:120,1')
        ->name('atendimento.channels.meta_graph.webhook');
});

// US-WA-329 fatia 4 — e-mail encaminhado pelo provedor de entrada (formato Postmark Inbound).
// Token do canal no Basic Auth ou em X-Inbound-Token. Atrás de whatsapp.omnicanal.email_enabled.
Route::post('atendimento/channels/email/{channel_uuid}', [EmailEntradaController::class, 'handle'])
    ->where('channel_uuid', '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}')
    ->middleware('throttle:120,1')
    ->name('atendimento.channels.email.inbound');
