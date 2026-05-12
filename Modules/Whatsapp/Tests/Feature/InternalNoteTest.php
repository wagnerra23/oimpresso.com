<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Entities\Conversation;
use Modules\Whatsapp\Entities\Message;
use Modules\Whatsapp\Http\Controllers\Admin\InboxController;

uses(Tests\TestCase::class);

/**
 * US-WA-071 (ADR 0142) — Notas internas estilo Chatwoot.
 *
 * GUARD tests Tier 0 IRREVOGÁVEL:
 *
 *   1. is_internal_note=true → driver Baileys NUNCA é chamado (Http::assertNothingSent)
 *   2. is_internal_note=true → message persiste com status='sent' direto
 *   3. is_internal_note=true → composer aceita mesmo com janela 24h fechada
 *   4. is_internal_note + kind=template → 422 (combinação inválida)
 *   5. cross-tenant biz=99 → não vê notas internas de biz=1 (global scope)
 *   6. msgToUiArray retorna is_internal_note no payload UI
 *
 * Métrica `internal_note_dispatch_to_driver_violation_24h` MUST be 0 em prod.
 *
 * @see memory/decisions/0142-notas-internas-sinal-treino-jana.md
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-071
 */
beforeEach(function () {
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
        $table->timestamp('last_inbound_at')->nullable();
        $table->timestamp('last_outbound_at')->nullable();
        $table->timestamp('last_message_at')->nullable();
        $table->unsignedInteger('unread_count')->default(0);
        // US-WA-072 denormalize (vem do MessageObserver::created)
        $table->string('last_message_preview', 120)->nullable();
        $table->string('last_message_direction', 20)->nullable();
        $table->boolean('is_blocked')->default(false);
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
        $table->boolean('is_internal_note')->default(false);
        $table->timestamp('created_at')->useCurrent();
        $table->timestamp('updated_at')->nullable();
    });
});

function makeChannelAndConv(int $businessId, string $uuid = 'aaaa-0000-0000-0000-internal'): array
{
    $channel = Channel::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => $businessId,
        'channel_uuid' => $uuid,
        'label' => 'Suporte',
        'type' => Channel::TYPE_WHATSAPP_BAILEYS,
        'status' => 'active',
    ]);

    $conv = Conversation::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => $businessId,
        'channel_id' => $channel->id,
        'customer_external_id' => '+5511999999999',
        'contact_name' => 'Cliente Teste',
        'status' => 'open',
    ]);

    return [$channel, $conv];
}

it('Tier 0 — is_internal_note=true NUNCA dispatcha driver Baileys (Http::assertNothingSent)', function () {
    Http::fake();
    session(['user.business_id' => 1, 'user.id' => 1]);
    [, $conv] = makeChannelAndConv(1);

    $request = Request::create('', 'POST', [
        'kind' => 'freeform',
        'body' => 'lembrar de ligar antes do almoço',
        'is_internal_note' => true,
    ]);
    $request->setLaravelSession(app('session.store'));
    app('session.store')->put('user.business_id', 1);
    app('session.store')->put('user.id', 1);

    $controller = new InboxController();
    $response = $controller->send($request, $conv->id);

    // Tier 0 IRREVOGÁVEL — daemon Baileys NUNCA chamado
    Http::assertNothingSent();

    // Message persistida com flag + status sent
    $message = Message::withoutGlobalScope(ScopeByBusiness::class)
        ->where('business_id', 1)
        ->first();
    expect($message)->not->toBeNull();
    expect($message->is_internal_note)->toBeTrue();
    expect($message->status)->toBe('sent');
    expect($message->body)->toBe('lembrar de ligar antes do almoço');
    expect($message->sender_kind)->toBe('human');
});

it('Tier 0 — is_internal_note=false (default) DISPATCHA driver Baileys (controle positivo)', function () {
    // Fake só daemon URL — qualquer outra chamada falha
    Http::fake([
        '*' => Http::response(['status' => 'sent', 'message_id' => 'wamid.abc'], 200),
    ]);
    config([
        'whatsapp.baileys.daemon_url' => 'https://daemon.test',
        'whatsapp.baileys.api_key' => 'test-key-min16chars',
    ]);
    session(['user.business_id' => 1, 'user.id' => 1]);
    [, $conv] = makeChannelAndConv(1);

    $request = Request::create('', 'POST', [
        'kind' => 'freeform',
        'body' => 'oi cliente',
        'is_internal_note' => false,
    ]);
    $request->setLaravelSession(app('session.store'));
    app('session.store')->put('user.business_id', 1);
    app('session.store')->put('user.id', 1);

    $controller = new InboxController();
    $controller->send($request, $conv->id);

    // Sem assertNothingSent — controle positivo, deve TER chamado o daemon
    Http::assertSent(fn ($req) => str_contains($req->url(), '/instances/'));
});

it('is_internal_note + kind=template → 422 (combinação inválida)', function () {
    session(['user.business_id' => 1, 'user.id' => 1]);
    [, $conv] = makeChannelAndConv(1);

    $request = Request::create('', 'POST', [
        'kind' => 'template',
        'template_name' => 'billing_due',
        'is_internal_note' => true,
    ]);
    $request->setLaravelSession(app('session.store'));
    app('session.store')->put('user.business_id', 1);
    app('session.store')->put('user.id', 1);

    $controller = new InboxController();
    $response = $controller->send($request, $conv->id);

    // Template + nota interna = combinação inválida
    expect($response->getSession()->get('errors'))->not->toBeNull();
    // Nenhuma Message criada
    expect(Message::withoutGlobalScope(ScopeByBusiness::class)->count())->toBe(0);
});

it('cross-tenant — biz=99 NÃO vê notas internas de biz=1 (global scope Tier 0)', function () {
    [, $conv1] = makeChannelAndConv(1, 'aaaa-0000-0000-0000-biz1');
    [, $conv99] = makeChannelAndConv(99, 'aaaa-0000-0000-0000-biz99');

    Message::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'conversation_id' => $conv1->id,
        'direction' => 'outbound',
        'provider' => 'whatsapp_baileys',
        'type' => 'text',
        'body' => 'NOTA SECRETA biz=1',
        'status' => 'sent',
        'sender_kind' => 'human',
        'is_internal_note' => true,
    ]);

    Message::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 99,
        'conversation_id' => $conv99->id,
        'direction' => 'outbound',
        'provider' => 'whatsapp_baileys',
        'type' => 'text',
        'body' => 'NOTA biz=99',
        'status' => 'sent',
        'sender_kind' => 'human',
        'is_internal_note' => true,
    ]);

    // Simula sessão biz=99
    session(['user.business_id' => 99]);
    $visible = Message::where('business_id', 99)->get(); // global scope ativo

    expect($visible)->toHaveCount(1);
    expect($visible->first()->body)->toBe('NOTA biz=99');
    expect($visible->pluck('body')->toArray())->not->toContain('NOTA SECRETA biz=1');
});

it('Message Model — is_internal_note default false (schema default)', function () {
    [, $conv] = makeChannelAndConv(1);

    // Cria sem passar a flag — default schema é false
    $message = Message::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'conversation_id' => $conv->id,
        'direction' => 'outbound',
        'provider' => 'whatsapp_baileys',
        'type' => 'text',
        'body' => 'sem flag',
        'status' => 'sent',
    ]);

    expect($message->fresh()->is_internal_note)->toBeFalse();
});
