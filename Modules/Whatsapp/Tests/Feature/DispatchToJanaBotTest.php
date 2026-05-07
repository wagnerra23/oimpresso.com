<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\WhatsappBusinessConfig;
use Modules\Whatsapp\Entities\WhatsappConversation;
use Modules\Whatsapp\Entities\WhatsappMessage;
use Modules\Whatsapp\Events\WhatsappMessageReceived;
use Modules\Whatsapp\Listeners\DispatchToJanaBot;

uses(Tests\TestCase::class);

/**
 * US-WA-020 (Sprint 3 prep) · DispatchToJanaBot listener.
 *
 * Cobre:
 * - bot.enabled global=false → no-op
 * - bot.enabled global=true mas WhatsappBusinessConfig sem bot_enabled → no-op
 * - bot.enabled global+business=true → marca conversation.bot_handling=true
 * - sem WhatsappBusinessConfig pra business → no-op (não estoura)
 * - sem WhatsappConversation → no-op
 *
 * Sprint 3: quando ADS Universal ativar, este test cobre também os 4
 * outcomes do PolicyEngine (ALLOW_BRAIN_A/REQUIRE_BRAIN_B/REQUIRE_HUMAN_REVIEW/BLOCK).
 */

beforeEach(function () {
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
        $table->string('driver_health', 20)->default('healthy');
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
    });
    Schema::create('whatsapp_messages', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
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

function makeMessageReceivedEvent(int $businessId, int $convId, string $body): WhatsappMessageReceived
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

    $conv = WhatsappConversation::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'customer_phone' => '+5511987654321',
    ]);
    WhatsappBusinessConfig::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'business_uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'driver' => 'zapi',
        'bot_enabled' => true, // mesmo true em business
    ]);

    $event = makeMessageReceivedEvent(4, $conv->id, 'oi');
    (new DispatchToJanaBot())->handle($event);

    $conv->refresh();
    expect($conv->bot_handling)->toBeFalse(); // global flag desligado, não tocou
});

it('no-op quando bot.enabled business = false (mesmo global true)', function () {
    config()->set('whatsapp.bot.enabled', true);

    $conv = WhatsappConversation::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'customer_phone' => '+5511987654321',
    ]);
    WhatsappBusinessConfig::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'business_uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'driver' => 'zapi',
        'bot_enabled' => false, // business desligou
    ]);

    $event = makeMessageReceivedEvent(4, $conv->id, 'oi');
    (new DispatchToJanaBot())->handle($event);

    $conv->refresh();
    expect($conv->bot_handling)->toBeFalse();
});

it('marca conversation.bot_handling=true quando ambos enabled', function () {
    config()->set('whatsapp.bot.enabled', true);

    $conv = WhatsappConversation::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'customer_phone' => '+5511987654321',
        'bot_handling' => false,
    ]);
    WhatsappBusinessConfig::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'business_uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'driver' => 'zapi',
        'bot_enabled' => true,
    ]);

    $event = makeMessageReceivedEvent(4, $conv->id, 'oi');
    (new DispatchToJanaBot())->handle($event);

    $conv->refresh();
    expect($conv->bot_handling)->toBeTrue();
});

it('no-op quando WhatsappBusinessConfig não existe (business sem Whatsapp ativo)', function () {
    config()->set('whatsapp.bot.enabled', true);

    $conv = WhatsappConversation::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 99, // sem config
        'customer_phone' => '+5511987654321',
    ]);

    $event = makeMessageReceivedEvent(99, $conv->id, 'oi');

    // Não deve estourar exception
    expect(fn () => (new DispatchToJanaBot())->handle($event))->not->toThrow(\Throwable::class);

    $conv->refresh();
    expect($conv->bot_handling)->toBeFalse();
});
