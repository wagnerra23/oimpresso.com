<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\WhatsappBusinessConfig;
use Modules\Whatsapp\Http\Controllers\Api\BaileysWebhookController;
use Modules\Whatsapp\Http\Middleware\VerifyBaileysSignature;
use Modules\Whatsapp\Jobs\ProcessIncomingWebhookJob;
use Modules\Whatsapp\Services\Drivers\BaileysDriver;
use Modules\Whatsapp\Services\Drivers\DriverFactory;

uses(Tests\TestCase::class);

/**
 * US-WA-002d · BaileysDriver custom (Sprint 3 — ADR 0096 emenda 4).
 *
 * Cobre:
 *  - sendFreeform sucesso → WhatsappSendResult::ok com message_id
 *  - sendFreeform 404 instance_not_found → sessionLost=true
 *  - sendFreeform 401 → sessionLost=true
 *  - ban detection (body contém "banned") → banDetected=true
 *  - ping connected = healthy + display_phone
 *  - ping qr_required = unhealthy
 *  - ping banned = unhealthy + banDetected
 *  - DriverFactory resolve BaileysDriver quando driver=baileys
 *  - Webhook Bearer inválido = 401
 *  - Webhook Bearer válido + event=message = 200 + ProcessIncomingWebhookJob dispatched
 *  - Webhook event=ban_detected atualiza driver_health=banned
 *
 * SQLite-friendly: cria a tabela em beforeEach (mesmo padrão WebhookSignatureTest).
 */

beforeEach(function () {
    Schema::dropIfExists('whatsapp_business_configs');
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

    app('router')->aliasMiddleware('whatsapp.baileys.signature', VerifyBaileysSignature::class);
    Route::post('/api/whatsapp/webhook/baileys/{business_uuid}', [BaileysWebhookController::class, 'handle'])
        ->middleware('whatsapp.baileys.signature');
});

function makeBaileysConfig(array $overrides = []): WhatsappBusinessConfig
{
    return WhatsappBusinessConfig::withoutGlobalScope(ScopeByBusiness::class)->create(array_merge([
        'business_id' => 4,
        'business_uuid' => Str::uuid()->toString(),
        'driver' => 'baileys',
        'fallback_driver' => 'meta_cloud',
        'baileys_instance_id' => 'biz4-main',
        'baileys_daemon_url' => 'https://daemon.test',
        'baileys_api_key' => 'test-bearer-token-min16chars',
    ], $overrides));
}

// ---------- Driver: send ----------

it('sendFreeform sucesso retorna message_id', function () {
    Http::fake([
        'daemon.test/instances/biz4-main/text' => Http::response(['message_id' => 'BAE5XYZ', 'status' => 'sent'], 200),
    ]);

    $config = makeBaileysConfig();
    $driver = app(BaileysDriver::class);
    $result = $driver->sendFreeform($config, '+5511987654321', 'oi');

    expect($result->success)->toBeTrue()
        ->and($result->providerMessageId)->toBe('BAE5XYZ')
        ->and($result->sessionLost)->toBeFalse()
        ->and($result->banDetected)->toBeFalse();
});

it('sendFreeform 404 instance_not_found marca sessionLost', function () {
    Http::fake([
        'daemon.test/*' => Http::response(['error' => 'instance_not_found'], 404),
    ]);

    $config = makeBaileysConfig();
    $result = app(BaileysDriver::class)->sendFreeform($config, '+5511987654321', 'oi');

    expect($result->success)->toBeFalse()
        ->and($result->errorCode)->toBe('baileys_404')
        ->and($result->sessionLost)->toBeTrue()
        ->and($result->banDetected)->toBeFalse();
});

it('sendFreeform 401 marca sessionLost', function () {
    Http::fake([
        'daemon.test/*' => Http::response(['error' => 'unauthorized'], 401),
    ]);

    $config = makeBaileysConfig();
    $result = app(BaileysDriver::class)->sendFreeform($config, '+5511987654321', 'oi');

    expect($result->success)->toBeFalse()
        ->and($result->sessionLost)->toBeTrue();
});

it('sendFreeform com body "banned" marca banDetected', function () {
    Http::fake([
        'daemon.test/*' => Http::response('Connection Closed: banned', 503),
    ]);

    $config = makeBaileysConfig();
    $result = app(BaileysDriver::class)->sendFreeform($config, '+5511987654321', 'oi');

    expect($result->success)->toBeFalse()
        ->and($result->banDetected)->toBeTrue();
});

// ---------- Driver: ping ----------

it('ping retorna healthy quando state=connected', function () {
    Http::fake([
        'daemon.test/instances/biz4-main/status' => Http::response([
            'state' => 'connected',
            'display_phone' => '5511987654321',
        ], 200),
    ]);

    $health = app(BaileysDriver::class)->ping(makeBaileysConfig());

    expect($health->healthy)->toBeTrue()
        ->and($health->displayPhone)->toBe('5511987654321')
        ->and($health->sessionState)->toBe('connected')
        ->and($health->banDetected)->toBeFalse();
});

