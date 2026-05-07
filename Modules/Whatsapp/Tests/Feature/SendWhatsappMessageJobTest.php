<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\WhatsappBusinessConfig;
use Modules\Whatsapp\Entities\WhatsappConversation;
use Modules\Whatsapp\Entities\WhatsappMessage;
use Modules\Whatsapp\Events\WhatsappMessageFailed;
use Modules\Whatsapp\Events\WhatsappMessageQueued;
use Modules\Whatsapp\Events\WhatsappMessageSent;
use Modules\Whatsapp\Jobs\SendWhatsappMessageJob;

uses(Tests\TestCase::class);

/**
 * US-WA-003 · SendWhatsappMessageJob — fluxo end-to-end + multi-tenant Tier 0.
 *
 * Cobre:
 * - Job aceita $businessId no constructor (Tier 0 — não usa session())
 * - Cria WhatsappMessage status=queued antes do driver
 * - Em sucesso: status=sent + provider_message_id, dispara Sent event
 * - Em falha permanente (4xx): status=failed, dispara Failed event,
 *   re-throw pra Laravel agendar retry exponencial
 * - WhatsappConversation criada/atualizada (last_outbound_at)
 *
 * Padrão SQLite friendly (cria tabelas em beforeEach — RecurringBilling pattern).
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

    // Cria config ZAPI pra business=4 (driver=null não estoura rede em Pest)
    WhatsappBusinessConfig::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'business_uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'driver' => 'zapi',
        'fallback_driver' => 'meta_cloud',
        'driver_health' => 'healthy',
        'zapi_instance_id' => 'test-instance-123',
        'zapi_instance_token' => 'token-xyz',
        'zapi_client_token' => 'client-abc',
    ]);
});

it('cria WhatsappMessage queued + dispatch Queued event antes de chamar driver', function () {
    Event::fake([WhatsappMessageQueued::class, WhatsappMessageSent::class]);

    Http::fake([
        'api.z-api.io/*' => Http::response(['messageId' => 'wamid.TEST123'], 200),
    ]);

    $job = new SendWhatsappMessageJob(
        businessId: 1,
        to: '+5511987654321',
        kind: 'freeform',
        payload: ['body' => 'Olá Cliente — sua OS está pronta!'],
    );

    $job->handle();

    Event::assertDispatched(WhatsappMessageQueued::class);
    Event::assertDispatched(WhatsappMessageSent::class);

    // Mensagem persistida com status=sent + provider_message_id
    $msgs = WhatsappMessage::withoutGlobalScope(ScopeByBusiness::class)->get();
    expect($msgs)->toHaveCount(1);
    $msg = $msgs->first();
    expect($msg->status)->toBe('sent');
    expect($msg->provider_message_id)->toBe('wamid.TEST123');
    expect($msg->business_id)->toBe(4);
    expect($msg->direction)->toBe('outbound');
    expect($msg->provider)->toBe('zapi');
});

it('em falha permanente: status=failed + Failed event + re-throw pra retry exponencial', function () {
    Event::fake([WhatsappMessageFailed::class]);

    Http::fake([
        'api.z-api.io/*' => Http::response(['error' => 'invalid phone'], 400),
    ]);

    $job = new SendWhatsappMessageJob(
        businessId: 1,
        to: '+5511987654321',
        kind: 'freeform',
        payload: ['body' => 'msg falha'],
    );

    expect(fn () => $job->handle())
        ->toThrow(\RuntimeException::class, 'Whatsapp send failed');

    Event::assertDispatched(WhatsappMessageFailed::class);

    $msg = WhatsappMessage::withoutGlobalScope(ScopeByBusiness::class)->first();
    expect($msg->status)->toBe('failed');
    expect($msg->failed_reason)->toContain('invalid phone');
});

it('detecta ban Z-API (HTTP 403) e marca banDetected no Failed event', function () {
    Event::fake([WhatsappMessageFailed::class]);

    Http::fake([
        'api.z-api.io/*' => Http::response(['error' => 'number banned by Meta'], 403),
    ]);

    $job = new SendWhatsappMessageJob(
        businessId: 1,
        to: '+5511987654321',
        kind: 'freeform',
        payload: ['body' => 'msg ban'],
    );

    expect(fn () => $job->handle())->toThrow(\RuntimeException::class);

    Event::assertDispatched(WhatsappMessageFailed::class, function ($event) {
        return $event->banDetected === true;
    });
});

it('atualiza WhatsappConversation last_outbound_at em sucesso', function () {
    Http::fake([
        'api.z-api.io/*' => Http::response(['messageId' => 'wamid.OK'], 200),
    ]);

    $job = new SendWhatsappMessageJob(
        businessId: 1,
        to: '+5511987654321',
        kind: 'freeform',
        payload: ['body' => 'olá'],
    );
    $job->handle();

    $conv = WhatsappConversation::withoutGlobalScope(ScopeByBusiness::class)->first();
    expect($conv)->not->toBeNull();
    expect($conv->business_id)->toBe(4);
    expect($conv->customer_phone)->toBe('+5511987654321');
    expect($conv->last_outbound_at)->not->toBeNull();
    expect($conv->last_message_at)->not->toBeNull();
});

it('Tier 0 — businessId no constructor isola do session()', function () {
    Http::fake([
        'api.z-api.io/*' => Http::response(['messageId' => 'wamid.ISOLATED'], 200),
    ]);

    // Simula session de outro business (deve ser ignorado pelo job — Tier 0)
    auth()->logout();
    session(['user.business_id' => 999]);

    $job = new SendWhatsappMessageJob(
        businessId: 1, // explícito no constructor
        to: '+5511987654321',
        kind: 'freeform',
        payload: ['body' => 'tier 0 test'],
    );
    $job->handle();

    $msg = WhatsappMessage::withoutGlobalScope(ScopeByBusiness::class)->first();
    expect($msg->business_id)->toBe(4); // não 999
});
