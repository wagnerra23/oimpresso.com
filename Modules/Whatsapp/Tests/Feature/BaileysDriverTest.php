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
 * US-WA-002d + US-WA-022 · BaileysDriver custom + UX simplificada.
 *
 * Cobre:
 *  - send/ping com daemon_url + api_key globais (US-WA-022)
 *  - webhook Bearer global (não mais per-tenant)
 *  - DriverFactory resolve baileys
 *  - Schema atualizada: phone_e164/verified_name/profile_pic_url + UNIQUE
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
        // US-WA-022: instance_id auto-gerado, phone E.164 + perfil sincronizado
        $table->string('baileys_instance_id', 64)->nullable();
        $table->string('baileys_phone_e164', 20)->nullable();
        $table->string('baileys_verified_name', 100)->nullable();
        $table->string('baileys_profile_pic_url', 255)->nullable();
        $table->unique(['business_id', 'baileys_phone_e164'], 'wbc_biz_phone_unq');
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

    // Daemon URL + api key são GLOBAIS agora (US-WA-022)
    config([
        'whatsapp.baileys.daemon_url' => 'https://daemon.test',
        'whatsapp.baileys.api_key' => 'test-bearer-token-min16chars',
        'whatsapp.baileys.request_timeout' => 5,
        'whatsapp.baileys.connect_rate_limit_per_day' => 3,
    ]);

    app('router')->aliasMiddleware('whatsapp.baileys.signature', VerifyBaileysSignature::class);
    Route::post('/api/whatsapp/webhook/baileys/{business_uuid}', [BaileysWebhookController::class, 'handle'])
        ->middleware('whatsapp.baileys.signature');
});

function makeBaileysConfig(array $overrides = []): WhatsappBusinessConfig
{
    return WhatsappBusinessConfig::withoutGlobalScope(ScopeByBusiness::class)->create(array_merge([
        'business_id' => 1,
        'business_uuid' => Str::uuid()->toString(),
        'driver' => 'baileys',
        'fallback_driver' => 'meta_cloud',
        'baileys_instance_id' => 'biz1-main',
        'baileys_phone_e164' => '+5511987654321',
    ], $overrides));
}

// ---------- Driver: send ----------

