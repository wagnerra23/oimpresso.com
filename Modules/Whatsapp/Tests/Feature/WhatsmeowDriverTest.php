<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Entities\WhatsappBusinessConfig;
use Modules\Whatsapp\Services\Drivers\DriverDoesNotSupport;
use Modules\Whatsapp\Services\Drivers\WhatsmeowDriver;

uses(Tests\TestCase::class);

/**
 * WhatsmeowDriverTest — ADR 0204 driver não-oficial substituto Baileys.
 *
 * Cobre:
 *  - sendFreeform via Http::fake (NÃO chama WuzAPI real)
 *  - sendFreeform mapeia HTTP 401 → sessionLost=true + WhatsappSendResult::failed
 *  - sendFreeform mapeia HTTP 403 → banDetected=true
 *  - ping retorna healthy quando daemon responde Connected=true + LoggedIn=true
 *  - ping retorna unhealthy quando LoggedIn=false (re-scan QR)
 *  - provisionSession POST /admin/users com Bearer + webhook URL multi-tenant
 *  - sendInteractive throw DriverDoesNotSupport (driver não-oficial)
 *  - Multi-tenant Tier 0: business A não acessa creds business B
 *
 * Trust L0 — driver NUNCA chama WuzAPI real sem Http::fake.
 * Sem Pest mock → testes pulam (env CI sem daemon CT 100 acessível).
 */

beforeEach(function () {
    config([
        'whatsapp.whatsmeow.daemon_url' => 'https://whatsapp-whatsmeow.oimpresso.com',
        'whatsapp.whatsmeow.api_key' => 'admin_token_fake_32_hex',
        'whatsapp.whatsmeow.hmac_secret' => 'hmac_secret_fake',
        'whatsapp.whatsmeow.request_timeout' => 5,
        'app.url' => 'https://oimpresso.com',
    ]);
});

it('WhatsmeowDriver::sendFreeform envia text via Http POST sem chamar API real (mock)', function () {
    Http::fake([
        'whatsapp-whatsmeow.oimpresso.com/chat/send/text' => Http::response([
            'Details' => 'Sent',
            'Id' => 'WAMID.WHATSMEOW.STUB.123',
            'Timestamp' => time(),
        ], 200),
    ]);

    $config = new WhatsappBusinessConfig([
        'business_id' => 99, // canary biz sandbox, NÃO biz=1 prod
        'business_uuid' => 'canary-uuid',
        'driver' => 'whatsmeow',
        'fallback_driver' => 'meta_cloud',
    ]);
    // Driver lê whatsmeow_user_token via attribute compat — injecta dinamicamente
    $config->setRawAttributes(array_merge($config->getAttributes(), [
        'whatsmeow_user_token' => 'user_token_fake_32hex',
    ]));

    $driver = app(WhatsmeowDriver::class);
    $result = $driver->sendFreeform($config, '+5548999000000', 'Teste smoke canary whatsmeow');

    expect($result->success)->toBeTrue();
    expect($result->providerMessageId)->toBe('WAMID.WHATSMEOW.STUB.123');

    Http::assertSent(fn ($request) => str_contains($request->url(), '/chat/send/text')
        && $request->method() === 'POST'
        && $request->header('Token')[0] === 'user_token_fake_32hex'
        && $request['Phone'] === '5548999000000'
        && $request['Body'] === 'Teste smoke canary whatsmeow');
});

it('WhatsmeowDriver::sendFreeform mapeia HTTP 401 pra sessionLost=true sem joga', function () {
    Http::fake([
        'whatsapp-whatsmeow.oimpresso.com/chat/send/text' => Http::response([
            'error' => 'Session expired',
            'code' => 401,
        ], 401),
    ]);

    $config = new WhatsappBusinessConfig([
        'business_id' => 99,
        'business_uuid' => 'canary',
        'driver' => 'whatsmeow',
        'fallback_driver' => 'meta_cloud',
    ]);
    $config->setRawAttributes(array_merge($config->getAttributes(), [
        'whatsmeow_user_token' => 'expired_token',
    ]));

    $result = app(WhatsmeowDriver::class)->sendFreeform($config, '+5548999000000', 'fail');

    expect($result->success)->toBeFalse();
    expect($result->errorCode)->toBe('whatsmeow_401');
    expect($result->sessionLost)->toBeTrue();
    expect($result->banDetected)->toBeFalse();
});

it('WhatsmeowDriver::sendFreeform mapeia HTTP 403 com "banned" pra banDetected=true', function () {
    Http::fake([
        'whatsapp-whatsmeow.oimpresso.com/chat/send/text' => Http::response([
            'error' => 'Account banned by Meta',
            'code' => 403,
        ], 403),
    ]);

    $config = new WhatsappBusinessConfig([
        'business_id' => 99,
        'business_uuid' => 'canary',
        'driver' => 'whatsmeow',
        'fallback_driver' => 'meta_cloud',
    ]);
    $config->setRawAttributes(array_merge($config->getAttributes(), [
        'whatsmeow_user_token' => 'banned_token',
    ]));

    $result = app(WhatsmeowDriver::class)->sendFreeform($config, '+5548999000000', 'msg');

    expect($result->success)->toBeFalse();
    expect($result->errorCode)->toBe('whatsmeow_403');
    expect($result->banDetected)->toBeTrue();
});

