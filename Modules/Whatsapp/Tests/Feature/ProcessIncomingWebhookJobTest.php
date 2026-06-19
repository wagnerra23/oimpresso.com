<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\WhatsappBusinessConfig;
use Modules\Whatsapp\Entities\WhatsappConversation;
use Modules\Whatsapp\Entities\WhatsappMessage;
use Modules\Whatsapp\Events\WhatsappMessageReceived;
use Modules\Whatsapp\Jobs\ProcessIncomingWebhookJob;

uses(Tests\TestCase::class);

/**
 * R-WA-003 + US-WA-011 · ProcessIncomingWebhookJob driver-agnóstico.
 *
 * Cobre:
 * - Meta Cloud payload (estrutura entry/changes/value/messages) → cria msg inbound
 * - Z-API payload (on-message com text.message) → cria msg inbound
 * - Z-API fromMe=true → IGNORA (echo do próprio business)
 * - Idempotência: mesmo provider_message_id 2× = no-op (UNIQUE)
 * - Conversation criada/atualizada (last_inbound_at, unread_count++)
 * - Tier 0 isolation: business_id explícito (não usa session)
 *
 * Padrão SQLite friendly.
 */

beforeEach(function () {
    // era-sqlite: este teste cria schema manual (sqlite-friendly). No MySQL persistente
    // do nightly isso DROPA tabelas reais → corrompe os testes irmãos (lever do floor SDD).
    // Cobertura real é na lane sqlite (per-PR); pula no MySQL.
    if (config('database.default') !== 'sqlite') {
        $this->markTestSkipped('era-sqlite: corruptor de schema compartilhado no MySQL — sqlite-only no burn-down do floor SDD.');
    }
    foreach (['whatsapp_messages', 'whatsapp_conversations', 'whatsapp_business_configs'] as $t) {
        Schema::dropIfExists($t);
    }

    Schema::create('whatsapp_business_configs', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->uuid('business_uuid')->unique();
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
        $table->string('baileys_daemon_url', 255)->nullable();
        $table->text('baileys_api_key')->nullable();
        $table->timestamp('lgpd_acknowledged_at')->nullable();
        $table->unsignedInteger('lgpd_acknowledged_by_user_id')->nullable();
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
        $table->unsignedInteger('contact_id')->nullable();
        $table->string('customer_phone', 20);
        $table->string('status', 20)->default('open');
        $table->unsignedInteger('assigned_user_id')->nullable();
        $table->boolean('bot_handling')->default(false);
        $table->timestamp('last_inbound_at')->nullable();
        $table->timestamp('last_outbound_at')->nullable();
        $table->timestamp('last_message_at')->nullable();
        $table->unsignedInteger('unread_count')->default(0);
        $table->timestamps();
        $table->unique(['business_id', 'customer_phone']);
    });

    Schema::create('whatsapp_messages', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->unsignedBigInteger('conversation_id');
        $table->string('direction', 10);
        $table->string('provider', 20);
        $table->string('provider_message_id', 128)->nullable()->unique();
        $table->string('type', 20)->default('text');
        $table->string('template_name', 64)->nullable();
        $table->text('body')->nullable();
        $table->json('payload')->nullable();
        $table->string('status', 20);
        $table->string('failed_reason', 255)->nullable();
        $table->unsignedInteger('sender_user_id')->nullable();
        $table->string('sender_kind', 20)->nullable();
        $table->unsignedInteger('cost_centavos')->nullable();
        $table->timestamp('created_at')->useCurrent();
        $table->timestamp('updated_at')->nullable();
    });

    WhatsappBusinessConfig::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'business_uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'driver' => 'zapi',
        'fallback_driver' => 'meta_cloud',
    ]);
});

