<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\WhatsappBusinessPhone;
use Modules\Whatsapp\Entities\WhatsappConversation;
use Modules\Whatsapp\Entities\WhatsappMessage;
use Modules\Whatsapp\Jobs\ProcessIncomingWebhookJob;

uses(Tests\TestCase::class);

/**
 * PR 2c US-WA-040 — webhook payloads resolvem phone correto e gravam
 * `whatsapp_business_phone_id` em conversation/message inbound.
 *
 * Cobre comportamento direto do `ProcessIncomingWebhookJob` quando
 * recebe `$phoneId` opcional setado (caso normal pós-PR 2c) e quando
 * recebe NULL (legacy/coexistência durante PR 1 → PR 5).
 *
 * Os middlewares (`VerifyMetaSignature`, `VerifyZapiSignature`,
 * `VerifyBaileysSignature`) extraem phone via metadata do payload
 * (`phone_number_id`, `z-api-token`, `instance_id`) e injetam pra
 * controller passar adiante. Esta cobertura indireta valida o contrato
 * completo (controller → job → DB).
 */

beforeEach(function () {
    // era-sqlite: este teste cria schema manual (sqlite-friendly). No MySQL persistente
    // do nightly isso DROPA tabelas reais → corrompe os testes irmãos (lever do floor SDD).
    // Cobertura real é na lane sqlite (per-PR); pula no MySQL.
    if (config('database.default') !== 'sqlite') {
        $this->markTestSkipped('era-sqlite: corruptor de schema compartilhado no MySQL — sqlite-only no burn-down do floor SDD.');
    }
    foreach (['whatsapp_messages', 'whatsapp_conversations', 'whatsapp_business_phones', 'whatsapp_business_configs'] as $t) {
        Schema::dropIfExists($t);
    }

    Schema::create('whatsapp_business_configs', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->uuid('business_uuid')->unique();
        $table->string('driver', 20)->default('zapi');
        $table->string('fallback_driver', 20)->default('meta_cloud');
        $table->string('display_phone', 20)->nullable();
        $table->timestamps();
    });

    Schema::create('whatsapp_business_phones', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->uuid('phone_uuid')->unique();
        $table->string('label', 80);
        $table->string('driver', 20)->default('zapi');
        $table->string('fallback_driver', 20)->default('meta_cloud');
        $table->string('display_phone', 20)->nullable();
        $table->string('meta_phone_number_id', 64)->nullable();
        $table->text('meta_access_token')->nullable();
        $table->text('meta_app_secret')->nullable();
        $table->string('meta_webhook_verify_token', 64)->nullable();
        $table->string('zapi_instance_id', 64)->nullable();
        $table->text('zapi_instance_token')->nullable();
        $table->text('zapi_client_token')->nullable();
        $table->string('baileys_instance_id', 64)->nullable();
        $table->string('baileys_phone_e164', 20)->nullable();
        $table->string('baileys_verified_name', 100)->nullable();
        $table->string('baileys_profile_pic_url', 255)->nullable();
        $table->timestamp('lgpd_acknowledged_at')->nullable();
        $table->unsignedInteger('lgpd_acknowledged_by_user_id')->nullable();
        $table->boolean('handles_repair_status')->default(false);
        $table->boolean('handles_billing')->default(false);
        $table->boolean('handles_jana_bot')->default(true);
        $table->boolean('handles_outbound_default')->default(false);
        $table->boolean('bot_enabled')->default(false);
        $table->string('template_repair_ready_name', 64)->nullable();
        $table->string('template_repair_waiting_parts_name', 64)->nullable();
        $table->string('template_billing_due_name', 64)->nullable();
        $table->string('template_billing_paid_name', 64)->nullable();
        $table->string('driver_health', 20)->default('never_checked');
        $table->unsignedInteger('driver_health_consecutive_failures')->default(0);
        $table->timestamp('last_health_check_at')->nullable();
        $table->text('last_health_message')->nullable();
        $table->timestamps();
    });

    Schema::create('whatsapp_conversations', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->unsignedBigInteger('whatsapp_business_phone_id')->nullable();
        $table->unsignedInteger('contact_id')->nullable();
        $table->string('customer_phone', 20);
        $table->string('status', 20)->default('open');
        $table->timestamp('last_inbound_at')->nullable();
        $table->timestamp('last_message_at')->nullable();
        $table->unsignedInteger('unread_count')->default(0);
        $table->timestamps();
    });

    Schema::create('whatsapp_messages', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->unsignedBigInteger('whatsapp_business_phone_id')->nullable();
        $table->unsignedBigInteger('conversation_id');
        $table->string('direction', 10);
        $table->string('provider', 20);
        $table->string('provider_message_id', 128)->nullable()->unique();
        $table->string('type', 20)->default('text');
        $table->text('body')->nullable();
        $table->json('payload')->nullable();
        $table->string('status', 20);
        $table->timestamp('created_at')->useCurrent();
        // WhatsappMessage tem timestamps on (cast updated_at) → create() insere updated_at.
        // O schema sintético precisa da coluna senão "Unknown column 'updated_at'" (RC-31).
        $table->timestamp('updated_at')->nullable();
    });
});

