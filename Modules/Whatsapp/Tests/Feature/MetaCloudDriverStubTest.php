<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Modules\Whatsapp\Entities\WhatsappBusinessConfig;
use Modules\Whatsapp\Services\Drivers\MetaCloudDriver;

uses(Tests\TestCase::class);

/**
 * PR4 PoC — MetaCloudDriver stub canary biz sandbox.
 *
 * Cobre:
 *  - Outbound: sendFreeform via Http::fake (NÃO chama Meta real)
 *  - Outbound: erro HTTP 4xx mapeado pra WhatsappSendResult::failed (não joga)
 *  - Inbound: parseInboundWebhook extrai BSUID (shape mar/2026+)
 *  - Inbound: parseInboundWebhook tolera payload pré mar/2026 (bsuid=null)
 *
 * Trust L0 — testa que driver NUNCA chama Meta sem mock e que payload
 * inbound respeita schema 3-identifiers do PR1.
 *
 * @see Modules/Whatsapp/Services/Drivers/MetaCloudDriver.php
 * @see Modules/Whatsapp/Database/Migrations/2026_05_15_010000_add_identity_columns_to_conversations.php
 * @see memory/sessions/2026-05-15-estudo-whatsapp-protocol-vs-oimpresso.md §7 Opção A
 */

it('MetaCloudDriver::sendFreeform envia text via Http POST sem chamar API real (mock)', function () {
    Http::fake([
        'graph.facebook.com/v21.0/PHONE_ID_FAKE/messages' => Http::response([
            'messaging_product' => 'whatsapp',
            'messages' => [['id' => 'wamid.STUB']],
        ], 200),
    ]);

    $config = new WhatsappBusinessConfig([
        'business_id' => 99, // canary biz sandbox, NÃO biz=1 prod
        'business_uuid' => 'canary-uuid',
        'driver' => 'meta_cloud',
        'fallback_driver' => 'meta_cloud',
        'meta_phone_number_id' => 'PHONE_ID_FAKE',
        'meta_access_token' => 'TOKEN_FAKE',
    ]);

    $driver = app(MetaCloudDriver::class);
    $result = $driver->sendFreeform($config, '+5548999000000', 'Teste smoke canary');

    expect($result->ok)->toBeTrue();
    expect($result->providerMessageId)->toBe('wamid.STUB');

    Http::assertSent(fn ($request) => $request->url() === 'https://graph.facebook.com/v21.0/PHONE_ID_FAKE/messages'
        && $request->method() === 'POST'
        && $request['to'] === '5548999000000'
        && $request['type'] === 'text');
});

it('MetaCloudDriver::sendFreeform mapeia HTTP 401 pra WhatsappSendResult::failed sem joga', function () {
    Http::fake([
        'graph.facebook.com/v21.0/PHONE_ID_FAKE/messages' => Http::response([
            'error' => [
                'code' => 190,
                'message' => 'Invalid OAuth access token',
            ],
        ], 401),
    ]);

    $config = new WhatsappBusinessConfig([
        'business_id' => 99,
        'business_uuid' => 'canary-uuid',
        'driver' => 'meta_cloud',
        'fallback_driver' => 'meta_cloud',
        'meta_phone_number_id' => 'PHONE_ID_FAKE',
        'meta_access_token' => 'TOKEN_INVALID',
    ]);

    $result = app(MetaCloudDriver::class)->sendFreeform($config, '+5548999000000', 'Teste auth fail');

    expect($result->ok)->toBeFalse();
    expect($result->errorCode)->toBe('meta_190');
    expect($result->errorMessage)->toContain('Invalid OAuth');
});

it('MetaCloudDriver::parseInboundWebhook extrai BSUID do payload mar/2026+ (fixture)', function () {
    $path = __DIR__.'/../Fixtures/meta-cloud-inbound-with-bsuid.json';
    expect(file_exists($path))->toBeTrue('Fixture meta-cloud-inbound-with-bsuid.json ausente');

    $payload = json_decode(file_get_contents($path), true);
    expect($payload)->toBeArray();

    $driver = app(MetaCloudDriver::class);
    $msgs = $driver->parseInboundWebhook($payload);

    expect($msgs)->toHaveCount(1);

    $msg = $msgs[0];
    expect($msg['wa_id'])->toBe('5548999000000');
    expect($msg['phone_e164'])->toBe('+5548999000000');
    expect($msg['bsuid'])->toBe('abc123-bsuid-xyz'); // BSUID Meta-oficial 31-mar-2026+
    expect($msg['profile_name'])->toBe('Cliente Sandbox');
    expect($msg['message_id'])->toBe('wamid.HBgLNTU0ODk5OTAwMDAwFQIAEhggMTIzNDU2');
    expect($msg['type'])->toBe('text');
    expect($msg['body'])->toBe('Ola sandbox canary');
});

it('MetaCloudDriver::parseInboundWebhook tolera payload sem user_id (compat pre mar/2026)', function () {
    $payload = [
        'object' => 'whatsapp_business_account',
        'entry' => [[
            'id' => 'WABA_456',
            'changes' => [[
                'field' => 'messages',
                'value' => [
                    'messaging_product' => 'whatsapp',
                    'metadata' => ['phone_number_id' => 'PHONE_ID_FAKE'],
                    'contacts' => [[
                        'wa_id' => '5548999000000',
                        // SEM user_id (payload pré mar/2026)
                        'profile' => ['name' => 'Cliente Legacy'],
                    ]],
                    'messages' => [[
                        'from' => '5548999000000',
                        'id' => 'wamid.LEGACY',
                        'type' => 'text',
                        'text' => ['body' => 'oi mundo legacy'],
                    ]],
                ],
            ]],
        ]],
    ];

    $msgs = app(MetaCloudDriver::class)->parseInboundWebhook($payload);

    expect($msgs)->toHaveCount(1);
    expect($msgs[0]['bsuid'])->toBeNull(); // sem regressão — fica null, MessagePersister continua usando phone_e164
    expect($msgs[0]['phone_e164'])->toBe('+5548999000000');
    expect($msgs[0]['profile_name'])->toBe('Cliente Legacy');
    expect($msgs[0]['body'])->toBe('oi mundo legacy');
});
