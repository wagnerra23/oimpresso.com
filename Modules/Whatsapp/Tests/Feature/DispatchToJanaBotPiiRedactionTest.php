<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\WhatsappBusinessPhone;
use Modules\Whatsapp\Entities\WhatsappConversation;
use Modules\Whatsapp\Entities\WhatsappMessage;
use Modules\Whatsapp\Events\WhatsappMessageReceived;
use Modules\Whatsapp\Listeners\DispatchToJanaBot;

uses(Tests\TestCase::class);

/**
 * Wave 9 D7 LGPD — PII redaction obrigatória em log
 * `[whatsapp.dispatch_to_jana_bot]`.
 *
 * Reproduz o vetor de vazamento PII catalogado em
 * memory/requisitos/Whatsapp/COMPLIANCE.md §6 (PII em logs storage/logs/*.log):
 * cliente manda mensagem WhatsApp contendo CPF/CNPJ/email/phone/CEP, listener
 * grava `inbound_preview` em laravel.log. Pre-fix esse log gravava raw.
 * Pós-fix (Wave 9 governance push) usa `Modules\Jana\Services\Privacy\PiiRedactor`
 * pra substituir por placeholder `[REDACTED:TIPO]`.
 *
 * Multi-tenant Tier 0 ADR 0093: testa biz=1 (ADR 0101, NUNCA biz cliente real).
 *
 * @see Modules/Whatsapp/Listeners/DispatchToJanaBot.php
 * @see Modules/Jana/Services/Privacy/PiiRedactor.php
 */

beforeEach(function () {
    foreach (['whatsapp_messages', 'whatsapp_conversations', 'whatsapp_business_phones', 'activity_log'] as $t) {
        Schema::dropIfExists($t);
    }

    // Spatie Activitylog — WhatsappMessage tem LogsActivity, então insert
    // dispara INSERT em activity_log. Schema minimal espelha a migration
    // canônica spatie/laravel-activitylog v4 (subset suficiente pro INSERT).
    Schema::create('activity_log', function ($table) {
        $table->bigIncrements('id');
        $table->string('log_name')->nullable();
        $table->text('description');
        $table->nullableMorphs('subject', 'subject');
        $table->string('event')->nullable();
        $table->nullableMorphs('causer', 'causer');
        $table->longText('properties')->nullable();
        $table->uuid('batch_uuid')->nullable();
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

    config()->set('whatsapp.bot.enabled', true);
});

function setupBotPhoneAndConv(int $businessId, string $body): WhatsappMessageReceived
{
    $phone = WhatsappBusinessPhone::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => $businessId,
        'phone_uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'label' => 'Comercial',
        'driver' => 'zapi',
        'fallback_driver' => 'meta_cloud',
        'handles_jana_bot' => true,
        'bot_enabled' => true,
    ]);

    $conv = WhatsappConversation::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => $businessId,
        'whatsapp_business_phone_id' => $phone->id,
        'customer_phone' => '+5511987654321',
    ]);

    $msg = WhatsappMessage::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => $businessId,
        'conversation_id' => $conv->id,
        'direction' => 'inbound',
        'provider' => 'zapi',
        'provider_message_id' => 'pii-test-' . uniqid(),
        'type' => 'text',
        'body' => $body,
        'status' => 'received',
    ]);

    return new WhatsappMessageReceived($msg);
}

it('redacta CPF do inbound_preview antes de logar', function () {
    Log::spy();

    $event = setupBotPhoneAndConv(1, 'Olá meu CPF é 123.456.789-09 pra cadastro');
    (new DispatchToJanaBot())->handle($event);

    Log::shouldHaveReceived('info')
        ->withArgs(function (string $message, array $context) {
            return str_starts_with($message, '[whatsapp.dispatch_to_jana_bot]')
                && isset($context['inbound_preview'])
                && ! str_contains($context['inbound_preview'], '123.456.789-09')
                && str_contains($context['inbound_preview'], '[REDACTED:CPF]');
        })
        ->once();
});

