<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Services\Drivers\WhatsmeowDriver;

uses(Tests\TestCase::class);

/**
 * WhatsmeowSubscribeLoggedOutTest — Fase B (incidente 2026-06-18 / POC WAHA-GOWS Phase 2).
 *
 * O WuzAPI RECEBE o evento `LoggedOut` ("logged out from another device") mas só
 * repassa via webhook se o user assinar o tipo — provado nos logs do daemon de prod:
 *   INFO  Logged out  reason="401: logged out from another device"
 *   WARN  Skipping webhook. Not subscribed for this type=LoggedOut
 *         subscribedEvents=["Message","ReadReceipt","Connected","Disconnected"]
 *
 * Sem assinar, um logout remoto nunca chega ao app → channel_health fica `healthy`
 * eternamente (raiz do falso "fora do ar", ADR 0286). Estes testes travam a
 * assinatura de `LoggedOut` nos dois pontos: provisionamento (/admin/users) e
 * conexão (/session/connect Subscribe). O app já roteia o webhook `LoggedOut`
 * → handleDisconnected (WhatsmeowWebhookController).
 *
 * Trust L0 — NUNCA chama daemon real (Http::fake guard).
 */
beforeEach(function () {
    config([
        'whatsapp.whatsmeow.daemon_url' => 'https://whatsapp-whatsmeow.oimpresso.com',
        'whatsapp.whatsmeow.api_key' => 'admin_token_fake_32_hex',
        'whatsapp.whatsmeow.request_timeout' => 5,
        'app.url' => 'https://oimpresso.com',
    ]);
});

function loChannelStub(array $overrides = []): Channel
{
    $channel = new Channel(array_merge([
        'id' => 1,
        'business_id' => 99,
        'channel_uuid' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
        'type' => Channel::TYPE_WHATSAPP_WHATSMEOW,
        'status' => 'setup',
        'config_json' => [],
    ], $overrides));
    $channel->exists = true;

    return $channel;
}

it('provisionSession assina LoggedOut no /admin/users (fecha o gap do logout remoto)', function () {
    Http::fake([
        '*/admin/users' => Http::response(['code' => 200, 'success' => true], 200),
    ]);

    app(WhatsmeowDriver::class)->provisionSession(loChannelStub(), 'biz-uuid-1234');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/admin/users')
            && str_contains($request->body(), 'LoggedOut');
    });
});

it('connect assina LoggedOut no Subscribe do /session/connect', function () {
    Http::fake([
        '*/session/status' => Http::response(['data' => ['connected' => false, 'loggedIn' => false]], 200),
        '*/session/connect' => Http::response(['code' => 200, 'success' => true], 200),
        '*/session/qr' => Http::response(['data' => ['QRCode' => 'data:image/png;base64,AAAA']], 200),
    ]);

    $channel = loChannelStub(['config_json' => ['whatsmeow_user_token' => 'user_token_xyz']]);
    app(WhatsmeowDriver::class)->connect($channel);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/session/connect')
            && str_contains($request->body(), 'LoggedOut');
    });
});
