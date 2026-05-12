<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\WhatsappBusinessPhone;
use Modules\Whatsapp\Entities\WhatsappMessage;
use Modules\Whatsapp\Events\WhatsappMessageFailed;
use Modules\Whatsapp\Events\WhatsappMessageQueued;
use Modules\Whatsapp\Events\WhatsappMessageSent;
use Modules\Whatsapp\Jobs\SendInteractiveJob;
use Modules\Whatsapp\Services\Drivers\DriverDoesNotSupport;

uses(Tests\TestCase::class);

/**
 * US-WA-045/046 · SendInteractiveJob — botões reply + list menus.
 *
 * Cobre:
 *  - buttons via driver Z-API → POST send-button-actions
 *  - list via driver Meta Cloud → POST messages com type=interactive
 *  - Baileys CTA URL → DriverDoesNotSupport (falha permanente, sem retry)
 *  - Cross-tenant biz=99 (Tier 0)
 *  - List com 3 sections × 5 items each — payload válido
 *  - Z-API CTA URL → DriverDoesNotSupport (igual Baileys)
 */

beforeEach(function () {
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
        $table->timestamp('lgpd_acknowledged_at')->nullable();
        $table->unsignedInteger('lgpd_acknowledged_by_user_id')->nullable();
        $table->boolean('bot_enabled')->default(false);
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

    // Phone Z-API biz=1
    WhatsappBusinessPhone::withoutGlobalScope(ScopeByBusiness::class)->forceCreate([
        'id' => 10,
        'business_id' => 1,
        'phone_uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'label' => 'Comercial',
        'driver' => 'zapi',
        'fallback_driver' => 'meta_cloud',
        'driver_health' => 'healthy',
        'zapi_instance_id' => 'inst-zapi-1',
        'zapi_instance_token' => 'tok-zapi',
        'zapi_client_token' => 'cli-zapi',
    ]);

    // Phone Meta biz=1
    WhatsappBusinessPhone::withoutGlobalScope(ScopeByBusiness::class)->forceCreate([
        'id' => 11,
        'business_id' => 1,
        'phone_uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'label' => 'Meta Oficial',
        'driver' => 'meta_cloud',
        'fallback_driver' => 'meta_cloud',
        'driver_health' => 'healthy',
        'meta_phone_number_id' => '999000111',
        'meta_access_token' => 'EAAG-test-token',
    ]);

    // Phone Baileys biz=1
    WhatsappBusinessPhone::withoutGlobalScope(ScopeByBusiness::class)->forceCreate([
        'id' => 12,
        'business_id' => 1,
        'phone_uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'label' => 'Baileys',
        'driver' => 'baileys',
        'fallback_driver' => 'meta_cloud',
        'driver_health' => 'healthy',
        'baileys_instance_id' => 'ch-baileys-1',
    ]);

    config([
        'whatsapp.baileys.daemon_url' => 'https://daemon.test',
        'whatsapp.baileys.api_key' => 'test-key',
        'whatsapp.baileys.request_timeout' => 5,
    ]);
});

it('Z-API buttons → POST send-button-actions com payload correto', function () {
    Event::fake([WhatsappMessageQueued::class, WhatsappMessageSent::class]);

    Http::fake([
        'api.z-api.io/*/send-button-actions' => Http::response(['messageId' => 'wamid.ZBTN'], 200),
    ]);

    $job = new SendInteractiveJob(
        businessId: 1,
        whatsappBusinessPhoneId: 10,
        to: '+5511987654321',
        body: 'Confirmar venda?',
        interactive: [
            'type' => 'buttons',
            'buttons' => [
                ['id' => 'sim', 'label' => 'Sim'],
                ['id' => 'nao', 'label' => 'Não'],
            ],
        ],
    );

    $job->handle();

    Event::assertDispatched(WhatsappMessageSent::class);

    Http::assertSent(function ($request) {
        $data = $request->data();
        return str_contains($request->url(), 'send-button-actions')
            && ($data['message'] ?? null) === 'Confirmar venda?'
            && count($data['buttonActions'] ?? []) === 2;
    });

    $msg = WhatsappMessage::withoutGlobalScope(ScopeByBusiness::class)->first();
    expect($msg->type)->toBe('interactive');
    expect($msg->status)->toBe('sent');
    expect($msg->provider_message_id)->toBe('wamid.ZBTN');
    expect($msg->payload['type'])->toBe('buttons');
});

