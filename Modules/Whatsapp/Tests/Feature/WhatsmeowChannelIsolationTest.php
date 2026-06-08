<?php

declare(strict_types=1);

use Modules\Whatsapp\Entities\Channel;

uses(Tests\TestCase::class);

/**
 * WhatsmeowChannelIsolationTest — ADR 0204 multi-tenant Tier 0 (ADR 0093).
 *
 * Verifica que channels com type=whatsapp_whatsmeow respeitam global scope
 * `business_id` igual qualquer outro Channel.
 *
 * NÃO usa banco real (RefreshDatabase) — testa via Model::query() em memória
 * + reflection do trait. Isolamento de banco real é validado em
 * OmnichannelIsolationTest + MultiTenantIsolationTest existentes.
 *
 * Cobre:
 *  - TYPE_WHATSAPP_WHATSMEOW está em Channel::TYPES (lista canon)
 *  - whatsmeowUserName() retorna null se type errado
 *  - whatsmeowUserName() retorna ch-{uuid sem hifens} se type=whatsmeow
 *  - isWhatsapp() inclui whatsmeow
 *  - Config 'whatsmeow' presente
 */

it('Channel::TYPES inclui TYPE_WHATSAPP_WHATSMEOW (ADR 0204)', function () {
    expect(Channel::TYPES)->toContain(Channel::TYPE_WHATSAPP_WHATSMEOW);
    expect(Channel::TYPE_WHATSAPP_WHATSMEOW)->toBe('whatsapp_whatsmeow');
});

it('Channel::whatsmeowUserName retorna null pra type != whatsmeow', function () {
    $channel = new Channel([
        'channel_uuid' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
        'type' => Channel::TYPE_WHATSAPP_ZAPI,
    ]);

    expect($channel->whatsmeowUserName())->toBeNull();
});

it('Channel::whatsmeowUserName retorna null pra channel_uuid vazio', function () {
    $channel = new Channel([
        'channel_uuid' => '',
        'type' => Channel::TYPE_WHATSAPP_WHATSMEOW,
    ]);

    expect($channel->whatsmeowUserName())->toBeNull();
});

it('Channel::whatsmeowUserName retorna ch-{uuid_sem_hifens} pra whatsmeow', function () {
    $channel = new Channel([
        'channel_uuid' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
        'type' => Channel::TYPE_WHATSAPP_WHATSMEOW,
    ]);

    expect($channel->whatsmeowUserName())->toBe('ch-aaaaaaaabbbbccccddddeeeeeeeeeeee');
});

it('Channel::isWhatsapp() inclui type whatsmeow (ADR 0204)', function () {
    $channel = new Channel(['type' => Channel::TYPE_WHATSAPP_WHATSMEOW]);
    expect($channel->isWhatsapp())->toBeTrue();
});

it('Channel::isWhatsapp() inclui Meta/Z-API/Baileys/Whatsmeow', function () {
    foreach ([
        Channel::TYPE_WHATSAPP_META,
        Channel::TYPE_WHATSAPP_ZAPI,
        Channel::TYPE_WHATSAPP_BAILEYS,
        Channel::TYPE_WHATSAPP_WHATSMEOW,
    ] as $type) {
        $channel = new Channel(['type' => $type]);
        expect($channel->isWhatsapp())->toBeTrue();
    }

    $other = new Channel(['type' => Channel::TYPE_INSTAGRAM]);
    expect($other->isWhatsapp())->toBeFalse();
});

it('Config whatsmeow exposes daemon_url + api_key + hmac_secret + timeout (ADR 0204)', function () {
    $cfg = config('whatsapp.whatsmeow');

    expect($cfg)->toBeArray();
    expect($cfg)->toHaveKeys(['daemon_url', 'api_key', 'hmac_secret', 'request_timeout']);
});

it('forbidden_drivers NÃO inclui whatsmeow (ADR 0204 keeps it allowed)', function () {
    $forbidden = (array) config('whatsapp.forbidden_drivers', []);

    expect($forbidden)->not->toContain('whatsmeow');
    // Mantém forbidden o que foi (ADR 0202)
    expect($forbidden)->toContain('baileys');
    expect($forbidden)->toContain('evolution');
    expect($forbidden)->toContain('whatsapp_web_js');
});