it('sendFreeform sucesso retorna message_id', function () {
    Http::fake([
        'daemon.test/instances/biz1-main/text' => Http::response(['message_id' => 'BAE5XYZ', 'status' => 'sent'], 200),
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

    $result = app(BaileysDriver::class)->sendFreeform(makeBaileysConfig(), '+5511987654321', 'oi');

    expect($result->success)->toBeFalse()
        ->and($result->sessionLost)->toBeTrue();
});

it('sendFreeform com body "banned" marca banDetected', function () {
    Http::fake([
        'daemon.test/*' => Http::response('Connection Closed: banned', 503),
    ]);

    $result = app(BaileysDriver::class)->sendFreeform(makeBaileysConfig(), '+5511987654321', 'oi');

    expect($result->success)->toBeFalse()
        ->and($result->banDetected)->toBeTrue();
});

// ---------- Driver: ping ----------

it('ping retorna healthy quando state=connected', function () {
    Http::fake([
        'daemon.test/instances/biz1-main/status' => Http::response([
            'state' => 'connected',
            'display_phone' => '5511987654321',
        ], 200),
    ]);

    $health = app(BaileysDriver::class)->ping(makeBaileysConfig());

    expect($health->healthy)->toBeTrue()
        ->and($health->displayPhone)->toBe('5511987654321')
        ->and($health->sessionState)->toBe('connected');
});

it('ping retorna unhealthy quando state=qr_required', function () {
    Http::fake([
        'daemon.test/instances/biz1-main/status' => Http::response(['state' => 'qr_required'], 200),
    ]);

    $health = app(BaileysDriver::class)->ping(makeBaileysConfig());

    expect($health->healthy)->toBeFalse()
        ->and($health->sessionState)->toBe('qr_required');
});

it('ping com state=banned marca banDetected', function () {
    Http::fake([
        'daemon.test/instances/biz1-main/status' => Http::response([
            'state' => 'banned',
            'ban_reason' => 'logged_out',
        ], 200),
    ]);

    $health = app(BaileysDriver::class)->ping(makeBaileysConfig());

    expect($health->healthy)->toBeFalse()
        ->and($health->banDetected)->toBeTrue();
});

it('ping sem instance_id retorna unhealthy', function () {
    $config = makeBaileysConfig(['baileys_instance_id' => null]);
    $health = app(BaileysDriver::class)->ping($config);

    expect($health->healthy)->toBeFalse()
        ->and($health->sessionState)->toBe('disconnected')
        ->and($health->errorMessage)->toContain('BaileysConnectJob');
});

it('ping sem api_key global retorna unhealthy', function () {
    config(['whatsapp.baileys.api_key' => '']);

    $health = app(BaileysDriver::class)->ping(makeBaileysConfig());

    expect($health->healthy)->toBeFalse()
        ->and($health->errorMessage)->toContain('WHATSAPP_BAILEYS_API_KEY');
});

// ---------- DriverFactory ----------

it('DriverFactory resolve BaileysDriver quando driver=baileys', function () {
    $config = makeBaileysConfig(['driver_health' => 'healthy']);
    expect(DriverFactory::make($config))->toBeInstanceOf(BaileysDriver::class);
});

it('DriverFactory aplica fallback Meta Cloud quando Baileys degraded', function () {
    $config = makeBaileysConfig(['driver_health' => 'degraded', 'fallback_driver' => 'meta_cloud']);
    expect(DriverFactory::make($config))->toBeInstanceOf(\Modules\Whatsapp\Services\Drivers\MetaCloudDriver::class);
});

// ---------- Webhook (Bearer agora é global) ----------

it('Webhook Bearer inválido retorna 401', function () {
    $config = makeBaileysConfig();

    $response = $this->postJson(
        "/api/whatsapp/webhook/baileys/{$config->business_uuid}",
        ['event' => 'message', 'data' => []],
        ['Authorization' => 'Bearer WRONG_TOKEN']
    );

    $response->assertStatus(401);
});

it('Webhook Bearer válido (global) + event=message dispara Job', function () {
    Bus::fake([ProcessIncomingWebhookJob::class]);
    $config = makeBaileysConfig();

    $response = $this->postJson(
        "/api/whatsapp/webhook/baileys/{$config->business_uuid}",
        [
            'instance_id' => 'biz1-main',
            'event' => 'message',
            'data' => ['key' => ['id' => 'BAE5INBOUND'], 'message' => ['conversation' => 'oi']],
        ],
        ['Authorization' => 'Bearer ' . config('whatsapp.baileys.api_key')]
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
        ['instance_id' => 'biz1-main', 'event' => 'ban_detected', 'data' => ['reason' => 'logged_out']],
        ['Authorization' => 'Bearer ' . config('whatsapp.baileys.api_key')]
    );

    $response->assertStatus(200);
    $config->refresh();
    expect($config->driver_health)->toBe('banned');
});

it('Webhook event=connected sincroniza display_phone + verified_name + profile_pic', function () {
    $config = makeBaileysConfig(['driver_health' => 'never_checked']);

    $response = $this->postJson(
        "/api/whatsapp/webhook/baileys/{$config->business_uuid}",
        [
            'instance_id' => 'biz1-main',
            'event' => 'connected',
            'data' => [
                'display_phone' => '5511987654321',
                'verified_name' => 'Office Impresso',
                'profile_pic_url' => 'https://example.com/pic.jpg',
            ],
        ],
        ['Authorization' => 'Bearer ' . config('whatsapp.baileys.api_key')]
    );

    $response->assertStatus(200);
    $config->refresh();
    expect($config->driver_health)->toBe('healthy')
        ->and($config->display_phone)->toBe('5511987654321')
        ->and($config->baileys_verified_name)->toBe('Office Impresso')
        ->and($config->baileys_profile_pic_url)->toBe('https://example.com/pic.jpg');
});

it('Webhook business_uuid inexistente retorna 404', function () {
    $response = $this->postJson(
        '/api/whatsapp/webhook/baileys/' . Str::uuid()->toString(),
        ['event' => 'message', 'data' => []],
        ['Authorization' => 'Bearer ' . config('whatsapp.baileys.api_key')]
    );

    $response->assertStatus(404);
});
