<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\WhatsappBusinessConfig;
use Modules\Whatsapp\Http\Controllers\Api\MetaWebhookController;
use Modules\Whatsapp\Http\Controllers\Api\ZapiWebhookController;
use Modules\Whatsapp\Http\Middleware\VerifyMetaSignature;
use Modules\Whatsapp\Http\Middleware\VerifyZapiSignature;
use Modules\Whatsapp\Jobs\ProcessIncomingWebhookJob;

uses(Tests\TestCase::class);

/**
 * R-WA-002 + R-WA-002b · Webhook signature validation (Tier 0 segurança).
 *
 * Cobre:
 * - Meta: HMAC SHA-256 inválido = 401; válido = 200 + Job dispatched
 * - Meta: GET challenge com verify_token correto = 200 + hub.challenge
 * - Z-API: Client-Token inválido = 401; válido = 200 + Job dispatched
 * - business_uuid inexistente = 404
 *
 * Padrão SQLite friendly (cria tabela em beforeEach).
 */

beforeEach(function () {
    // era-sqlite: este teste cria schema manual (sqlite-friendly). No MySQL persistente
    // do nightly isso DROPA tabelas reais → corrompe os testes irmãos (lever do floor SDD).
    // Cobertura real é na lane sqlite (per-PR); pula no MySQL.
    if (config('database.default') !== 'sqlite') {
        $this->markTestSkipped('era-sqlite: corruptor de schema compartilhado no MySQL — sqlite-only no burn-down do floor SDD.');
    }
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

    // Registra rotas + middleware aliases
    app('router')->aliasMiddleware('whatsapp.meta.signature', VerifyMetaSignature::class);
    app('router')->aliasMiddleware('whatsapp.zapi.signature', VerifyZapiSignature::class);

    Route::get('/api/whatsapp/webhook/meta/{business_uuid}', [MetaWebhookController::class, 'verify'])
        ->middleware('whatsapp.meta.signature');
    Route::post('/api/whatsapp/webhook/meta/{business_uuid}', [MetaWebhookController::class, 'handle'])
        ->middleware('whatsapp.meta.signature');
    Route::post('/api/whatsapp/webhook/zapi/{business_uuid}', [ZapiWebhookController::class, 'handle'])
        ->middleware('whatsapp.zapi.signature');
});

it('Meta GET challenge com verify_token correto retorna hub.challenge', function () {
    $uuid = Str::uuid()->toString();
    WhatsappBusinessConfig::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'business_uuid' => $uuid,
        'driver' => 'meta_cloud',
        'meta_webhook_verify_token' => 'meta-verify-secret-123',
    ]);

    $response = $this->get("/api/whatsapp/webhook/meta/{$uuid}?hub_mode=subscribe&hub_verify_token=meta-verify-secret-123&hub_challenge=challenge-abc");

    $response->assertStatus(200);
    expect($response->getContent())->toBe('challenge-abc');
});

it('Meta GET challenge com verify_token errado retorna 403', function () {
    $uuid = Str::uuid()->toString();
    WhatsappBusinessConfig::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'business_uuid' => $uuid,
        'driver' => 'meta_cloud',
        'meta_webhook_verify_token' => 'right-token',
    ]);

    $response = $this->get("/api/whatsapp/webhook/meta/{$uuid}?hub_mode=subscribe&hub_verify_token=WRONG&hub_challenge=challenge-abc");

    $response->assertStatus(403);
});

it('Meta POST sem assinatura retorna 401', function () {
    $uuid = Str::uuid()->toString();
    WhatsappBusinessConfig::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'business_uuid' => $uuid,
        'driver' => 'meta_cloud',
        'meta_app_secret' => 'app-secret-xyz',
    ]);

    $response = $this->postJson("/api/whatsapp/webhook/meta/{$uuid}", ['entry' => []]);

    $response->assertStatus(401);
});

it('Meta POST com HMAC inválido retorna 401', function () {
    $uuid = Str::uuid()->toString();
    WhatsappBusinessConfig::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'business_uuid' => $uuid,
        'driver' => 'meta_cloud',
        'meta_app_secret' => 'app-secret-xyz',
    ]);

    $response = $this->postJson(
        "/api/whatsapp/webhook/meta/{$uuid}",
        ['entry' => []],
        ['X-Hub-Signature-256' => 'sha256=WRONG_HEX']
    );

    $response->assertStatus(401);
});

it('Meta POST com HMAC válido = 200 + Job dispatched', function () {
    Bus::fake([ProcessIncomingWebhookJob::class]);

    $uuid = Str::uuid()->toString();
    $secret = 'app-secret-xyz';
    WhatsappBusinessConfig::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'business_uuid' => $uuid,
        'driver' => 'meta_cloud',
        'meta_app_secret' => $secret,
    ]);

    $body = ['entry' => [['changes' => [['value' => ['messages' => [['id' => 'wamid.X', 'from' => '5511987654321', 'type' => 'text', 'text' => ['body' => 'oi']]]]]]]]];
    $rawBody = json_encode($body);
    $hmac = hash_hmac('sha256', $rawBody, $secret);

    $response = $this->call(
        'POST',
        "/api/whatsapp/webhook/meta/{$uuid}",
        [], [], [],
        ['HTTP_X-Hub-Signature-256' => "sha256={$hmac}", 'CONTENT_TYPE' => 'application/json'],
        $rawBody
    );

    $response->assertStatus(200);
    Bus::assertDispatched(ProcessIncomingWebhookJob::class, fn ($job) => $job->businessId === 4 && $job->provider === 'meta_cloud');
});

it('Z-API POST com z-api-token inválido retorna 401', function () {
    $uuid = Str::uuid()->toString();
    WhatsappBusinessConfig::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'business_uuid' => $uuid,
        'driver' => 'zapi',
        'zapi_instance_token' => 'right-instance-token',
    ]);

    $response = $this->postJson(
        "/api/whatsapp/webhook/zapi/{$uuid}",
        ['messageId' => 'X', 'phone' => '5511987654321', 'fromMe' => false],
        ['z-api-token' => 'WRONG']
    );

    $response->assertStatus(401);
});

it('Z-API POST com z-api-token válido (= zapi_instance_token) = 200 + Job dispatched', function () {
    Bus::fake([ProcessIncomingWebhookJob::class]);

    $uuid = Str::uuid()->toString();
    WhatsappBusinessConfig::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'business_uuid' => $uuid,
        'driver' => 'zapi',
        'zapi_instance_token' => 'right-instance-token',
    ]);

    $response = $this->postJson(
        "/api/whatsapp/webhook/zapi/{$uuid}",
        ['type' => 'ReceivedCallback', 'messageId' => 'msg-X', 'phone' => '5511987654321', 'fromMe' => false, 'text' => ['message' => 'oi']],
        ['z-api-token' => 'right-instance-token']
    );

    $response->assertStatus(200);
    Bus::assertDispatched(ProcessIncomingWebhookJob::class, fn ($job) => $job->provider === 'zapi');
});

it('Webhook com business_uuid inexistente retorna 404', function () {
    $response = $this->postJson(
        '/api/whatsapp/webhook/zapi/00000000-0000-0000-0000-000000000000',
        ['messageId' => 'X', 'phone' => '5511987654321', 'fromMe' => false],
        ['z-api-token' => 'whatever']
    );

    $response->assertStatus(404);
});
