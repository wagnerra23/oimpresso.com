<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Entities\Conversation;
use Modules\Whatsapp\Entities\Message;
use Modules\Whatsapp\Entities\WhatsappContactBotOverride;
use Modules\Whatsapp\Services\Notes\ConfigHandler;
use Modules\Whatsapp\Services\Notes\ParsedCommand;
use Modules\Whatsapp\Services\Notes\SlashCommandParser;
use Modules\Whatsapp\Services\Notes\SlashCommandResult;

uses(Tests\TestCase::class);

/**
 * US-WA-077 (ADR 0142 §3c) — Slash command `/config bot=off` override
 * per-contato + Model {@see WhatsappContactBotOverride}.
 *
 * Cobre 10 dimensões:
 *
 *   1. Parser — `/config bot=off` → ParsedCommand('config', 'bot=off')
 *   2. Parser — `/config bot=on`  → ParsedCommand('config', 'bot=on')
 *   3. ConfigHandler — sintaxe inválida `/config xyz=abc` → error
 *   4. ConfigHandler — conversa sem contact_id → error pedindo vínculo CRM
 *   5. ConfigHandler — cria override se primeiro (`/config bot=off`)
 *   6. ConfigHandler — update idempotente (`/config bot=on` em cima de off)
 *   7. resolvedFor() — sem override → fallback business/phone
 *   8. resolvedFor() — com override → respeita override
 *   9. Multi-tenant Tier 0 — biz=99 não vê override de biz=1
 *  10. UNIQUE composto — toggle múltiplas vezes sempre tem 1 row
 *
 * @see Modules\Whatsapp\Services\Notes\ConfigHandler
 * @see Modules\Whatsapp\Entities\WhatsappContactBotOverride
 * @see memory/decisions/0142-notas-internas-sinal-treino-jana.md §3c
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-077
 */
beforeEach(function () {
    foreach (['messages', 'conversations', 'channels', 'whatsapp_contact_bot_overrides'] as $t) {
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

    Schema::create('whatsapp_contact_bot_overrides', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->unsignedInteger('contact_id');
        $table->boolean('bot_enabled');
        $table->unsignedInteger('set_by_user_id');
        $table->text('reason')->nullable();
        $table->timestamp('set_at');
        $table->timestamps();

        $table->unique(['business_id', 'contact_id'], 'wcbo_biz_contact_unq');
        $table->index(['set_by_user_id', 'set_at'], 'wcbo_set_by_idx');
    });
});

function makeWaConvConfig(int $businessId, string $uuid, ?int $contactId = null): array
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
        'contact_id' => $contactId,
        'customer_external_id' => '+5511999999999',
        'contact_name' => 'Cliente Teste',
        'status' => 'open',
    ]);

    return [$channel, $conv];
}

function makeInternalNoteConfig(int $businessId, Conversation $conv, string $body, int $senderUserId = 7): Message
{
    $note = Message::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => $businessId,
        'conversation_id' => $conv->id,
        'direction' => 'outbound',
        'provider' => 'whatsapp_baileys',
        'type' => 'text',
        'body' => $body,
        'status' => 'sent',
        'sender_user_id' => $senderUserId,
        'sender_kind' => 'human',
        'is_internal_note' => true,
    ]);
    $note->setRelation('conversation', $conv);

    return $note;
}

// ─── 1. Parser ──────────────────────────────────────────────────────────────

it('Parser — `/config bot=off` → ParsedCommand(config, "bot=off")', function () {
    $parser = new SlashCommandParser();
    $result = $parser->parse('/config bot=off');

    expect($result)->toBeInstanceOf(ParsedCommand::class);
    expect($result->command)->toBe('config');
    expect($result->arguments)->toBe('bot=off');
});

it('Parser — `/config bot=on` → ParsedCommand(config, "bot=on")', function () {
    $parser = new SlashCommandParser();
    $result = $parser->parse('/config bot=on');

    expect($result)->toBeInstanceOf(ParsedCommand::class);
    expect($result->command)->toBe('config');
    expect($result->arguments)->toBe('bot=on');
});

// ─── 2. ConfigHandler ───────────────────────────────────────────────────────

it('ConfigHandler — sintaxe inválida `/config xyz=abc` → error explicativo', function () {
    [, $conv] = makeWaConvConfig(1, 'aaaa-0000-0000-0000-cfg-bad', 42);
    $note = makeInternalNoteConfig(1, $conv, '/config xyz=abc');

    $handler = new ConfigHandler();
    $result = $handler->handle($note, 'xyz=abc');

    expect($result->isError())->toBeTrue();
    expect($result->errorMessage)->toContain('Sintaxe inválida');
    expect(WhatsappContactBotOverride::withoutGlobalScope(ScopeByBusiness::class)->count())->toBe(0);
});

