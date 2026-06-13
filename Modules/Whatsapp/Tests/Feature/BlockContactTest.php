<?php

declare(strict_types=1);

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Entities\Conversation;
use Modules\Whatsapp\Entities\Message;
use Modules\Whatsapp\Http\Controllers\Admin\InboxController;
use Modules\Whatsapp\Http\Controllers\Api\ChannelBaileysWebhookController;

uses(Tests\TestCase::class);

/**
 * R-WA-066 — GUARD tests pra bloquear contato (US-WA-066).
 *
 * Wagner 2026-05-11: "Botão Bloquear no sidebar direito. Quando blocked:
 * webhook inbound de conv blocked é DROPPED. Composer disabled. Botão vira
 * Desbloquear."
 *
 * Cobre:
 *  001. blockContact controller seta is_blocked=true + retorna RedirectResponse
 *  002. webhook handleMessage com conv.is_blocked=true retorna 200
 *       'inbound_dropped_blocked' SEM criar Message nova
 *  003. Tier 0 cross-tenant — biz=1 não pode bloquear conv de biz=99
 *       (findOrFail throws 404 — global scope HasBusinessScope filtra)
 *  004. unblock toggle reverte is_blocked=false
 *
 * @see memory/decisions/0135-omnichannel-inbox-arquitetura.md
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('era-sqlite: schema sintético manual incompatível com MySQL persistente — quarentena Onda 2 SDD floor; burn-down converte depois.');
    }

    foreach (['messages', 'conversations', 'channels'] as $t) {
        Schema::dropIfExists($t);
    }

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
        $table->boolean('is_blocked')->default(false);
        $table->timestamp('last_inbound_at')->nullable();
        $table->timestamp('last_outbound_at')->nullable();
        $table->timestamp('last_message_at')->nullable();
        $table->unsignedInteger('unread_count')->default(0);
        $table->timestamps();
    });

    Schema::create('messages', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->unsignedBigInteger('conversation_id');
        $table->string('direction', 10);
        $table->string('provider', 30);
        $table->string('provider_message_id', 128)->nullable();
        $table->string('type', 20)->default('text');
        $table->string('template_name', 64)->nullable();
        $table->string('subject', 255)->nullable();
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

    // Mock daemon Baileys — qualquer chamada Http retorna 404 (graceful)
    // pra exercitar o fallback de tolerância. Equivale a "endpoint ainda
    // não existe no daemon CT 100" (status real US-WA-066).
    Http::fake([
        '*/instances/*/block' => Http::response(['error' => 'not_found'], 404),
    ]);
});

it('R-WA-066-001 — blockContact controller seta is_blocked=true e retorna RedirectResponse', function () {
    session()->put('user.business_id', 1);
    session()->put('user.id', 42);

    $channel = Channel::query()->create([
        'business_id' => 1,
        'channel_uuid' => '66666666-0000-0000-0000-000000000001',
        'label' => 'Suporte',
        'type' => Channel::TYPE_WHATSAPP_BAILEYS,
        'status' => 'active',
    ]);
    $conv = Conversation::query()->create([
        'business_id' => 1, 'channel_id' => $channel->id,
        'customer_external_id' => '+554899999999', 'status' => 'open',
        'is_blocked' => false,
    ]);

    $controller = app(InboxController::class);
    $req = Request::create('/test', 'PATCH', ['block' => true]);
    $resp = $controller->blockContact($req, $conv->id);

    expect($resp)->toBeInstanceOf(RedirectResponse::class);
    $conv->refresh();
    expect($conv->is_blocked)->toBeTrue();
});

it('R-WA-066-002 — webhook handleMessage com conv.is_blocked=true retorna 200 inbound_dropped_blocked SEM criar Message', function () {
    $channel = Channel::query()->create([
        'business_id' => 1,
        'channel_uuid' => '66666666-0000-0000-0000-000000000002',
        'label' => 'Suporte',
        'type' => Channel::TYPE_WHATSAPP_BAILEYS,
        'status' => 'active',
    ]);
    // Conv pré-existente bloqueada
    $conv = Conversation::query()->create([
        'business_id' => 1, 'channel_id' => $channel->id,
        'customer_external_id' => '+554811112222', 'status' => 'open',
        'is_blocked' => true,
    ]);

    // Baileys real envia remoteJid SEM '+' inicial (E.164 puro + sufixo).
    // Controller resolve customer_external_id = '+' . raw → '+554811112222'.
    $payload = [
        'event' => 'message',
        'data' => [
            'key' => [
                'remoteJid' => '554811112222@s.whatsapp.net',
                'id' => 'SPAM_MSG_001',
                'fromMe' => false,
            ],
            'message' => ['conversation' => 'spam spam spam'],
            'push_name' => 'Cliente Spammer',
        ],
    ];

    $controller = app(ChannelBaileysWebhookController::class);
    $req = Request::create('/test', 'POST', $payload);
    $resp = $controller->handle($req, $channel->channel_uuid);

    expect($resp->getStatusCode())->toBe(200);
    expect($resp->getData(true)['note'])->toBe('inbound_dropped_blocked');

    // Crítico: NENHUMA Message criada (drop antes do firstOrCreate)
    $msgCount = Message::query()->withoutGlobalScope(ScopeByBusiness::class)->count();
    expect($msgCount)->toBe(0);

    // Conv NÃO foi modificada (unread_count = 0, last_message_at unchanged)
    $conv->refresh();
    expect($conv->unread_count)->toBe(0);
    expect($conv->is_blocked)->toBeTrue();
});

it('R-WA-066-003 — Tier 0 cross-tenant: biz=1 nao pode bloquear conv de biz=99 (findOrFail 404)', function () {
    session()->put('user.business_id', 1);
    session()->put('user.id', 42);

    // Channel + Conversation pertencem a biz=99 (atacante é biz=1)
    $channelAlien = Channel::query()->create([
        'business_id' => 99,
        'channel_uuid' => '66666666-0000-0000-0000-000000000003',
        'label' => 'X',
        'type' => Channel::TYPE_WHATSAPP_BAILEYS,
        'status' => 'active',
    ]);
    $convAlien = new Conversation([
        'business_id' => 99,
        'channel_id' => $channelAlien->id,
        'customer_external_id' => '+554833334444',
        'status' => 'open',
    ]);
    $convAlien->save();

    $controller = app(InboxController::class);
    $req = Request::create('/test', 'PATCH', ['block' => true]);

    // Atacante biz=1 tentando bloquear conv de biz=99 → findOrFail throws 404
    // (global scope HasBusinessScope filtra a busca por business_id = 1)
    expect(fn () => $controller->blockContact($req, $convAlien->id))
        ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

    // Conv alien NÃO foi modificada — Tier 0 preservado
    $convAlien->refresh();
    expect($convAlien->is_blocked)->toBeFalse();
});

it('R-WA-066-004 — unblock toggle reverte is_blocked=false', function () {
    session()->put('user.business_id', 1);
    session()->put('user.id', 42);

    $channel = Channel::query()->create([
        'business_id' => 1,
        'channel_uuid' => '66666666-0000-0000-0000-000000000004',
        'label' => 'X',
        'type' => Channel::TYPE_WHATSAPP_BAILEYS,
        'status' => 'active',
    ]);
    // Conv já bloqueada — atendente quer desbloquear
    $conv = Conversation::query()->create([
        'business_id' => 1, 'channel_id' => $channel->id,
        'customer_external_id' => '+554855556666', 'status' => 'open',
        'is_blocked' => true,
    ]);

    $controller = app(InboxController::class);
    $req = Request::create('/test', 'PATCH', ['block' => false]);
    $resp = $controller->blockContact($req, $conv->id);

    expect($resp)->toBeInstanceOf(RedirectResponse::class);
    $conv->refresh();
    expect($conv->is_blocked)->toBeFalse();
});
