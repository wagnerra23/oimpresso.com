<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Repair\Events\RepairStatusChanged;
use Modules\Whatsapp\Entities\WhatsappBusinessPhone;
use Modules\Whatsapp\Jobs\SendWhatsappMessageJob;
use Modules\Whatsapp\Listeners\NotifyRepairCustomer;

uses(Tests\TestCase::class);

/**
 * US-WA-004 + US-WA-040 · NotifyRepairCustomer — Listener Repair → WhatsApp
 * com roteamento por phone (ADR 0117).
 *
 * Cobre:
 * (a) phone com handles_repair_status=true + cliente + template → Job dispatched
 *     com phone_id correto
 * (b) sem phone configurado → no-op silencioso
 * (c) phone sem flag handles_repair_status nem outbound_default → no-op
 * (d) phone com handles_outbound_default=true (sem específico) → Job dispatched
 *     (fallback default)
 * (e) cliente sem mobile → no-op
 *
 * Padrão SQLite friendly.
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('era-sqlite: schema sintético manual incompatível com MySQL persistente — quarentena Onda 2 SDD floor; burn-down converte depois.');
    }

    foreach (['contacts', 'whatsapp_business_phones'] as $t) {
        Schema::dropIfExists($t);
    }

    Schema::create('contacts', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->string('name', 191)->nullable();
        $table->string('mobile', 60)->nullable();
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
});

it('(a) phone com handles_repair_status + cliente + template → Job dispatched com phone_id correto', function () {
    Bus::fake();

    $contact = \DB::table('contacts')->insertGetId([
        'business_id' => 1,
        'name' => 'João Silva',
        'mobile' => '+5511987654321',
    ]);

    $phone = WhatsappBusinessPhone::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'phone_uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'label' => 'Comercial',
        'driver' => 'zapi',
        'fallback_driver' => 'meta_cloud',
        'zapi_instance_id' => 'inst-test',
        'zapi_instance_token' => 'tok-test',
        'handles_repair_status' => true,
        'template_repair_ready_name' => 'repair_status_ready',
    ]);

    $repair = (object) ['id' => 42, 'business_id' => 1, 'contact_id' => $contact];

    $listener = new NotifyRepairCustomer;
    $listener->handle($repair, 'ready');

    Bus::assertDispatched(SendWhatsappMessageJob::class, function ($job) use ($repair, $phone) {
        return $job->businessId === 1
            && $job->whatsappBusinessPhoneId === $phone->id
            && $job->to === '+5511987654321'
            && $job->kind === 'template'
            && $job->payload['name'] === 'repair_status_ready'
            && $job->payload['params']['customer_name'] === 'João Silva'
            && (string) $job->payload['params']['repair_id'] === (string) $repair->id;
    });
});

it('(b) sem phone configurado → no-op silencioso (zero jobs)', function () {
    Bus::fake();

    $repair = (object) ['id' => 7, 'business_id' => 1, 'contact_id' => 99];

    $listener = new NotifyRepairCustomer;
    $listener->handle($repair, 'ready');

    Bus::assertNothingDispatched();
});

it('(c) phone sem flag handles_repair_status nem handles_outbound_default → no-op', function () {
    Bus::fake();

    $contact = \DB::table('contacts')->insertGetId([
        'business_id' => 1,
        'name' => 'Z',
        'mobile' => '+5511900000000',
    ]);

    WhatsappBusinessPhone::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'phone_uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'label' => 'Sem rotear nada',
        'driver' => 'zapi',
        'fallback_driver' => 'meta_cloud',
        'handles_repair_status' => false,
        'handles_outbound_default' => false,
        'template_repair_ready_name' => 'repair_status_ready',
    ]);

    $repair = (object) ['id' => 11, 'business_id' => 1, 'contact_id' => $contact];

    $listener = new NotifyRepairCustomer;
    $listener->handle($repair, 'ready');

    Bus::assertNothingDispatched();
});

it('(d) phone só com handles_outbound_default=true → Job dispatched (fallback default)', function () {
    Bus::fake();

    $contact = \DB::table('contacts')->insertGetId([
        'business_id' => 1,
        'name' => 'Default',
        'mobile' => '+5511911111111',
    ]);

    $phone = WhatsappBusinessPhone::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'phone_uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'label' => 'Único',
        'driver' => 'zapi',
        'fallback_driver' => 'meta_cloud',
        'handles_repair_status' => false,
        'handles_outbound_default' => true,
        'template_repair_ready_name' => 'repair_default_ready',
    ]);

    $repair = (object) ['id' => 12, 'business_id' => 1, 'contact_id' => $contact];

    $listener = new NotifyRepairCustomer;
    $listener->handle($repair, 'ready');

    Bus::assertDispatched(SendWhatsappMessageJob::class, fn ($job) =>
        $job->whatsappBusinessPhoneId === $phone->id
        && $job->payload['name'] === 'repair_default_ready'
    );
});

it('(e) cliente sem mobile (contact_id=null) → no-op silencioso (zero jobs)', function () {
    Bus::fake();

    WhatsappBusinessPhone::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'phone_uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'label' => 'Comercial',
        'driver' => 'zapi',
        'fallback_driver' => 'meta_cloud',
        'handles_repair_status' => true,
        'template_repair_ready_name' => 'repair_status_ready',
    ]);

    $repair = (object) ['id' => 8, 'business_id' => 1, 'contact_id' => null];

    $listener = new NotifyRepairCustomer;
    $listener->handle($repair, 'ready');

    Bus::assertNothingDispatched();
});

it('phone tem flag mas template não cadastrado → no-op silencioso', function () {
    Bus::fake();

    $contact = \DB::table('contacts')->insertGetId([
        'business_id' => 1,
        'name' => 'A',
        'mobile' => '+5511922222222',
    ]);

    WhatsappBusinessPhone::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'phone_uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'label' => 'Comercial sem template',
        'driver' => 'zapi',
        'fallback_driver' => 'meta_cloud',
        'handles_repair_status' => true,
        'template_repair_ready_name' => null,
    ]);

    $repair = (object) ['id' => 13, 'business_id' => 1, 'contact_id' => $contact];

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

    $phone = WhatsappBusinessPhone::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'phone_uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'label' => 'Comercial',
        'driver' => 'zapi',
        'fallback_driver' => 'meta_cloud',
        'handles_repair_status' => true,
        'template_repair_waiting_parts_name' => 'repair_waiting_parts',
    ]);

    $repair = (object) ['id' => 10, 'business_id' => 1, 'contact_id' => $contact];
    $event = new RepairStatusChanged($repair, 'waiting_parts');

    $listener = new NotifyRepairCustomer;
    $listener->handleEvent($event);

    Bus::assertDispatched(SendWhatsappMessageJob::class, fn ($job) =>
        $job->whatsappBusinessPhoneId === $phone->id
        && $job->payload['name'] === 'repair_waiting_parts'
        && $job->to === '+5548999990000'
    );
});