it('ConfigHandler — conversa SEM contact_id → error pedindo vínculo CRM', function () {
    [, $conv] = makeWaConvConfig(1, 'aaaa-0000-0000-0000-cfg-no-ct'); // contact_id NULL
    $note = makeInternalNoteConfig(1, $conv, '/config bot=off');

    $handler = new ConfigHandler();
    $result = $handler->handle($note, 'bot=off');

    expect($result->isError())->toBeTrue();
    expect($result->errorMessage)->toContain('Vincular contato');
    expect(WhatsappContactBotOverride::withoutGlobalScope(ScopeByBusiness::class)->count())->toBe(0);
});

it('ConfigHandler — cria override `bot=off` no primeiro toggle', function () {
    [, $conv] = makeWaConvConfig(1, 'aaaa-0000-0000-0000-cfg-create', 42);
    $note = makeInternalNoteConfig(1, $conv, '/config bot=off');

    $handler = new ConfigHandler();
    $result = $handler->handle($note, 'bot=off');

    expect($result->kind)->toBe(SlashCommandResult::KIND_SUCCESS);
    expect($result->badge)->toBe('🤖 bot desligado');

    $override = WhatsappContactBotOverride::withoutGlobalScope(ScopeByBusiness::class)->first();
    expect($override)->not->toBeNull();
    expect($override->business_id)->toBe(1);
    expect($override->contact_id)->toBe(42);
    expect($override->bot_enabled)->toBeFalse();
    expect($override->set_by_user_id)->toBe(7);
    expect($override->set_at)->not->toBeNull();
});

it('ConfigHandler — idempotente: `/config bot=on` em cima de bot=off UPDATE mesma row', function () {
    [, $conv] = makeWaConvConfig(1, 'aaaa-0000-0000-0000-cfg-upd', 42);

    // 1º toggle: bot=off
    $note1 = makeInternalNoteConfig(1, $conv, '/config bot=off');
    (new ConfigHandler())->handle($note1, 'bot=off');
    expect(WhatsappContactBotOverride::withoutGlobalScope(ScopeByBusiness::class)->count())->toBe(1);

    // 2º toggle: bot=on (mesma combinação biz+contact → updateOrCreate atualiza)
    $note2 = makeInternalNoteConfig(1, $conv, '/config bot=on');
    $result = (new ConfigHandler())->handle($note2, 'bot=on');

    expect($result->kind)->toBe(SlashCommandResult::KIND_SUCCESS);
    expect($result->badge)->toBe('🤖 bot ligado');
    expect(WhatsappContactBotOverride::withoutGlobalScope(ScopeByBusiness::class)->count())->toBe(1);

    $override = WhatsappContactBotOverride::withoutGlobalScope(ScopeByBusiness::class)->first();
    expect($override->bot_enabled)->toBeTrue();
});

it('ConfigHandler — aceita true/false como alias on/off', function () {
    [, $conv1] = makeWaConvConfig(1, 'aaaa-0000-0000-0000-cfg-true', 10);
    $note1 = makeInternalNoteConfig(1, $conv1, '/config bot=true');
    $result1 = (new ConfigHandler())->handle($note1, 'bot=true');
    expect($result1->isSuccess())->toBeTrue();

    [, $conv2] = makeWaConvConfig(1, 'aaaa-0000-0000-0000-cfg-false', 11);
    $note2 = makeInternalNoteConfig(1, $conv2, '/config bot=false');
    $result2 = (new ConfigHandler())->handle($note2, 'bot=false');
    expect($result2->isSuccess())->toBeTrue();

    $on = WhatsappContactBotOverride::withoutGlobalScope(ScopeByBusiness::class)->where('contact_id', 10)->first();
    $off = WhatsappContactBotOverride::withoutGlobalScope(ScopeByBusiness::class)->where('contact_id', 11)->first();
    expect($on->bot_enabled)->toBeTrue();
    expect($off->bot_enabled)->toBeFalse();
});

// ─── 3. resolvedFor() — fallback vs override ────────────────────────────────

it('resolvedFor — SEM override retorna fallback (flag global)', function () {
    expect(WhatsappContactBotOverride::resolvedFor(1, 999, fallback: true))->toBeTrue();
    expect(WhatsappContactBotOverride::resolvedFor(1, 999, fallback: false))->toBeFalse();
});

