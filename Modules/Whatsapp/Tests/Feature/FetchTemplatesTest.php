<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Modules\Whatsapp\Entities\WhatsappBusinessConfig;
use Modules\Whatsapp\Services\Drivers\MetaCloudDriver;

uses(Tests\TestCase::class);

/**
 * US-WA-013 · MetaCloudDriver::fetchTemplates() — sync HSM Meta.
 *
 * Cobre:
 * - 2-step lookup: phone_number_id → WABA → templates
 * - Normalização de payload Meta pra formato comum
 * - Falha silenciosa retorna [] (não estoura)
 * - Filtra templates sem name/language
 */

it('faz 2-step lookup phone_number_id → WABA → templates e normaliza payload', function () {
    Http::fake([
        // Step 1: GET phone_number_id retorna whatsapp_business_account
        'graph.facebook.com/v21.0/PHONE_ID_123*' => Http::response([
            'id' => 'PHONE_ID_123',
            'whatsapp_business_account' => ['id' => 'WABA_456'],
        ], 200),
        // Step 2: GET WABA/message_templates retorna lista
        'graph.facebook.com/v21.0/WABA_456/message_templates*' => Http::response([
            'data' => [
                [
                    'id' => 'TPL_1',
                    'name' => 'repair_status_ready',
                    'language' => 'pt_BR',
                    'category' => 'utility',
                    'status' => 'approved',
                    'components' => [
                        ['type' => 'BODY', 'text' => 'Olá {{1}}, sua OS #{{2}} está pronta!'],
                    ],
                ],
                [
                    'id' => 'TPL_2',
                    'name' => 'billing_due_reminder',
                    'language' => 'pt_BR',
                    'category' => 'UTILITY',
                    'status' => 'PENDING',
                    'components' => [],
                ],
            ],
        ], 200),
    ]);

    $config = new WhatsappBusinessConfig([
        'business_id' => 1,
        'business_uuid' => 'test-uuid',
        'driver' => 'meta_cloud',
        'fallback_driver' => 'meta_cloud',
        'meta_phone_number_id' => 'PHONE_ID_123',
        'meta_access_token' => 'EAAB-test-token',
    ]);

    $driver = app(MetaCloudDriver::class);
    $items = $driver->fetchTemplates($config);

    expect($items)->toHaveCount(2);

    $first = $items[0];
    expect($first['meta_template_id'])->toBe('TPL_1');
    expect($first['name'])->toBe('repair_status_ready');
    expect($first['language'])->toBe('pt_BR');
    expect($first['category'])->toBe('UTILITY'); // strtoupper aplicado
    expect($first['status'])->toBe('APPROVED');
    expect($first['components'])->toHaveCount(1);
});

it('retorna [] se phone_number_id retorna 404', function () {
    Http::fake([
        'graph.facebook.com/v21.0/INVALID_ID*' => Http::response(['error' => 'not found'], 404),
    ]);

    $config = new WhatsappBusinessConfig([
        'business_id' => 1,
        'business_uuid' => 'test-uuid',
        'driver' => 'meta_cloud',
        'fallback_driver' => 'meta_cloud',
        'meta_phone_number_id' => 'INVALID_ID',
        'meta_access_token' => 'token',
    ]);

    $items = app(MetaCloudDriver::class)->fetchTemplates($config);
    expect($items)->toBe([]);
});

it('retorna [] se WABA não tem whatsapp_business_account no response', function () {
    Http::fake([
        'graph.facebook.com/v21.0/PHONE_X*' => Http::response([
            'id' => 'PHONE_X',
            // sem whatsapp_business_account
        ], 200),
    ]);

    $config = new WhatsappBusinessConfig([
        'business_id' => 1,
        'business_uuid' => 'test-uuid',
        'driver' => 'meta_cloud',
        'fallback_driver' => 'meta_cloud',
        'meta_phone_number_id' => 'PHONE_X',
        'meta_access_token' => 'token',
    ]);

    $items = app(MetaCloudDriver::class)->fetchTemplates($config);
    expect($items)->toBe([]);
});

it('retorna [] se WABA/message_templates falha', function () {
    Http::fake([
        'graph.facebook.com/v21.0/PHONE_Y*' => Http::response([
            'id' => 'PHONE_Y',
            'whatsapp_business_account' => ['id' => 'WABA_FAIL'],
        ], 200),
        'graph.facebook.com/v21.0/WABA_FAIL/message_templates*' => Http::response([], 500),
    ]);

    $config = new WhatsappBusinessConfig([
        'business_id' => 1,
        'business_uuid' => 'test-uuid',
        'driver' => 'meta_cloud',
        'fallback_driver' => 'meta_cloud',
        'meta_phone_number_id' => 'PHONE_Y',
        'meta_access_token' => 'token',
    ]);

    $items = app(MetaCloudDriver::class)->fetchTemplates($config);
    expect($items)->toBe([]);
});