it('Meta Cloud list → POST messages type=interactive subtype=list', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'messages' => [['id' => 'wamid.MTLIST']],
        ], 200),
    ]);

    $sections = [];
    for ($i = 1; $i <= 3; $i++) {
        $items = [];
        for ($j = 1; $j <= 5; $j++) {
            $items[] = ['id' => "s{$i}-i{$j}", 'title' => "Item {$j}", 'description' => "Desc {$j}"];
        }
        $sections[] = ['title' => "Seção {$i}", 'items' => $items];
    }

    $job = new SendInteractiveJob(
        businessId: 1,
        whatsappBusinessPhoneId: 11,
        to: '+5511987654321',
        body: 'Escolha:',
        interactive: ['type' => 'list', 'button_label' => 'Ver', 'sections' => $sections],
    );

    $job->handle();

    Http::assertSent(function ($request) {
        $payload = $request->data();
        return ($payload['type'] ?? null) === 'interactive'
            && ($payload['interactive']['type'] ?? null) === 'list'
            && count($payload['interactive']['action']['sections'] ?? []) === 3;
    });

    $msg = WhatsappMessage::withoutGlobalScope(ScopeByBusiness::class)->first();
    expect($msg->status)->toBe('sent')
        ->and($msg->provider_message_id)->toBe('wamid.MTLIST')
        ->and($msg->payload['type'])->toBe('list');
});

it('Baileys interactive → POST /instances/:id/interactive daemon', function () {
    Http::fake([
        'daemon.test/instances/ch-baileys-1/interactive' => Http::response(
            ['message_id' => 'BAE5INT', 'status' => 'sent'],
            200,
        ),
    ]);

    $job = new SendInteractiveJob(
        businessId: 1,
        whatsappBusinessPhoneId: 12,
        to: '+5511987654321',
        body: 'Qual tamanho?',
        interactive: [
            'type' => 'list',
            'button_label' => 'Tamanhos',
            'sections' => [
                ['title' => 'Linha', 'items' => [
                    ['id' => 'p', 'title' => 'P'],
                    ['id' => 'm', 'title' => 'M'],
                ]],
            ],
        ],
    );

    $job->handle();

    Http::assertSent(fn ($request) => str_contains($request->url(), '/instances/ch-baileys-1/interactive'));

    $msg = WhatsappMessage::withoutGlobalScope(ScopeByBusiness::class)->first();
    expect($msg->status)->toBe('sent')
        ->and($msg->provider_message_id)->toBe('BAE5INT');
});

it('Baileys CTA URL → DriverDoesNotSupport, status=failed sem retry', function () {
    Event::fake([WhatsappMessageFailed::class]);

    $job = new SendInteractiveJob(
        businessId: 1,
        whatsappBusinessPhoneId: 12,
        to: '+5511987654321',
        body: 'Pague:',
        interactive: [
            'type' => 'cta_url',
            'button_label' => 'Pagar',
            'url' => 'https://pay.test/x',
        ],
    );

    // Não deve fazer throw — DriverDoesNotSupport é permanent failure
    $job->handle();

    Event::assertDispatched(WhatsappMessageFailed::class, function ($event) {
        return $event->errorCode === 'driver_does_not_support';
    });

    $msg = WhatsappMessage::withoutGlobalScope(ScopeByBusiness::class)->first();
    expect($msg->status)->toBe('failed')
        ->and($msg->failed_reason)->toContain('cta_url');
});

it('Z-API CTA URL → DriverDoesNotSupport (igual Baileys)', function () {
    Event::fake([WhatsappMessageFailed::class]);

    $job = new SendInteractiveJob(
        businessId: 1,
        whatsappBusinessPhoneId: 10,
        to: '+5511987654321',
        body: 'Pague:',
        interactive: ['type' => 'cta_url', 'button_label' => 'Pagar', 'url' => 'https://x'],
    );

    $job->handle();

    Event::assertDispatched(WhatsappMessageFailed::class);

    $msg = WhatsappMessage::withoutGlobalScope(ScopeByBusiness::class)->first();
    expect($msg->status)->toBe('failed');
});

it('Tier 0 — phone de biz=99 com businessId=1 lança ModelNotFoundException', function () {
    WhatsappBusinessPhone::withoutGlobalScope(ScopeByBusiness::class)->forceCreate([
        'id' => 99,
        'business_id' => 99,
        'phone_uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'label' => 'Cross tenant',
        'driver' => 'null',
        'fallback_driver' => 'null',
        'driver_health' => 'healthy',
    ]);

    $job = new SendInteractiveJob(
        businessId: 1,
        whatsappBusinessPhoneId: 99,
        to: '+5511987654321',
        body: 'X',
        interactive: ['type' => 'buttons', 'buttons' => [['id' => 'a', 'label' => 'A']]],
    );

    expect(fn () => $job->handle())
        ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

    expect(WhatsappMessage::withoutGlobalScope(ScopeByBusiness::class)->count())->toBe(0);
});

it('DriverDoesNotSupport exception expõe driverName + featureKey', function () {
    $ex = DriverDoesNotSupport::for('zapi', 'interactive.cta_url');

    expect($ex)->toBeInstanceOf(\RuntimeException::class)
        ->and($ex->driverName)->toBe('zapi')
        ->and($ex->featureKey)->toBe('interactive.cta_url')
        ->and($ex->getMessage())->toContain('zapi')
        ->and($ex->getMessage())->toContain('interactive.cta_url');
});