it('Meta webhook + phone resolvido → conversation e message ganham phone_id', function () {
    $phone = WhatsappBusinessPhone::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'phone_uuid' => (string) Str::uuid(),
        'label' => 'Comercial',
        'driver' => 'meta_cloud',
        'fallback_driver' => 'meta_cloud',
        'meta_phone_number_id' => '123456789',
    ]);

    // Payload Meta com phone_number_id
    $payload = [
        'entry' => [[
            'changes' => [[
                'value' => [
                    'metadata' => ['phone_number_id' => '123456789'],
                    'messages' => [[
                        'id' => 'wamid.META.001',
                        'from' => '5511987654321',
                        'type' => 'text',
                        'text' => ['body' => 'Olá Comercial'],
                    ]],
                ],
            ]],
        ]],
    ];

    $job = new ProcessIncomingWebhookJob(
        businessId: 1,
        provider: 'meta_cloud',
        payload: $payload,
        whatsappBusinessPhoneId: $phone->id,
    );
    $job->handle();

    $msg = WhatsappMessage::withoutGlobalScope(ScopeByBusiness::class)->first();
    expect($msg)->not->toBeNull();
    expect($msg->whatsapp_business_phone_id)->toBe($phone->id);
    expect($msg->business_id)->toBe(1);
    expect($msg->direction)->toBe('inbound');

    $conv = WhatsappConversation::withoutGlobalScope(ScopeByBusiness::class)->first();
    expect($conv->whatsapp_business_phone_id)->toBe($phone->id);
});

it('Z-API webhook + phone resolvido → message ganha phone_id', function () {
    $phone = WhatsappBusinessPhone::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'phone_uuid' => (string) Str::uuid(),
        'label' => 'Comercial',
        'driver' => 'zapi',
        'fallback_driver' => 'meta_cloud',
        'zapi_instance_id' => 'inst-xyz',
        'zapi_instance_token' => 'tok-zapi-001',
    ]);

    $payload = [
        'messageId' => 'zapi.MSG.001',
        'phone' => '5511988887777',
        'fromMe' => false,
        'type' => 'ReceivedCallback',
        'text' => ['message' => 'oi via z-api'],
    ];

    $job = new ProcessIncomingWebhookJob(
        businessId: 1,
        provider: 'zapi',
        payload: $payload,
        whatsappBusinessPhoneId: $phone->id,
    );
    $job->handle();

    $msg = WhatsappMessage::withoutGlobalScope(ScopeByBusiness::class)->first();
    expect($msg->whatsapp_business_phone_id)->toBe($phone->id);
    expect($msg->provider)->toBe('zapi');
});