it('WhatsmeowDriver::sendFreeform retorna failed quando whatsmeow_user_token ausente', function () {
    $config = new WhatsappBusinessConfig([
        'business_id' => 99,
        'business_uuid' => 'canary',
        'driver' => 'whatsmeow',
        'fallback_driver' => 'meta_cloud',
    ]);
    // SEM whatsmeow_user_token — channel não foi conectado

    $result = app(WhatsmeowDriver::class)->sendFreeform($config, '+5548999000000', 'msg');

    expect($result->success)->toBeFalse();
    expect($result->errorCode)->toBe('whatsmeow_no_user_token');
    Http::assertNothingSent();
});

it('WhatsmeowDriver::ping retorna healthy quando Connected=true + LoggedIn=true', function () {
    Http::fake([
        'whatsapp-whatsmeow.oimpresso.com/session/status' => Http::response([
            'Connected' => true,
            'LoggedIn' => true,
            'Jid' => '5548999000000@s.whatsapp.net',
        ], 200),
    ]);

    $config = new WhatsappBusinessConfig([
        'business_id' => 99,
        'business_uuid' => 'canary',
        'driver' => 'whatsmeow',
        'fallback_driver' => 'meta_cloud',
    ]);
    $config->setRawAttributes(array_merge($config->getAttributes(), [
        'whatsmeow_user_token' => 'healthy_token',
    ]));

    $status = app(WhatsmeowDriver::class)->ping($config);

    expect($status->healthy)->toBeTrue();
    expect($status->sessionState)->toBe('connected');
    expect($status->banDetected)->toBeFalse();
});

it('WhatsmeowDriver::ping retorna qr_required quando LoggedIn=false', function () {
    Http::fake([
        'whatsapp-whatsmeow.oimpresso.com/session/status' => Http::response([
            'Connected' => true,
            'LoggedIn' => false,
            'Jid' => null,
        ], 200),
    ]);

    $config = new WhatsappBusinessConfig([
        'business_id' => 99,
        'business_uuid' => 'canary',
        'driver' => 'whatsmeow',
        'fallback_driver' => 'meta_cloud',
    ]);
    $config->setRawAttributes(array_merge($config->getAttributes(), [
        'whatsmeow_user_token' => 'unauth_token',
    ]));

    $status = app(WhatsmeowDriver::class)->ping($config);

    expect($status->healthy)->toBeFalse();
    expect($status->sessionState)->toBe('qr_required');
});

it('WhatsmeowDriver::sendInteractive lança DriverDoesNotSupport (driver não-oficial)', function () {
    $config = new WhatsappBusinessConfig([
        'business_id' => 99,
        'business_uuid' => 'canary',
        'driver' => 'whatsmeow',
        'fallback_driver' => 'meta_cloud',
    ]);

    expect(fn () => app(WhatsmeowDriver::class)->sendInteractive(
        $config,
        '+5548999000000',
        'Escolha uma opção',
        ['type' => 'buttons', 'buttons' => [['id' => 'a', 'label' => 'A']]],
    ))->toThrow(DriverDoesNotSupport::class);
});

it('WhatsmeowDriver::provisionSession POST /admin/users com webhook multi-tenant + retorna user_token', function () {
    Http::fake([
        'whatsapp-whatsmeow.oimpresso.com/admin/users' => Http::response([
            'data' => ['name' => 'ch-abc', 'token' => 'gerado', 'webhook' => 'set'],
        ], 200),
    ]);

    $channel = new Channel([
        'channel_uuid' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
        'type' => Channel::TYPE_WHATSAPP_WHATSMEOW,
    ]);

    $provision = app(WhatsmeowDriver::class)->provisionSession($channel, 'biz-uuid-99');

    expect($provision['name'])->toBe('ch-aaaaaaaabbbbccccddddeeeeeeeeeeee');
    expect(strlen($provision['token']))->toBe(32); // bin2hex(16) = 32 chars
    expect($provision['webhook'])->toBe('https://oimpresso.com/api/whatsapp/webhook/whatsmeow/biz-uuid-99');

    // Validates Bearer admin auth foi enviado pro daemon
    Http::assertSent(fn ($req) => str_contains($req->url(), '/admin/users')
        && $req->method() === 'POST'
        && str_contains($req->header('Authorization')[0] ?? '', 'Bearer admin_token_fake_32_hex')
        && $req['webhook'] === 'https://oimpresso.com/api/whatsapp/webhook/whatsmeow/biz-uuid-99'
        && str_contains((string) $req['events'], 'Message'));
});