it('ping retorna unhealthy quando state=qr_required', function () {
    Http::fake([
        'daemon.test/instances/biz4-main/status' => Http::response(['state' => 'qr_required'], 200),
    ]);

    $health = app(BaileysDriver::class)->ping(makeBaileysConfig());

    expect($health->healthy)->toBeFalse()
        ->and($health->sessionState)->toBe('qr_required')
        ->and($health->banDetected)->toBeFalse();
});

it('ping com state=banned marca banDetected', function () {
    Http::fake([
        'daemon.test/instances/biz4-main/status' => Http::response([
            'state' => 'banned',
            'ban_reason' => 'logged_out',
        ], 200),
    ]);

    $health = app(BaileysDriver::class)->ping(makeBaileysConfig());

    expect($health->healthy)->toBeFalse()
        ->and($health->banDetected)->toBeTrue()
        ->and($health->sessionState)->toBe('banned');
});

it('ping sem baileys_instance_id retorna unhealthy', function () {
    $config = makeBaileysConfig(['baileys_instance_id' => null]);
    $health = app(BaileysDriver::class)->ping($config);

    expect($health->healthy)->toBeFalse()
        ->and($health->sessionState)->toBe('disconnected');
});

// ---------- DriverFactory ----------

it('DriverFactory resolve BaileysDriver quando driver=baileys', function () {
    $config = makeBaileysConfig(['driver_health' => 'healthy']);
    $driver = DriverFactory::make($config);

    expect($driver)->toBeInstanceOf(BaileysDriver::class);
});

it('DriverFactory aplica fallback Meta Cloud quando Baileys degraded', function () {
    $config = makeBaileysConfig([
        'driver_health' => 'degraded',
        'fallback_driver' => 'meta_cloud',
    ]);
    $driver = DriverFactory::make($config);

    expect($driver)->toBeInstanceOf(\Modules\Whatsapp\Services\Drivers\MetaCloudDriver::class);
});

// ---------- Webhook ----------

it('Webhook Bearer inválido retorna 401', function () {
    $config = makeBaileysConfig();

    $response = $this->postJson(
        "/api/whatsapp/webhook/baileys/{$config->business_uuid}",
        ['event' => 'message', 'data' => []],
        ['Authorization' => 'Bearer WRONG_TOKEN']
    );

    $response->assertStatus(401);
});

it('Webhook Bearer válido + event=message dispara Job', function () {
    Bus::fake([ProcessIncomingWebhookJob::class]);
    $config = makeBaileysConfig();

    $response = $this->postJson(
        "/api/whatsapp/webhook/baileys/{$config->business_uuid}",
        [
            'instance_id' => 'biz4-main',
            'event' => 'message',
            'data' => ['key' => ['id' => 'BAE5INBOUND'], 'message' => ['conversation' => 'oi']],
        ],
        ['Authorization' => "Bearer {$config->baileys_api_key}"]
    );

    $response->assertStatus(200);
    Bus::assertDispatched(ProcessIncomingWebhookJob::class, function ($job) use ($config) {
        return $job->businessId === $config->business_id && $job->provider === 'baileys';
    });
});

it('Webhook event=ban_detected marca driver_health=banned', function () {
    $config = makeBaileysConfig();

    $response = $this->postJson(
        "/api/whatsapp/webhook/baileys/{$config->business_uuid}",
        [
            'instance_id' => 'biz4-main',
            'event' => 'ban_detected',
            'data' => ['reason' => 'logged_out'],
        ],
        ['Authorization' => "Bearer {$config->baileys_api_key}"]
    );

    $response->assertStatus(200);

    $config->refresh();
    expect($config->driver_health)->toBe('banned')
        ->and($config->last_health_message)->toContain('logged_out');
});

it('Webhook event=connected marca driver_health=healthy + display_phone', function () {
    $config = makeBaileysConfig(['driver_health' => 'never_checked']);

    $response = $this->postJson(
        "/api/whatsapp/webhook/baileys/{$config->business_uuid}",
        [
            'instance_id' => 'biz4-main',
            'event' => 'connected',
            'data' => ['display_phone' => '5511987654321'],
        ],
        ['Authorization' => "Bearer {$config->baileys_api_key}"]
    );

    $response->assertStatus(200);

    $config->refresh();
    expect($config->driver_health)->toBe('healthy')
        ->and($config->display_phone)->toBe('5511987654321')
        ->and($config->driver_health_consecutive_failures)->toBe(0);
});

it('Webhook business_uuid inexistente retorna 404', function () {
    $response = $this->postJson(
        '/api/whatsapp/webhook/baileys/' . Str::uuid()->toString(),
        ['event' => 'message', 'data' => []],
        ['Authorization' => 'Bearer any-token']
    );

    $response->assertStatus(404);
});