it('Baileys webhook + phone resolvido → message ganha phone_id', function () {
    $phone = WhatsappBusinessPhone::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'phone_uuid' => (string) Str::uuid(),
        'label' => 'Comercial',
        'driver' => 'baileys',
        'fallback_driver' => 'meta_cloud',
        'baileys_instance_id' => 'biz1-abc123',
    ]);

    $payload = [
        'event' => 'message',
        'instance_id' => 'biz1-abc123',
        'data' => [
            'id' => 'baileys.MSG.001',
            'from' => '5511977776666',
            'body' => 'oi via baileys',
            'type' => 'text',
        ],
    ];

    $job = new ProcessIncomingWebhookJob(
        businessId: 1,
        provider: 'baileys',
        payload: $payload,
        whatsappBusinessPhoneId: $phone->id,
    );
    $job->handle();

    $msg = WhatsappMessage::withoutGlobalScope(ScopeByBusiness::class)->first();
    expect($msg->whatsapp_business_phone_id)->toBe($phone->id);
    expect($msg->provider)->toBe('baileys');
});

it('phoneId NULL (legacy) → message gravada sem phone_id (config fallback)', function () {
    \DB::table('whatsapp_business_configs')->insert([
        'business_id' => 1,
        'business_uuid' => (string) Str::uuid(),
        'driver' => 'zapi',
        'fallback_driver' => 'meta_cloud',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $payload = [
        'messageId' => 'zapi.LEGACY.001',
        'phone' => '5511966665555',
        'fromMe' => false,
        'type' => 'ReceivedCallback',
        'text' => ['message' => 'legacy'],
    ];

    $job = new ProcessIncomingWebhookJob(
        businessId: 1,
        provider: 'zapi',
        payload: $payload,
        // whatsappBusinessPhoneId omitido = null (legacy fallback)
    );
    $job->handle();

    $msg = WhatsappMessage::withoutGlobalScope(ScopeByBusiness::class)->first();
    expect($msg)->not->toBeNull();
    expect($msg->whatsapp_business_phone_id)->toBeNull();
});

it('Tier 0 — phoneId de outro business ignorado, fallback config', function () {
    // Phone em business 999 (não 1)
    $phoneOutroBiz = WhatsappBusinessPhone::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 999,
        'phone_uuid' => (string) Str::uuid(),
        'label' => 'Cross-tenant',
        'driver' => 'zapi',
        'fallback_driver' => 'meta_cloud',
    ]);

    \DB::table('whatsapp_business_configs')->insert([
        'business_id' => 1,
        'business_uuid' => (string) Str::uuid(),
        'driver' => 'zapi',
        'fallback_driver' => 'meta_cloud',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $payload = [
        'messageId' => 'zapi.CROSS.001',
        'phone' => '5511955554444',
        'fromMe' => false,
        'type' => 'ReceivedCallback',
        'text' => ['message' => 'tentativa cross-tenant'],
    ];

    // Tenta usar phone_id=$phoneOutroBiz->id (business 999) com businessId=1
    $job = new ProcessIncomingWebhookJob(
        businessId: 1,
        provider: 'zapi',
        payload: $payload,
        whatsappBusinessPhoneId: $phoneOutroBiz->id,
    );
    $job->handle();

    // Phone foi rejeitado (não casou business_id+id), caiu no fallback config legacy
    $msg = WhatsappMessage::withoutGlobalScope(ScopeByBusiness::class)->first();
    expect($msg->business_id)->toBe(1);
    expect($msg->whatsapp_business_phone_id)->toBeNull(); // não usou phone de outro business
});

it('idempotência: mesmo provider_message_id 2× = 1 message só', function () {
    $phone = WhatsappBusinessPhone::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'phone_uuid' => (string) Str::uuid(),
        'label' => 'Comercial',
        'driver' => 'zapi',
        'fallback_driver' => 'meta_cloud',
    ]);

    $payload = [
        'messageId' => 'zapi.IDEMPOTENT.001',
        'phone' => '5511944443333',
        'fromMe' => false,
        'type' => 'ReceivedCallback',
        'text' => ['message' => 'duplicate'],
    ];

    $job1 = new ProcessIncomingWebhookJob(1, 'zapi', $payload, $phone->id);
    $job1->handle();

    $job2 = new ProcessIncomingWebhookJob(1, 'zapi', $payload, $phone->id);
    $job2->handle();

    $msgs = WhatsappMessage::withoutGlobalScope(ScopeByBusiness::class)->get();
    expect($msgs)->toHaveCount(1);
});
