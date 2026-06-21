<?php

declare(strict_types=1);

use App\Contact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Entities\Conversation;
use Modules\Whatsapp\Http\Controllers\Admin\InboxController;

uses(Tests\TestCase::class);

/**
 * R-WA-064 — GUARD tests pra vincular Contact UltimatePOS à Conversation (US-WA-064).
 *
 * Wagner 2026-05-11: "Opções de dados do contato: adicionar..."
 *
 * Cobre:
 *  001. searchContacts filtra por business_id (NÃO vaza CRM cross-tenant)
 *  002. searchContacts requer min 2 chars (anti-spam)
 *  003. searchContacts busca em name/mobile/landline/email/supplier_business_name
 *  004. linkContact persiste contact_id válido do mesmo business
 *  005. Tier 0: linkContact com contact_id cross-tenant retorna null (silently dropped)
 *  006. linkContact null desvincula (contact_id = null)
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('era-sqlite: schema sintético manual incompatível com MySQL persistente — quarentena Onda 2 SDD floor; burn-down converte depois.');
    }

    foreach (['conversations', 'channels', 'messages', 'contacts', 'activity_log'] as $t) {
        Schema::dropIfExists($t);
    }

    // Spatie LogsActivity trait do Contact escreve aqui em cada save —
    // sem esta tabela o test quebra com SQLSTATE 1 no such table.
    Schema::create('activity_log', function ($table) {
        $table->bigIncrements('id');
        $table->string('log_name')->nullable();
        $table->text('description')->nullable();
        $table->unsignedBigInteger('subject_id')->nullable();
        $table->string('subject_type')->nullable();
        $table->unsignedBigInteger('causer_id')->nullable();
        $table->string('causer_type')->nullable();
        $table->text('properties')->nullable();
        $table->uuid('batch_uuid')->nullable();
        $table->string('event')->nullable();
        $table->timestamps();
    });

    // Schema minimal pro Contact UltimatePOS (só campos usados nos tests).
    Schema::create('contacts', function ($table) {
        $table->increments('id');
        $table->unsignedInteger('business_id');
        $table->string('name', 191);
        $table->string('mobile', 191)->nullable();
        $table->string('landline', 191)->nullable();
        $table->string('alternate_number', 191)->nullable();
        $table->string('email', 191)->nullable();
        $table->string('type', 191)->default('customer');
        $table->string('contact_type', 191)->nullable();
        $table->string('supplier_business_name', 191)->nullable();
        $table->timestamp('deleted_at')->nullable();
        $table->timestamps();
    });

    Schema::create('channels', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->uuid('channel_uuid')->unique();
        $table->string('label', 80);
        $table->string('type', 30);
        $table->string('status', 20)->default('setup');
        $table->string('display_identifier', 100)->nullable();
        $table->text('config_json')->nullable();
        $table->boolean('handles_repair_status')->default(false);
        $table->boolean('handles_billing')->default(false);
        $table->boolean('handles_jana_bot')->default(true);
        $table->boolean('handles_outbound_default')->default(false);
        $table->boolean('bot_enabled')->default(false);
        $table->string('template_repair_ready_name', 64)->nullable();
        $table->string('template_repair_waiting_parts_name', 64)->nullable();
        $table->string('template_billing_due_name', 64)->nullable();
        $table->string('template_billing_paid_name', 64)->nullable();
        $table->string('channel_health', 20)->default('never_checked');
        $table->unsignedInteger('channel_health_consecutive_failures')->default(0);
        $table->timestamp('last_health_check_at')->nullable();
        $table->text('last_health_message')->nullable();
        $table->timestamp('lgpd_acknowledged_at')->nullable();
        $table->unsignedInteger('lgpd_acknowledged_by_user_id')->nullable();
        $table->timestamps();
    });

    Schema::create('conversations', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->unsignedBigInteger('channel_id');
        $table->unsignedInteger('contact_id')->nullable();
        $table->string('customer_external_id', 150);
        $table->string('contact_name', 120)->nullable();
        $table->string('status', 20)->default('open');
        $table->unsignedInteger('assigned_user_id')->nullable();
        $table->boolean('bot_handling')->default(false);
        $table->timestamp('last_inbound_at')->nullable();
        $table->timestamp('last_outbound_at')->nullable();
        $table->timestamp('last_message_at')->nullable();
        $table->unsignedInteger('unread_count')->default(0);
        $table->timestamps();
    });
});

it('R-WA-064-001 — searchContacts filtra por business_id (NAO vaza CRM cross-tenant)', function () {
    session()->put('user.business_id', 1);

    // biz=1: 2 contacts
    Contact::create(['business_id' => 1, 'name' => 'Wagner Rocha',    'mobile' => '+5548999872822', 'type' => 'customer']);
    Contact::create(['business_id' => 1, 'name' => 'Larissa Comercio','mobile' => '+5548996486699', 'type' => 'customer']);
    // biz=99: 1 contact com nome igual (não deve aparecer)
    Contact::create(['business_id' => 99, 'name' => 'Wagner ALIEN',   'mobile' => '+5511000000000', 'type' => 'customer']);

    $controller = app(InboxController::class);
    $req = Request::create('/test', 'GET', ['q' => 'Wagner']);
    $resp = $controller->searchContacts($req);

    expect($resp)->toBeInstanceOf(JsonResponse::class);
    $body = $resp->getData(true);
    expect($body['contacts'])->toHaveCount(1);
    expect($body['contacts'][0]['name'])->toBe('Wagner Rocha'); // NAO Wagner ALIEN
});

it('R-WA-064-002 — searchContacts requer min 2 chars (retorna vazio sem query, anti-spam)', function () {
    session()->put('user.business_id', 1);

    Contact::create(['business_id' => 1, 'name' => 'Wagner', 'mobile' => '+5548999872822', 'type' => 'customer']);

    $controller = app(InboxController::class);
    // Vazio
    expect($controller->searchContacts(Request::create('/test', 'GET', ['q' => '']))->getData(true)['contacts'])->toBe([]);
    // 1 char
    expect($controller->searchContacts(Request::create('/test', 'GET', ['q' => 'W']))->getData(true)['contacts'])->toBe([]);
    // 2 chars → busca real
    expect($controller->searchContacts(Request::create('/test', 'GET', ['q' => 'Wa']))->getData(true)['contacts'])->toHaveCount(1);
});

it('R-WA-064-003 — searchContacts busca em name/mobile/landline/email/supplier_business_name', function () {
    session()->put('user.business_id', 1);

    // Insert individual via Contact::create — disparar LogsActivity OK,
    // activity_log table existe no schema. Bulk insert quebra com columns
    // diferentes (SQLite "all VALUES must have same terms").
    Contact::create(['business_id' => 1, 'name' => 'Cliente A', 'mobile'   => '+5548912345678', 'type' => 'customer']);
    Contact::create(['business_id' => 1, 'name' => 'Cliente B', 'landline' => '+554822334455',  'type' => 'customer']);
    Contact::create(['business_id' => 1, 'name' => 'Cliente C', 'email'    => 'foo@bar.com',     'type' => 'customer']);
    Contact::create(['business_id' => 1, 'name' => 'Fornec D',  'mobile'   => '+554899999999',  'type' => 'supplier', 'supplier_business_name' => 'Acme LTDA']);

    $controller = app(InboxController::class);

    // Match por mobile
    $r1 = $controller->searchContacts(Request::create('/test', 'GET', ['q' => '912345']))->getData(true)['contacts'];
    expect($r1)->toHaveCount(1);
    expect($r1[0]['name'])->toBe('Cliente A');
    // Match por landline
    expect($controller->searchContacts(Request::create('/test', 'GET', ['q' => '22334']))->getData(true)['contacts'])->toHaveCount(1);
    // Match por email
    expect($controller->searchContacts(Request::create('/test', 'GET', ['q' => 'foo@']))->getData(true)['contacts'])->toHaveCount(1);
    // Match por supplier_business_name
    expect($controller->searchContacts(Request::create('/test', 'GET', ['q' => 'Acme']))->getData(true)['contacts'])->toHaveCount(1);
});

it('R-WA-064-004 — linkContact persiste contact_id valido do mesmo business', function () {
    session()->put('user.business_id', 1);

    $contact = Contact::create(['business_id' => 1, 'name' => 'Wagner', 'mobile' => '+5548999872822', 'type' => 'customer']);

    $channel = Channel::query()->create([
        'business_id' => 1,
        'channel_uuid' => 'aaaaaaaa-0000-0000-0000-000000000064',
        'label' => 'X',
        'type' => Channel::TYPE_WHATSAPP_BAILEYS,
        'status' => 'active',
    ]);
    $conv = Conversation::query()->create([
        'business_id' => 1, 'channel_id' => $channel->id,
        'customer_external_id' => '+5548999872822', 'status' => 'open',
        'contact_id' => null,
    ]);

    $controller = app(InboxController::class);
    $req = Request::create('/test', 'PATCH', ['contact_id' => $contact->id]);
    $resp = $controller->linkContact($req, $conv->id);

    expect($resp)->toBeInstanceOf(RedirectResponse::class);
    $conv->refresh();
    expect($conv->contact_id)->toBe($contact->id);
});

it('R-WA-064-005 — Tier 0: linkContact com contact_id cross-tenant retorna null (silently dropped)', function () {
    session()->put('user.business_id', 1);

    // Contact do biz=99 (cross-tenant) — atacante envia esse id
    $alienContact = Contact::create(['business_id' => 99, 'name' => 'Alien CRM', 'mobile' => '+5599999999999', 'type' => 'customer']);

    $channel = Channel::query()->create([
        'business_id' => 1,
        'channel_uuid' => 'bbbbbbbb-0000-0000-0000-000000000064',
        'label' => 'X',
        'type' => Channel::TYPE_WHATSAPP_BAILEYS,
        'status' => 'active',
    ]);
    $conv = Conversation::query()->create([
        'business_id' => 1, 'channel_id' => $channel->id,
        'customer_external_id' => '+5511111111111', 'status' => 'open',
        'contact_id' => null,
    ]);

    $controller = app(InboxController::class);
    $req = Request::create('/test', 'PATCH', ['contact_id' => $alienContact->id]);
    $resp = $controller->linkContact($req, $conv->id);

    expect($resp)->toBeInstanceOf(RedirectResponse::class);
    $conv->refresh();
    expect($conv->contact_id)->toBeNull(); // silently dropped — Tier 0 enforced
});

it('R-WA-064-006 — linkContact null desvincula (contact_id reset)', function () {
    session()->put('user.business_id', 1);

    $contact = Contact::create(['business_id' => 1, 'name' => 'Wagner', 'mobile' => '+5548999872822', 'type' => 'customer']);

    $channel = Channel::query()->create([
        'business_id' => 1,
        'channel_uuid' => 'cccccccc-0000-0000-0000-000000000064',
        'label' => 'X',
        'type' => Channel::TYPE_WHATSAPP_BAILEYS,
        'status' => 'active',
    ]);
    $conv = Conversation::query()->create([
        'business_id' => 1, 'channel_id' => $channel->id,
        'customer_external_id' => '+5548999872822', 'status' => 'open',
        'contact_id' => $contact->id, // ja vinculado
    ]);

    $controller = app(InboxController::class);
    $req = Request::create('/test', 'PATCH', ['contact_id' => null]);
    $resp = $controller->linkContact($req, $conv->id);

    expect($resp)->toBeInstanceOf(RedirectResponse::class);
    $conv->refresh();
    expect($conv->contact_id)->toBeNull();
});
