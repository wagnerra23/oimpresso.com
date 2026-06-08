<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Entities\Conversation;
use Modules\Whatsapp\Entities\Message;
use Modules\Whatsapp\Services\Webhook\MessagePersister;

uses(Tests\TestCase::class);

/**
 * R-WA-BAILEYS-7X-001/002 — anti-regressão upgrade Baileys 6.7.18 → 7.0.0-rc11.
 *
 * Baileys 7.x adiciona ao `message.key`:
 *   - `remoteJidAlt` — JID espelho (se remoteJid é @lid, alt é @s.whatsapp.net e vice-versa)
 *   - `participantAlt` — equivalente em grupos
 *
 * Estes campos NÃO existiam em 6.7.x — back-compat preservado lendo opcional.
 *
 * Cobre:
 *   001. Payload Baileys 7.x: remoteJid=@lid + remoteJidAlt=@s.whatsapp.net →
 *        MessagePersister extrai E.164 correto de remoteJidAlt (não usa LID bruto).
 *   002. Payload Baileys 6.7.x legacy (sem remoteJidAlt): MessagePersister
 *        continua funcionando, customer_external_id resolvido de senderPn
 *        ou (fallback) remoteJid bruto.
 *
 * Tier 0 multi-tenant: business_id sempre presente em queries (ScopeByBusiness).
 *
 * @see Modules/Whatsapp/Services/Webhook/MessagePersister.php
 * @see Modules/Whatsapp/Http/Controllers/Api/ChannelBaileysWebhookController.php
 * @see memory/requisitos/Whatsapp/runbooks/migrar-baileys-7x.md
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
        $table->string('media_url', 500)->nullable();
        $table->string('media_mime', 100)->nullable();
        $table->unsignedBigInteger('media_size_bytes')->nullable();
        $table->unsignedSmallInteger('media_duration_s')->nullable();
        $table->string('media_thumbnail_url', 500)->nullable();
        $table->text('media_transcription')->nullable();
        $table->string('media_filename', 255)->nullable();
        $table->string('media_download_status', 30)->default('pending');
        $table->unsignedInteger('media_download_attempts')->default(0);
        $table->timestamp('media_download_last_attempt_at')->nullable();
        $table->string('media_download_failed_reason', 255)->nullable();
        $table->timestamp('created_at')->useCurrent();
        $table->timestamp('updated_at')->nullable();
        $table->unique('provider_message_id', 'msgs_provider_msg_uniq');
    });
});

function makeBaileys7xChannel(int $businessId, string $uuidSuffix): Channel
{
    return Channel::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => $businessId,
        'channel_uuid' => '7x000000-0000-0000-0000-00000000' . str_pad($uuidSuffix, 4, '0', STR_PAD_LEFT),
        'label' => 'Baileys 7x Test',
        'type' => Channel::TYPE_WHATSAPP_BAILEYS,
        'status' => 'active',
        'display_identifier' => '5511999998888',
    ]);
}

it('R-WA-BAILEYS-7X-001 — payload Baileys 7.x: remoteJidAlt entrega phone real quando remoteJid é @lid', function () {
    // Multi-tenant Tier 0: business_id=99 (sandbox/teste — ADR 0093/0101)
    $channel = makeBaileys7xChannel(99, '0001');
    $persister = new MessagePersister($channel);

    // Payload realista shape Baileys 7.x — remoteJid=@lid + remoteJidAlt=@s.whatsapp.net
    $result = $persister->persist([
        'key' => [
            'remoteJid' => '146288096175***@lid',         // PII redacted
            'remoteJidAlt' => '55489998***2822@s.whatsapp.net', // Baileys 7.x novo
            'id' => 'MSG_7X_REMOTEJID_ALT_001',
            'fromMe' => false,
        ],
        'message' => ['conversation' => 'Olá vindo via Baileys 7.x'],
        'push_name' => 'Cliente Teste 7x',
    ], bumpUnread: true);

    expect($result->wasCreated())->toBeTrue();
    expect($result->conversation)->not->toBeNull();
    // E.164 vem de remoteJidAlt (não do @lid bruto)
    expect($result->conversation->customer_external_id)->toBe('+55489998***2822');
    expect($result->conversation->contact_name)->toBe('Cliente Teste 7x');
    expect($result->message->body)->toBe('Olá vindo via Baileys 7.x');
    expect($result->message->business_id)->toBe(99); // Tier 0 isolation preservado
});

it('R-WA-BAILEYS-7X-002 — back-compat: payload Baileys 6.7.x sem remoteJidAlt continua usando senderPn', function () {
    $channel = makeBaileys7xChannel(99, '0002');
    $persister = new MessagePersister($channel);

    // Payload legacy shape Baileys 6.7.x — só senderPn (SEM remoteJidAlt)
    $result = $persister->persist([
        'key' => [
            'remoteJid' => '146288096175***@lid',
            'senderPn' => '55489998***2822@s.whatsapp.net', // legacy 6.7.x
            'id' => 'MSG_6X_LEGACY_SENDERPN_001',
            'fromMe' => false,
        ],
        'message' => ['conversation' => 'Mensagem 6.7.x legacy'],
        'push_name' => 'Cliente Legacy',
    ], bumpUnread: true);

    expect($result->wasCreated())->toBeTrue();
    // senderPn 6.7.x ainda preferido — back-compat 100%
    expect($result->conversation->customer_external_id)->toBe('+55489998***2822');
    expect($result->message->body)->toBe('Mensagem 6.7.x legacy');
});

it('R-WA-BAILEYS-7X-003 — prioridade: senderPn > remoteJidAlt quando ambos presentes (não regredir 6.7.x)', function () {
    $channel = makeBaileys7xChannel(99, '0003');
    $persister = new MessagePersister($channel);

    // Cenário edge — daemon Baileys 7.x pode entregar AMBOS senderPn e remoteJidAlt
    // (rc11 ainda entrega senderPn em alguns paths). Comportamento esperado:
    // senderPn vence porque é fonte legacy autoritativa.
    $result = $persister->persist([
        'key' => [
            'remoteJid' => '146288096175***@lid',
            'senderPn' => '55119999***1111@s.whatsapp.net',    // legacy
            'remoteJidAlt' => '55119999***2222@s.whatsapp.net', // Baileys 7.x
            'id' => 'MSG_7X_BOTH_FIELDS_001',
            'fromMe' => false,
        ],
        'message' => ['conversation' => 'Ambos presentes'],
    ], bumpUnread: true);

    expect($result->wasCreated())->toBeTrue();
    // senderPn (1111) vence remoteJidAlt (2222)
    expect($result->conversation->customer_external_id)->toBe('+55119999***1111');
});

it('R-WA-BAILEYS-7X-004 — payload sem nenhum identifier válido retorna skipped (anti-regressão guard)', function () {
    $channel = makeBaileys7xChannel(99, '0004');
    $persister = new MessagePersister($channel);

    // Payload corrompido — nenhum campo de JID
    $result = $persister->persist([
        'key' => [
            'id' => 'MSG_NO_JID_001',
            'fromMe' => false,
        ],
        'message' => ['conversation' => 'Sem JID'],
    ], bumpUnread: true);

    expect($result->wasSkipped())->toBeTrue();
    expect($result->note)->toBe('no_remote_jid');
});

it('R-WA-BAILEYS-7X-005 — payload Baileys 7.x com remoteJidAlt SEM senderPn: ainda resolve sem precisar do legacy', function () {
    $channel = makeBaileys7xChannel(99, '0005');
    $persister = new MessagePersister($channel);

    // Cenário esperado pós-migração 7.x: senderPn pode parar de vir em alguns
    // paths e remoteJidAlt vira fonte primária. Garante que essa transição
    // não quebra inbox.
    $result = $persister->persist([
        'key' => [
            'remoteJid' => '146288096175***@lid',
            'remoteJidAlt' => '55489998***2822@s.whatsapp.net',
            // SEM senderPn — 7.x puro
            'id' => 'MSG_7X_ONLY_ALT_001',
            'fromMe' => false,
        ],
        'message' => ['conversation' => 'Só remoteJidAlt, sem senderPn'],
    ], bumpUnread: true);

    expect($result->wasCreated())->toBeTrue();
    expect($result->conversation->customer_external_id)->toBe('+55489998***2822');
});
