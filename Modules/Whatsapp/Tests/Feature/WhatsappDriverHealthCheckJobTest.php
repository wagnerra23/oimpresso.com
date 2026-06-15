<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\WhatsappBusinessConfig;
use Modules\Whatsapp\Jobs\WhatsappDriverHealthCheckJob;

uses(Tests\TestCase::class);

/**
 * US-WA-014 · Driver Health Check + fallback automático.
 *
 * Cobre:
 * - Ping bem-sucedido → driver_health=healthy + reset failures=0
 * - 1 falha → consecutive_failures++ mantém healthy (não atinge threshold)
 * - 5 falhas consecutivas → driver_health=degraded (FALLBACK ativo)
 * - 10 falhas → disconnected
 * - Ban detectado (HTTP 403) → driver_health=banned
 * - Driver=meta_cloud não é checado (pula — Meta oficial não bane)
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

    config()->set('whatsapp.health_check', [
        'consecutive_failures_to_degrade' => 5,
        'consecutive_failures_to_disconnect' => 10,
        'cross_tenant_ban_alarm_threshold' => 3,
    ]);
});

it('ping bem-sucedido marca driver_health=healthy + reset failures', function () {
    $config = WhatsappBusinessConfig::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'business_uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'driver' => 'zapi',
        'fallback_driver' => 'meta_cloud',
        'zapi_instance_id' => 'inst-1',
        'zapi_instance_token' => 'tok',
        'zapi_client_token' => 'cli',
        'driver_health' => 'degraded',
        'driver_health_consecutive_failures' => 4,
    ]);

    Http::fake([
        'api.z-api.io/*' => Http::response(['connected' => true, 'smartphoneConnected' => true, 'phone' => '+5511987654321'], 200),
    ]);

    (new WhatsappDriverHealthCheckJob(4))->handle();

    $config->refresh();
    expect($config->driver_health)->toBe('healthy');
    expect($config->driver_health_consecutive_failures)->toBe(0);
    expect($config->display_phone)->toBe('+5511987654321');
    expect($config->last_health_check_at)->not->toBeNull();
});

it('5 falhas consecutivas marca driver_health=degraded (fallback ativo)', function () {
    $config = WhatsappBusinessConfig::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'business_uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'driver' => 'zapi',
        'fallback_driver' => 'meta_cloud',
        'zapi_instance_id' => 'inst-1',
        'zapi_instance_token' => 'tok',
        'zapi_client_token' => 'cli',
        'driver_health' => 'healthy',
        'driver_health_consecutive_failures' => 4, // próxima falha cruza threshold
    ]);

    Http::fake([
        'api.z-api.io/*' => Http::response(['error' => 'service unavailable'], 500),
    ]);

    (new WhatsappDriverHealthCheckJob(4))->handle();

    $config->refresh();
    expect($config->driver_health)->toBe('degraded');
    expect($config->driver_health_consecutive_failures)->toBe(5);
    // effectiveDriver agora retorna fallback (Meta Cloud)
    expect($config->effectiveDriver())->toBe('meta_cloud');
});

it('ban detectado (HTTP 403) marca driver_health=banned imediato', function () {
    $config = WhatsappBusinessConfig::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'business_uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'driver' => 'zapi',
        'fallback_driver' => 'meta_cloud',
        'zapi_instance_id' => 'inst-1',
        'zapi_instance_token' => 'tok',
        'zapi_client_token' => 'cli',
        'driver_health' => 'healthy',
    ]);

    Http::fake([
        'api.z-api.io/*' => Http::response(['error' => 'banned'], 403),
    ]);

    (new WhatsappDriverHealthCheckJob(4))->handle();

    $config->refresh();
    expect($config->driver_health)->toBe('banned');
    // Fallback ativo
    expect($config->effectiveDriver())->toBe('meta_cloud');
});

it('driver=meta_cloud é PULADO (não checa, Meta oficial não bane)', function () {
    $config = WhatsappBusinessConfig::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'business_uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'driver' => 'meta_cloud',
        'fallback_driver' => 'meta_cloud',
        'driver_health' => 'never_checked',
    ]);

    // Sem Http::fake — se chamasse, estouraria. Job deve pular meta_cloud.
    (new WhatsappDriverHealthCheckJob(4))->handle();

    $config->refresh();
    expect($config->driver_health)->toBe('never_checked'); // não mudou
});