it('redacta CNPJ do inbound_preview antes de logar', function () {
    Log::spy();

    $event = setupBotPhoneAndConv(1, 'Faturar pro CNPJ 12.345.678/0001-90');
    (new DispatchToJanaBot())->handle($event);

    Log::shouldHaveReceived('info')
        ->withArgs(function (string $message, array $context) {
            return str_starts_with($message, '[whatsapp.dispatch_to_jana_bot]')
                && ! str_contains($context['inbound_preview'], '12.345.678/0001-90')
                && str_contains($context['inbound_preview'], '[REDACTED:CNPJ]');
        })
        ->once();
});

it('redacta email do inbound_preview antes de logar', function () {
    Log::spy();

    $event = setupBotPhoneAndConv(1, 'Pode mandar boleto pra cliente@exemplo.com.br por favor');
    (new DispatchToJanaBot())->handle($event);

    Log::shouldHaveReceived('info')
        ->withArgs(function (string $message, array $context) {
            return str_starts_with($message, '[whatsapp.dispatch_to_jana_bot]')
                && ! str_contains($context['inbound_preview'], 'cliente@exemplo.com.br')
                && str_contains($context['inbound_preview'], '[REDACTED:EMAIL]');
        })
        ->once();
});

it('redacta CEP do inbound_preview antes de logar', function () {
    Log::spy();

    $event = setupBotPhoneAndConv(1, 'Entregar no CEP 88780-000 Termas');
    (new DispatchToJanaBot())->handle($event);

    Log::shouldHaveReceived('info')
        ->withArgs(function (string $message, array $context) {
            return str_starts_with($message, '[whatsapp.dispatch_to_jana_bot]')
                && ! str_contains($context['inbound_preview'], '88780-000')
                && str_contains($context['inbound_preview'], '[REDACTED:CEP]');
        })
        ->once();
});

it('preserva texto não-PII normal sem alterar o preview', function () {
    Log::spy();

    $event = setupBotPhoneAndConv(1, 'Oi bom dia preciso de uma duvida sobre o produto');
    (new DispatchToJanaBot())->handle($event);

    Log::shouldHaveReceived('info')
        ->withArgs(function (string $message, array $context) {
            return str_starts_with($message, '[whatsapp.dispatch_to_jana_bot]')
                && str_contains($context['inbound_preview'], 'Oi bom dia')
                && ! str_contains($context['inbound_preview'], '[REDACTED:');
        })
        ->once();
});

it('multi-tenant Tier 0 — biz=1 redact isolado de biz=99', function () {
    Log::spy();

    $eventBiz1 = setupBotPhoneAndConv(1, 'CPF 111.222.333-44 biz=1');
    (new DispatchToJanaBot())->handle($eventBiz1);

    $eventBiz99 = setupBotPhoneAndConv(99, 'CPF 555.666.777-88 biz=99');
    (new DispatchToJanaBot())->handle($eventBiz99);

    // Cada biz registra 1 entrada redacted (não cross-tenant leak).
    Log::shouldHaveReceived('info')
        ->withArgs(function (string $message, array $context) {
            return str_starts_with($message, '[whatsapp.dispatch_to_jana_bot]')
                && ($context['business_id'] ?? null) === 1
                && ! str_contains($context['inbound_preview'], '111.222.333-44')
                && str_contains($context['inbound_preview'], '[REDACTED:CPF]');
        })
        ->once();

    Log::shouldHaveReceived('info')
        ->withArgs(function (string $message, array $context) {
            return str_starts_with($message, '[whatsapp.dispatch_to_jana_bot]')
                && ($context['business_id'] ?? null) === 99
                && ! str_contains($context['inbound_preview'], '555.666.777-88')
                && str_contains($context['inbound_preview'], '[REDACTED:CPF]');
        })
        ->once();
});

it('config retention canônico Wave 9 está exposed via config()', function () {
    expect(config('whatsapp.retention.body_redact_days'))->toBe(180);
    expect(config('whatsapp.retention.media_purge_days'))->toBe(365);
    expect(config('whatsapp.retention.contact_anonymize_months'))->toBe(24);
    expect(config('whatsapp.retention.activity_log_years'))->toBe(5);
});