it('Meta Cloud: extrai mensagem text de payload entry/changes/value/messages', function () {
    Event::fake([WhatsappMessageReceived::class]);

    $payload = [
        'entry' => [[
            'changes' => [[
                'value' => [
                    'messages' => [[
                        'id' => 'wamid.MtA',
                        'from' => '5511987654321',
                        'type' => 'text',
                        'text' => ['body' => 'Olá oimpresso!'],
                    ]],
                ],
            ]],
        ]],
    ];

    (new ProcessIncomingWebhookJob(4, 'meta_cloud', $payload))->handle();

    $msg = WhatsappMessage::withoutGlobalScope(ScopeByBusiness::class)->first();
    expect($msg)->not->toBeNull();
    expect($msg->business_id)->toBe(4);
    expect($msg->direction)->toBe('inbound');
    expect($msg->provider)->toBe('meta_cloud');
    expect($msg->provider_message_id)->toBe('wamid.MtA');
    expect($msg->body)->toBe('Olá oimpresso!');
    expect($msg->status)->toBe('received');

    Event::assertDispatched(WhatsappMessageReceived::class);
});

it('Z-API: extrai mensagem on-message com text.message', function () {
    Event::fake([WhatsappMessageReceived::class]);

    $payload = [
        'type' => 'ReceivedCallback',
        'messageId' => 'msg-zapi-123',
        'phone' => '5511987654321',
        'fromMe' => false,
        'text' => ['message' => 'Oi pelo Z-API'],
    ];

    (new ProcessIncomingWebhookJob(4, 'zapi', $payload))->handle();

    $msg = WhatsappMessage::withoutGlobalScope(ScopeByBusiness::class)->first();
    expect($msg->provider_message_id)->toBe('msg-zapi-123');
    expect($msg->body)->toBe('Oi pelo Z-API');
    expect($msg->provider)->toBe('zapi');

    Event::assertDispatched(WhatsappMessageReceived::class);
});

it('Z-API: ignora mensagens fromMe=true (echo do próprio business)', function () {
    Event::fake([WhatsappMessageReceived::class]);

    $payload = [
        'type' => 'ReceivedCallback',
        'messageId' => 'msg-echo',
        'phone' => '5511987654321',
        'fromMe' => true, // echo do próprio business
        'text' => ['message' => 'echo do próprio'],
    ];

    (new ProcessIncomingWebhookJob(4, 'zapi', $payload))->handle();

    $msgs = WhatsappMessage::withoutGlobalScope(ScopeByBusiness::class)->get();
    expect($msgs)->toHaveCount(0);

    Event::assertNotDispatched(WhatsappMessageReceived::class);
});

it('idempotência: mesma provider_message_id 2 vezes = no-op', function () {
    $payload = [
        'type' => 'ReceivedCallback',
        'messageId' => 'msg-DUPLICATE',
        'phone' => '5511987654321',
        'fromMe' => false,
        'text' => ['message' => 'mensagem dupla'],
    ];

    // 1ª execução cria
    (new ProcessIncomingWebhookJob(4, 'zapi', $payload))->handle();
    // 2ª execução não duplica
    (new ProcessIncomingWebhookJob(4, 'zapi', $payload))->handle();

    $count = WhatsappMessage::withoutGlobalScope(ScopeByBusiness::class)
        ->where('provider_message_id', 'msg-DUPLICATE')
        ->count();
    expect($count)->toBe(1);
});

it('cria/atualiza WhatsappConversation com last_inbound_at e unread_count++', function () {
    $payload = [
        'type' => 'ReceivedCallback',
        'messageId' => 'msg-conv-1',
        'phone' => '5511987654321',
        'fromMe' => false,
        'text' => ['message' => 'msg 1'],
    ];

    (new ProcessIncomingWebhookJob(4, 'zapi', $payload))->handle();

    $conv = WhatsappConversation::withoutGlobalScope(ScopeByBusiness::class)->first();
    expect($conv->business_id)->toBe(4);
    expect($conv->customer_phone)->toBe('+5511987654321');
    expect($conv->last_inbound_at)->not->toBeNull();
    expect($conv->unread_count)->toBe(1);

    // Segunda mensagem mesma conversa → unread++
    $payload['messageId'] = 'msg-conv-2';
    (new ProcessIncomingWebhookJob(4, 'zapi', $payload))->handle();

    $conv->refresh();
    expect($conv->unread_count)->toBe(2);
});
