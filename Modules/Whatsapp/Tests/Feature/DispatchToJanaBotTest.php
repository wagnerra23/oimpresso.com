<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\WhatsappBusinessPhone;
use Modules\Whatsapp\Entities\WhatsappConversation;
use Modules\Whatsapp\Entities\WhatsappMessage;
use Modules\Whatsapp\Events\WhatsappMessageReceived;
use Modules\Whatsapp\Listeners\DispatchToJanaBot;

uses(Tests\TestCase::class);

/**
 * US-WA-020 (Sprint 3 prep) + US-WA-040 · DispatchToJanaBot listener com
 * roteamento por phone (ADR 0117).
 *
 * Cobre:
 * - bot.enabled global=false → no-op (bot_handling intacto)
 * - sem phone configurado → no-op silencioso
 * - phone com handles_jana_bot=false → no-op (admin desativou bot
 *   neste número, ex: Financeiro só humano)
 * - phone com handles_jana_bot=true mas bot_enabled=false → no-op
 * - phone com ambos handles_jana_bot=true e bot_enabled=true →
 *   marca conversation.bot_handling=true
 * - conversation.whatsapp_business_phone_id setado → usa esse phone
 *   diretamente; senão fallback resolveForEvent
 *
 * Sprint 3: quando ADS Universal ativar, cobre também 4 outcomes do PolicyEngine.
 */

beforeEach(function () {
    // era-sqlite: este teste cria schema manual (sqlite-friendly). No MySQL persistente
    // do nightly isso DROPA tabelas reais → corrompe os testes irmãos (lever do floor SDD).
    // Cobertura real é na lane sqlite (per-PR); pula no MySQL.
    if (config('database.default') !== 'sqlite') {
        $this->markTestSkipped('era-sqlite: corruptor de schema compartilhado no MySQL — sqlite-only no burn-down do floor SDD.');
    }
    foreach (['whatsapp_messages', 'whatsapp_conversations', 'whatsapp_business_phones'] as $t) {
        Schema::dropIfExists($t);
    }

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
        $table->string('driver_health', 20)->default('healthy');
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
        $table->unsignedInteger('assigned_user_id')->nullable();
        $table->boolean('bot_handling')->default(false);
        $table->timestamp('last_inbound_at')->nullable();
        $table->timestamp('last_outbound_at')->nullable();
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
        $table->string('provider_message_id', 128)->nullable();
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
});

function makeJanaBotMessageReceivedEvent(int $businessId, int $convId, string $body): WhatsappMessageReceived
{
    $msg = WhatsappMessage::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => $businessId,
        'conversation_id' => $convId,
        'direction' => 'inbound',
        'provider' => 'zapi',
        'provider_message_id' => 'test-' . uniqid(),
        'type' => 'text',
        'body' => $body,
        'status' => 'received',
    ]);
    return new WhatsappMessageReceived($msg);
}

it('no-op quando bot.enabled global = false', function () {
    config()->set('whatsapp.bot.enabled', false);

    WhatsappBusinessPhone::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'phone_uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'label' => 'Comercial',
        'driver' => 'zapi',
        'fallback_driver' => 'meta_cloud',
        'handles_jana_bot' => true,
        'bot_enabled' => true,
    ]);

    $conv = WhatsappConversation::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'customer_phone' => '+5511987654321',
    ]);

    $event = makeJanaBotMessageReceivedEvent(1, $conv->id, 'oi');
    (new DispatchToJanaBot())->handle($event);

    $conv->refresh();
    expect($conv->bot_handling)->toBeFalse();
});

it('no-op quando phone tem handles_jana_bot=false (admin desligou bot pra este número)', function () {
    config()->set('whatsapp.bot.enabled', true);

    $phone = WhatsappBusinessPhone::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'phone_uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'label' => 'Financeiro só humano',
        'driver' => 'zapi',
        'fallback_driver' => 'meta_cloud',
        'handles_jana_bot' => false, // admin desligou bot pra este número
        'bot_enabled' => true,
    ]);

    $conv = WhatsappConversation::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'whatsapp_business_phone_id' => $phone->id,
        'customer_phone' => '+5511987654321',
    ]);

    $event = makeJanaBotMessageReceivedEvent(1, $conv->id, 'oi');
    (new DispatchToJanaBot())->handle($event);

    $conv->refresh();
    expect($conv->bot_handling)->toBeFalse();
});

it('no-op quando phone tem handles_jana_bot=true mas bot_enabled=false', function () {
    config()->set('whatsapp.bot.enabled', true);

    $phone = WhatsappBusinessPhone::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'phone_uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'label' => 'Comercial',
        'driver' => 'zapi',
        'fallback_driver' => 'meta_cloud',
        'handles_jana_bot' => true,
        'bot_enabled' => false,
    ]);

    $conv = WhatsappConversation::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'whatsapp_business_phone_id' => $phone->id,
        'customer_phone' => '+5511987654321',
    ]);

    $event = makeJanaBotMessageReceivedEvent(1, $conv->id, 'oi');
    (new DispatchToJanaBot())->handle($event);

    $conv->refresh();
    expect($conv->bot_handling)->toBeFalse();
});

it('marca conversation.bot_handling=true quando phone tem ambos flags ligados', function () {
    config()->set('whatsapp.bot.enabled', true);

    $phone = WhatsappBusinessPhone::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'phone_uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'label' => 'Comercial',
        'driver' => 'zapi',
        'fallback_driver' => 'meta_cloud',
        'handles_jana_bot' => true,
        'bot_enabled' => true,
    ]);

    $conv = WhatsappConversation::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'whatsapp_business_phone_id' => $phone->id,
        'customer_phone' => '+5511987654321',
        'bot_handling' => false,
    ]);

    $event = makeJanaBotMessageReceivedEvent(1, $conv->id, 'oi');
    (new DispatchToJanaBot())->handle($event);

    $conv->refresh();
    expect($conv->bot_handling)->toBeTrue();
});

it('conversation com whatsapp_business_phone_id NULL → fallback resolveForEvent jana_bot', function () {
    config()->set('whatsapp.bot.enabled', true);

    // Phone existe + flags ligados, mas conversation legacy não vincula
    WhatsappBusinessPhone::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'phone_uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'label' => 'Comercial',
        'driver' => 'zapi',
        'fallback_driver' => 'meta_cloud',
        'handles_jana_bot' => true,
        'bot_enabled' => true,
    ]);

    $conv = WhatsappConversation::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'whatsapp_business_phone_id' => null, // legacy não migrada
        'customer_phone' => '+5511987654321',
    ]);

    $event = makeJanaBotMessageReceivedEvent(1, $conv->id, 'oi');
    (new DispatchToJanaBot())->handle($event);

    $conv->refresh();
    expect($conv->bot_handling)->toBeTrue();
});

it('no-op quando business não tem phone cadastrado', function () {
    config()->set('whatsapp.bot.enabled', true);

    $conv = WhatsappConversation::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 99,
        'customer_phone' => '+5511987654321',
    ]);

    $event = makeJanaBotMessageReceivedEvent(99, $conv->id, 'oi');

    expect(fn () => (new DispatchToJanaBot())->handle($event))->not->toThrow(\Throwable::class);

    $conv->refresh();
    expect($conv->bot_handling)->toBeFalse();
});