it('resolvedFor — COM override respeita o flag override (ignora fallback)', function () {
    WhatsappContactBotOverride::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'contact_id' => 42,
        'bot_enabled' => false,
        'set_by_user_id' => 7,
        'set_at' => now(),
    ]);

    // Override=false vence fallback=true (caso comum: business tem bot=on global, mas atendente desligou pra este contato)
    expect(WhatsappContactBotOverride::resolvedFor(1, 42, fallback: true))->toBeFalse();

    // Caso simétrico: override=true vence fallback=false
    WhatsappContactBotOverride::withoutGlobalScope(ScopeByBusiness::class)->where('contact_id', 42)->update([
        'bot_enabled' => true,
    ]);
    expect(WhatsappContactBotOverride::resolvedFor(1, 42, fallback: false))->toBeTrue();
});

// ─── 4. Multi-tenant Tier 0 ─────────────────────────────────────────────────

it('Multi-tenant Tier 0 — biz=99 NÃO vê override criado em biz=1 (mesmo contact_id)', function () {
    // Cria override biz=1, contact=42
    [, $conv1] = makeWaConvConfig(1, 'aaaa-0000-0000-0000-biz1-cfg', 42);
    $note1 = makeInternalNoteConfig(1, $conv1, '/config bot=off');
    (new ConfigHandler())->handle($note1, 'bot=off');

    // Cria override biz=99, contact=42 (mesmo contact_id, tenant diferente — permitido)
    [, $conv99] = makeWaConvConfig(99, 'aaaa-0000-0000-0000-biz99-cfg', 42);
    $note99 = makeInternalNoteConfig(99, $conv99, '/config bot=on');
    (new ConfigHandler())->handle($note99, 'bot=on');

    expect(WhatsappContactBotOverride::withoutGlobalScope(ScopeByBusiness::class)->count())->toBe(2);

    // resolvedFor — biz=1 vê off
    expect(WhatsappContactBotOverride::resolvedFor(1, 42, fallback: true))->toBeFalse();
    // resolvedFor — biz=99 vê on
    expect(WhatsappContactBotOverride::resolvedFor(99, 42, fallback: false))->toBeTrue();

    // Global scope: simula sessão biz=99, query só vê 1 row
    session(['user.business_id' => 99]);
    $visible = WhatsappContactBotOverride::where('business_id', 99)->get();
    expect($visible)->toHaveCount(1);
    expect($visible->first()->bot_enabled)->toBeTrue();
});

// ─── 5. UNIQUE composto ─────────────────────────────────────────────────────

it('UNIQUE (business_id, contact_id) — toggle múltiplas vezes sempre 1 só row', function () {
    [, $conv] = makeWaConvConfig(1, 'aaaa-0000-0000-0000-cfg-toggle', 42);

    // 5 toggles seguidos (alternando) — sempre 1 row
    foreach (['off', 'on', 'off', 'on', 'off'] as $i => $valor) {
        $note = makeInternalNoteConfig(1, $conv, "/config bot={$valor}");
        $result = (new ConfigHandler())->handle($note, "bot={$valor}");
        expect($result->isSuccess())->toBeTrue();
        expect(WhatsappContactBotOverride::withoutGlobalScope(ScopeByBusiness::class)->count())->toBe(1);
    }

    // Estado final: bot=off (último toggle)
    $override = WhatsappContactBotOverride::withoutGlobalScope(ScopeByBusiness::class)->first();
    expect($override->bot_enabled)->toBeFalse();
});

// ─── 6. Gate Tier 0 — handler em mensagem NÃO-nota é bloqueado ──────────────

it('ConfigHandler — gate redundante Tier 0 (is_internal_note=false vira error)', function () {
    [, $conv] = makeWaConvConfig(1, 'aaaa-0000-0000-0000-cfg-gate', 42);

    $note = Message::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'conversation_id' => $conv->id,
        'direction' => 'outbound',
        'provider' => 'whatsapp_baileys',
        'type' => 'text',
        'body' => '/config bot=off',
        'status' => 'sent',
        'sender_user_id' => 7,
        'sender_kind' => 'human',
        'is_internal_note' => false, // NÃO é nota interna
    ]);
    $note->setRelation('conversation', $conv);

    $handler = new ConfigHandler();
    $result = $handler->handle($note, 'bot=off');

    expect($result->isError())->toBeTrue();
    expect(WhatsappContactBotOverride::withoutGlobalScope(ScopeByBusiness::class)->count())->toBe(0);
});
