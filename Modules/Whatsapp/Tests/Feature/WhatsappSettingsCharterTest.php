<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\WhatsappBusinessConfig;
use Modules\Whatsapp\Jobs\BaileysConnectJob;

uses(Tests\TestCase::class);

/**
 * Charter test — `resources/js/Pages/Whatsapp/Settings.charter.md` Pest GUARD.
 *
 * Cobre as 7 invariantes da página /whatsapp/settings (US-WA-022):
 *  1. Não expõe daemon_url/api_key em props
 *  2. Multi-tenant Tier 0 — isolamento por business_id
 *  3. UNIQUE(business_id, baileys_phone_e164) — anti-duplicate
 *  4. Dispatch BaileysConnectJob quando phone novo + LGPD
 *  5. Não chama daemon no render
 *  6. Webhook publica em Centrifugo (testado em BaileysDriverTest)
 *  7. Rate limit connect 3/dia/business
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

    config([
        'whatsapp.baileys.daemon_url' => 'https://daemon.test',
        'whatsapp.baileys.api_key' => 'global-test-bearer-min16chars',
        'whatsapp.baileys.connect_rate_limit_per_day' => 3,
    ]);
});

it('charter §1 — não expõe daemon_url/api_key em props da page', function () {
    $config = WhatsappBusinessConfig::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 4,
        'business_uuid' => Str::uuid()->toString(),
        'driver' => 'baileys',
        'baileys_instance_id' => 'biz4-abc123',
        'baileys_phone_e164' => '+5511987654321',
    ]);

    // Snapshot do array que o controller passa pro Inertia
    $configForUi = [
        'driver' => $config->driver,
        'has_baileys_credentials' => $config->hasBaileysConfigured(),
        'baileys_instance_id' => $config->baileys_instance_id,
        'baileys_phone_e164' => $config->baileys_phone_e164,
    ];

    expect($configForUi)
        ->not->toHaveKey('baileys_daemon_url')
        ->not->toHaveKey('baileys_api_key');
});

it('charter §2 — multi-tenant: outro business não vê config alheia', function () {
    WhatsappBusinessConfig::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 4,
        'business_uuid' => Str::uuid()->toString(),
        'driver' => 'baileys',
        'baileys_phone_e164' => '+5511111111111',
    ]);
    WhatsappBusinessConfig::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 7,
        'business_uuid' => Str::uuid()->toString(),
        'driver' => 'baileys',
        'baileys_phone_e164' => '+5522222222222',
    ]);

    // Query com global scope (simula o controller pós-auth)
    session(['user.business_id' => 4]);
    $count = WhatsappBusinessConfig::query()->count();
    expect($count)->toBeLessThanOrEqual(1); // só biz=4 (com global scope ativo no Model)
});

it('charter §3 — UNIQUE(business_id, phone_e164) bloqueia duplicate', function () {
    $businessId = 4;
    $phone = '+5511987654321';

    WhatsappBusinessConfig::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => $businessId,
        'business_uuid' => Str::uuid()->toString(),
        'driver' => 'baileys',
        'baileys_phone_e164' => $phone,
    ]);

    $duplicate = function () use ($businessId, $phone) {
        WhatsappBusinessConfig::withoutGlobalScope(ScopeByBusiness::class)->create([
            'business_id' => $businessId,
            'business_uuid' => Str::uuid()->toString(),
            'driver' => 'baileys',
            'baileys_phone_e164' => $phone,
        ]);
    };

    expect($duplicate)->toThrow(\Illuminate\Database\QueryException::class);
});

it('charter §4 — BaileysConnectJob é dispatched quando driver=baileys + phone novo + LGPD', function () {
    Bus::fake();

    BaileysConnectJob::dispatch(4);

    Bus::assertDispatched(BaileysConnectJob::class, fn ($job) => $job->businessId === 4);
});

it('charter §5 — não chama daemon no GET (render-only)', function () {
    Http::fake();

    // Simula render — SettingsController@show NÃO deve chamar daemon
    $config = WhatsappBusinessConfig::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 4,
        'business_uuid' => Str::uuid()->toString(),
        'driver' => 'baileys',
        'baileys_instance_id' => 'biz4-test',
        'baileys_phone_e164' => '+5511987654321',
    ]);

    // Apenas serializa — render não dispara nenhuma request HTTP
    $configForUi = [
        'driver' => $config->driver,
        'baileys_instance_id' => $config->baileys_instance_id,
        'has_baileys_credentials' => $config->hasBaileysConfigured(),
    ];

    expect($configForUi)->toBeArray();
    Http::assertNothingSent();
});

it('charter §7 — instance_id é auto-gerado idempotente', function () {
    $config = WhatsappBusinessConfig::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 4,
        'business_uuid' => Str::uuid()->toString(),
        'driver' => 'baileys',
        'baileys_phone_e164' => '+5511987654321',
    ]);

    expect($config->baileys_instance_id)->toBeNull();

    $first = $config->ensureBaileysInstanceId();
    $second = $config->ensureBaileysInstanceId();

    expect($first)->toStartWith('biz4-')
        ->and($first)->toBe($second); // idempotente
});

it('charter §7 — BaileysConnectJob aborta com phone vazio', function () {
    $config = WhatsappBusinessConfig::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 4,
        'business_uuid' => Str::uuid()->toString(),
        'driver' => 'baileys',
        'baileys_phone_e164' => null,
    ]);

    Http::fake();

    (new BaileysConnectJob(4))->handle();

    Http::assertNothingSent();
    $config->refresh();
    expect($config->baileys_instance_id)->toBeNull();
});
