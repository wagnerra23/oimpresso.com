<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\WhatsappBusinessPhone;
use Modules\Whatsapp\Entities\WhatsappConversation;
use Modules\Whatsapp\Entities\WhatsappMessage;
use Modules\Whatsapp\Events\WhatsappMessageFailed;
use Modules\Whatsapp\Events\WhatsappMessageQueued;
use Modules\Whatsapp\Events\WhatsappMessageSent;
use Modules\Whatsapp\Jobs\SendWhatsappMessageJob;

uses(Tests\TestCase::class);

/**
 * US-WA-003 + US-WA-040 · SendWhatsappMessageJob — fluxo end-to-end
 * + multi-tenant Tier 0 + multi-números (ADR 0117).
 *
 * Cobre:
 * - Job aceita $businessId + $whatsappBusinessPhoneId no constructor
 *   (Tier 0 + multi-números — não usa session())
 * - Resolve WhatsappBusinessPhone defensivo (where('business_id'=$bizId,
 *   'id'=$phoneId)) — phone de outro business jamais é aceito
 * - Cria WhatsappMessage status=queued antes do driver com phone_id setado
 * - Em sucesso: status=sent + provider_message_id, dispara Sent event
 * - Em falha permanente (4xx): status=failed, dispara Failed event, re-throw
 * - WhatsappConversation criada/atualizada com phone_id correto
 *
 * Padrão SQLite friendly (cria tabelas em beforeEach).
 */

beforeEach(function () {
    // era-sqlite: este teste cria schema manual (sqlite-friendly). No MySQL persistente
    // do nightly isso DROPA tabelas reais → corrompe os testes irmãos (lever do floor SDD).
    // Cobertura real é na lane sqlite (per-PR); pula no MySQL.
    if (config('database.default') !== 'sqlite') {
        $this->markTestSkipped('era-sqlite: corruptor de schema compartilhado no MySQL — sqlite-only no burn-down do floor SDD.');
    }
    foreach ([
        'whatsapp_messages',
        'whatsapp_conversations',
        'whatsapp_business_phones',
    ] as $t) {
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
        $table->unsignedBigInteger('whatsapp_business_phone_id')->nullable();
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

    // Cria phone ZAPI pra business=1
    WhatsappBusinessPhone::withoutGlobalScope(ScopeByBusiness::class)->create([
        'id' => 10,
        'business_id' => 1,
        'phone_uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'label' => 'Comercial',
        'driver' => 'zapi',
        'fallback_driver' => 'meta_cloud',
        'driver_health' => 'healthy',
        'zapi_instance_id' => 'test-instance-123',
        'zapi_instance_token' => 'token-xyz',
        'zapi_client_token' => 'client-abc',
        'handles_outbound_default' => true,
    ]);
});

it('cria WhatsappMessage queued + dispatch Queued event antes de chamar driver', function () {
    Event::fake([WhatsappMessageQueued::class, WhatsappMessageSent::class]);

    Http::fake([
        'api.z-api.io/*' => Http::response(['messageId' => 'wamid.TEST123'], 200),
    ]);

    $job = new SendWhatsappMessageJob(
        businessId: 1,
        whatsappBusinessPhoneId: 10,
        to: '+5511987654321',
        kind: 'freeform',
        payload: ['body' => 'Olá Cliente — sua OS está pronta!'],
    );

    $job->handle();

    Event::assertDispatched(WhatsappMessageQueued::class);
    Event::assertDispatched(WhatsappMessageSent::class);

    $msgs = WhatsappMessage::withoutGlobalScope(ScopeByBusiness::class)->get();
    expect($msgs)->toHaveCount(1);
    $msg = $msgs->first();
    expect($msg->status)->toBe('sent');
    expect($msg->provider_message_id)->toBe('wamid.TEST123');
    expect($msg->business_id)->toBe(1);
    expect($msg->whatsapp_business_phone_id)->toBe(10);
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
        whatsappBusinessPhoneId: 10,
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
        whatsappBusinessPhoneId: 10,
        to: '+5511987654321',
        kind: 'freeform',
        payload: ['body' => 'msg ban'],
    );

    expect(fn () => $job->handle())->toThrow(\RuntimeException::class);

    Event::assertDispatched(WhatsappMessageFailed::class, function ($event) {
        return $event->banDetected === true;
    });
});

it('atualiza WhatsappConversation last_outbound_at em sucesso + vincula phone_id', function () {
    Http::fake([
        'api.z-api.io/*' => Http::response(['messageId' => 'wamid.OK'], 200),
    ]);

    $job = new SendWhatsappMessageJob(
        businessId: 1,
        whatsappBusinessPhoneId: 10,
        to: '+5511987654321',
        kind: 'freeform',
        payload: ['body' => 'olá'],
    );
    $job->handle();

    $conv = WhatsappConversation::withoutGlobalScope(ScopeByBusiness::class)->first();
    expect($conv)->not->toBeNull();
    expect($conv->business_id)->toBe(1);
    expect($conv->whatsapp_business_phone_id)->toBe(10);
    expect($conv->customer_phone)->toBe('+5511987654321');
    expect($conv->last_outbound_at)->not->toBeNull();
    expect($conv->last_message_at)->not->toBeNull();
});

it('Tier 0 — businessId + phoneId no constructor isolam do session()', function () {
    Http::fake([
        'api.z-api.io/*' => Http::response(['messageId' => 'wamid.ISOLATED'], 200),
    ]);

    // Simula session de outro business (deve ser ignorado pelo job — Tier 0)
    auth()->logout();
    session(['user.business_id' => 999]);

    $job = new SendWhatsappMessageJob(
        businessId: 1,
        whatsappBusinessPhoneId: 10,
        to: '+5511987654321',
        kind: 'freeform',
        payload: ['body' => 'tier 0 test'],
    );
    $job->handle();

    $msg = WhatsappMessage::withoutGlobalScope(ScopeByBusiness::class)->first();
    expect($msg->business_id)->toBe(1);
    expect($msg->whatsapp_business_phone_id)->toBe(10);
});

it('Tier 0 defensive — phone de outro business é rejeitado com firstOrFail', function () {
    // Cria phone em outro business
    WhatsappBusinessPhone::withoutGlobalScope(ScopeByBusiness::class)->create([
        'id' => 99,
        'business_id' => 999,
        'phone_uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'label' => 'Outro biz',
        'driver' => 'null',
        'fallback_driver' => 'null',
        'driver_health' => 'healthy',
    ]);

    // Tenta usar phone_id=99 (business 999) com businessId=1 — Tier 0 bloqueia
    $job = new SendWhatsappMessageJob(
        businessId: 1,
        whatsappBusinessPhoneId: 99, // de outro business!
        to: '+5511987654321',
        kind: 'freeform',
        payload: ['body' => 'cross-tenant attempt'],
    );

    expect(fn () => $job->handle())
        ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

    // Nenhuma mensagem foi criada
    expect(WhatsappMessage::withoutGlobalScope(ScopeByBusiness::class)->count())->toBe(0);
});

it('tags do Job incluem business + phone + kind', function () {
    $job = new SendWhatsappMessageJob(
        businessId: 1,
        whatsappBusinessPhoneId: 10,
        to: '+5511987654321',
        kind: 'freeform',
        payload: ['body' => 'tag test'],
    );

    $tags = $job->tags();
    expect($tags)->toContain('business:1');
    expect($tags)->toContain('phone:10');
    expect($tags)->toContain('whatsapp:freeform');
});
