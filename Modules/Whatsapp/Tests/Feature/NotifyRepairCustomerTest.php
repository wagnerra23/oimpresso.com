<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Repair\Events\RepairStatusChanged;
use Modules\Whatsapp\Entities\WhatsappBusinessConfig;
use Modules\Whatsapp\Jobs\SendWhatsappMessageJob;
use Modules\Whatsapp\Listeners\NotifyRepairCustomer;

uses(Tests\TestCase::class);

/**
 * US-WA-004 · NotifyRepairCustomer — Listener Repair → dispara WhatsApp.
 *
 * Cobre:
 * (a) com config + cliente válido = SendWhatsappMessageJob dispatched
 * (b) sem WhatsappBusinessConfig = no-op (log info, zero jobs)
 * (c) sem mobile (contact_id=null) = log info + no-op (zero jobs)
 *
 * Padrão SQLite friendly.
 */

beforeEach(function () {
    foreach (['contacts', 'whatsapp_business_configs'] as $t) {
        Schema::dropIfExists($t);
    }

    Schema::create('contacts', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->string('name', 191)->nullable();
        $table->string('mobile', 60)->nullable();
    });

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
});

it('(a) com config + cliente válido + template configurado → dispatcha SendWhatsappMessageJob', function () {
    Bus::fake();

    $contact = \DB::table('contacts')->insertGetId([
        'business_id' => 1,
        'name' => 'João Silva',
        'mobile' => '+5511987654321',
    ]);

    WhatsappBusinessConfig::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'business_uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'driver' => 'zapi',
        'fallback_driver' => 'meta_cloud',
        'zapi_instance_id' => 'inst-test',
        'zapi_instance_token' => 'tok-test',
        'template_repair_ready_name' => 'repair_status_ready',
    ]);

    $repair = (object) [
        'id' => 42,
        'business_id' => 1,
        'contact_id' => $contact,
    ];

    $listener = new NotifyRepairCustomer;
    $listener->handle($repair, 'ready');

    Bus::assertDispatched(SendWhatsappMessageJob::class, function ($job) use ($repair) {
        return $job->businessId === 1
            && $job->to === '+5511987654321'
            && $job->kind === 'template'
            && $job->payload['name'] === 'repair_status_ready'
            && $job->payload['params']['customer_name'] === 'João Silva'
            && (string) $job->payload['params']['repair_id'] === (string) $repair->id;
    });
});

it('(b) sem WhatsappBusinessConfig → no-op silencioso (zero jobs)', function () {
    Bus::fake();

    // Nenhuma config criada — WhatsappBusinessConfig::first() retorna null

    $repair = (object) [
        'id' => 7,
        'business_id' => 1,
        'contact_id' => 99,
    ];

    $listener = new NotifyRepairCustomer;
    $listener->handle($repair, 'ready');

    Bus::assertNothingDispatched();
});

it('(c) sem mobile (contact_id=null) → no-op silencioso (zero jobs)', function () {
    Bus::fake();

    WhatsappBusinessConfig::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'business_uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'driver' => 'zapi',
        'template_repair_ready_name' => 'repair_status_ready',
    ]);

    $repair = (object) [
        'id' => 8,
        'business_id' => 1,
        'contact_id' => null, // sem contato = sem mobile
    ];

    $listener = new NotifyRepairCustomer;
    $listener->handle($repair, 'ready');

    Bus::assertNothingDispatched();
});

it('ignora status que não seja ready/waiting_parts (ex: in_progress)', function () {
    Bus::fake();

    $repair = (object) ['id' => 9, 'business_id' => 1, 'contact_id' => null];

    $listener = new NotifyRepairCustomer;
    $listener->handle($repair, 'in_progress');

    Bus::assertNothingDispatched();
});

it('handleEvent desempacota RepairStatusChanged e delega para handle()', function () {
    Bus::fake();

    $contact = \DB::table('contacts')->insertGetId([
        'business_id' => 1,
        'name' => 'Maria',
        'mobile' => '+5548999990000',
    ]);

    WhatsappBusinessConfig::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'business_uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'driver' => 'zapi',
        'template_repair_waiting_parts_name' => 'repair_waiting_parts',
    ]);

    $repair = (object) ['id' => 10, 'business_id' => 1, 'contact_id' => $contact];
    $event = new RepairStatusChanged($repair, 'waiting_parts');

    $listener = new NotifyRepairCustomer;
    $listener->handleEvent($event);

    Bus::assertDispatched(SendWhatsappMessageJob::class, fn ($job) =>
        $job->payload['name'] === 'repair_waiting_parts'
        && $job->to === '+5548999990000'
    );
});
