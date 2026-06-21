<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\WhatsappBusinessConfig;
use Modules\Whatsapp\Entities\WhatsappBusinessPhone;
use Modules\Whatsapp\Services\Drivers\NotImplementedDriverException;
use Modules\Whatsapp\Services\Drivers\DriverFactory;
use Modules\Whatsapp\Services\Drivers\MetaCloudDriver;
use Modules\Whatsapp\Services\Drivers\NullDriver;
use Modules\Whatsapp\Services\Drivers\ZapiDriver;

uses(Tests\TestCase::class);

/**
 * PR 2a US-WA-040 · Driver Layer aceita Config legacy E Phone novo via union type.
 *
 * ADR 0117 emite contrato: drivers acessam apenas campos comuns aos 2 models
 * (driver, fallback_driver, meta_*, zapi_*, baileys_*, business_id), então
 * ambos types funcionam transparente. Este test valida o contrato em runtime
 * exercitando DriverFactory + NullDriver com instâncias dos 2 models.
 *
 * Não exercita Meta/Zapi/Baileys diretamente porque envolveria HTTP fakes
 * (já cobertos em testes específicos de cada driver com Config legacy).
 * O essencial é provar que `make(Config)` e `make(Phone)` resolvem o mesmo
 * driver quando configuração é equivalente.
 */

beforeEach(function () {
    // era-sqlite: este teste cria schema manual (sqlite-friendly). No MySQL persistente
    // do nightly isso DROPA tabelas reais → corrompe os testes irmãos (lever do floor SDD).
    // Cobertura real é na lane sqlite (per-PR); pula no MySQL.
    if (config('database.default') !== 'sqlite') {
        $this->markTestSkipped('era-sqlite: corruptor de schema compartilhado no MySQL — sqlite-only no burn-down do floor SDD.');
    }
    Schema::dropIfExists('whatsapp_business_phones');
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
        $table->string('baileys_phone_e164', 20)->nullable();
        $table->string('baileys_verified_name', 100)->nullable();
        $table->string('baileys_profile_pic_url', 255)->nullable();
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

    config(['whatsapp.forbidden_drivers' => ['evolution']]);
});

it('DriverFactory::make resolve driver correto a partir de Config legacy', function () {
    $config = WhatsappBusinessConfig::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'business_uuid' => (string) Str::uuid(),
        'driver' => 'null',
        'fallback_driver' => 'meta_cloud',
        'driver_health' => 'healthy',
    ]);

    $driver = DriverFactory::make($config);
    expect($driver)->toBeInstanceOf(NullDriver::class);
});

it('DriverFactory::make resolve driver correto a partir de WhatsappBusinessPhone', function () {
    $phone = WhatsappBusinessPhone::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'phone_uuid' => (string) Str::uuid(),
        'label' => 'Comercial',
        'driver' => 'null',
        'fallback_driver' => 'meta_cloud',
        'driver_health' => 'healthy',
    ]);

    $driver = DriverFactory::make($phone);
    expect($driver)->toBeInstanceOf(NullDriver::class);
});

it('DriverFactory::make aplica fallback automático em Phone igual ao Config', function () {
    $phone = WhatsappBusinessPhone::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'phone_uuid' => (string) Str::uuid(),
        'label' => 'Comercial',
        'driver' => 'zapi',
        'fallback_driver' => 'null',
        'driver_health' => 'degraded',
    ]);

    $driver = DriverFactory::make($phone);
    // degraded → fallback (null) — não retorna ZapiDriver
    expect($driver)->toBeInstanceOf(NullDriver::class);
});

it('DriverFactory::makePrimary ignora fallback em Phone (mesma semantica de Config)', function () {
    $phone = WhatsappBusinessPhone::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'phone_uuid' => (string) Str::uuid(),
        'label' => 'Comercial',
        'driver' => 'null',
        'fallback_driver' => 'meta_cloud',
        'driver_health' => 'degraded',
    ]);

    // makePrimary IGNORA driver_health, sempre usa primário
    $driver = DriverFactory::makePrimary($phone);
    expect($driver)->toBeInstanceOf(NullDriver::class);
});

it('DriverFactory::make com driver banido em Phone também rejeita', function () {
    $phone = WhatsappBusinessPhone::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'phone_uuid' => (string) Str::uuid(),
        'label' => 'Comercial',
        'driver' => 'evolution',
        'fallback_driver' => 'meta_cloud',
        'driver_health' => 'healthy',
    ]);

    expect(fn () => DriverFactory::make($phone))
        ->toThrow(\InvalidArgumentException::class, 'forbidden_drivers');
});

it('NullDriver aceita instâncias Phone via DriverInterface union', function () {
    $phone = WhatsappBusinessPhone::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'phone_uuid' => (string) Str::uuid(),
        'label' => 'Comercial',
        'driver' => 'null',
        'fallback_driver' => 'null',
    ]);

    $driver = new NullDriver();
    $sendResult = $driver->sendFreeform($phone, '+5511987654321', 'olá teste');
    expect($sendResult->ok)->toBeTrue();
    expect($sendResult->providerMessageId)->toStartWith('null-freeform-');

    $health = $driver->ping($phone);
    expect($health->healthy)->toBeTrue();
});

it('NullDriver continua aceitando Config legacy (backward compat)', function () {
    $config = WhatsappBusinessConfig::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'business_uuid' => (string) Str::uuid(),
        'driver' => 'null',
        'fallback_driver' => 'null',
    ]);

    $driver = new NullDriver();
    $sendResult = $driver->sendFreeform($config, '+5511987654321', 'olá legacy');
    expect($sendResult->ok)->toBeTrue();

    $health = $driver->ping($config);
    expect($health->healthy)->toBeTrue();
});

it('Config e Phone equivalentes resolvem mesmo driver', function () {
    $config = WhatsappBusinessConfig::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'business_uuid' => (string) Str::uuid(),
        'driver' => 'meta_cloud',
        'fallback_driver' => 'meta_cloud',
        'driver_health' => 'healthy',
    ]);

    $phone = WhatsappBusinessPhone::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'phone_uuid' => (string) Str::uuid(),
        'label' => 'Comercial',
        'driver' => 'meta_cloud',
        'fallback_driver' => 'meta_cloud',
        'driver_health' => 'healthy',
    ]);

    $driverFromConfig = DriverFactory::make($config);
    $driverFromPhone = DriverFactory::make($phone);

    expect($driverFromConfig)->toBeInstanceOf(MetaCloudDriver::class);
    expect($driverFromPhone)->toBeInstanceOf(MetaCloudDriver::class);
    expect($driverFromPhone::class)->toBe($driverFromConfig::class);
});

it('Phone com driver=zapi resolve ZapiDriver', function () {
    $phone = WhatsappBusinessPhone::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'phone_uuid' => (string) Str::uuid(),
        'label' => 'Comercial',
        'driver' => 'zapi',
        'fallback_driver' => 'meta_cloud',
        'driver_health' => 'healthy',
    ]);

    expect(DriverFactory::make($phone))->toBeInstanceOf(ZapiDriver::class);
});

it('Phone com driver=baileys lança NotImplementedDriverException (ADR 0202 descontinuado)', function () {
    $phone = WhatsappBusinessPhone::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'phone_uuid' => (string) Str::uuid(),
        'label' => 'Legacy',
        'driver' => 'baileys',
        'fallback_driver' => 'meta_cloud',
        'driver_health' => 'healthy',
    ]);

    expect(fn () => DriverFactory::make($phone))->toThrow(NotImplementedDriverException::class);
});
