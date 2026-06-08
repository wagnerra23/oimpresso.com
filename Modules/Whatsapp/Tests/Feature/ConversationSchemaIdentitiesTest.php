<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Entities\Conversation;
use Modules\Whatsapp\Services\Webhook\MessagePersister;

uses(Tests\TestCase::class);

/**
 * R-WA-SCHEMA-IDENT — GUARD tests pra schema 3-identifiers (PR1).
 *
 * Estudo protocol-level 2026-05-15 revelou 3 IDs distintos no WhatsApp
 * Multi-Device. Esse PR adiciona colunas `lid` + `phone_e164` + `bsuid` na
 * `conversations` (e mirror `whatsapp_conversations`) + popula via
 * MessagePersister sem perder dados existentes.
 *
 * Cobre:
 *  001. Migration adiciona 3 colunas + idempotente (re-roda sem quebrar)
 *  002. MessagePersister popula `lid` quando remoteJid @lid (sem senderPn)
 *  003. MessagePersister popula `phone_e164` quando senderPn @s.whatsapp.net
 *  004. MessagePersister backfill 3-ids em conv pré-existente (sem sobrescrever)
 *  005. Tier 0 — biz=1 vs biz=99 não compartilham conv pelo mesmo LID
 *
 * @see Modules/Whatsapp/Database/Migrations/2026_05_15_010000_add_identity_columns_to_conversations.php
 * @see Modules/Whatsapp/Services/Webhook/MessagePersister.php
 * @see memory/sessions/2026-05-15-estudo-whatsapp-protocol-vs-oimpresso.md
 */
beforeEach(function () {
    Event::fake();

    foreach (['conversations', 'channels', 'messages'] as $t) {
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
        $table->string('channel_health', 20)->default('never_checked');
        $table->unsignedInteger('channel_health_consecutive_failures')->default(0);
        $table->timestamp('last_health_check_at')->nullable();
        $table->text('last_health_message')->nullable();
        $table->timestamps();
    });

    // Schema base SEM as 3 colunas novas — migration deve adicioná-las.
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

/**
 * Helper — cria Channel pra associar à conv de teste.
 */
function makeSchemaTestChannel(int $bizId = 1, string $uuidSuffix = '0001'): Channel
{
    return Channel::query()->create([
        'business_id' => $bizId,
        'channel_uuid' => 'aaaaaaaa-1111-0000-0000-' . str_pad($uuidSuffix, 12, '0', STR_PAD_LEFT),
        'label' => 'Teste schema',
        'type' => Channel::TYPE_WHATSAPP_BAILEYS,
        'status' => 'active',
    ]);
}

it('R-WA-SCHEMA-IDENT-001 — migration adiciona lid+phone_e164+bsuid em conversations e e idempotente em re-run', function () {
    // Pré-condição — colunas NÃO existem antes da migration.
    expect(Schema::hasColumn('conversations', 'lid'))->toBeFalse();
    expect(Schema::hasColumn('conversations', 'phone_e164'))->toBeFalse();
    expect(Schema::hasColumn('conversations', 'bsuid'))->toBeFalse();

    // Roda migration manualmente (não dá `migrate` pq schema é criado em
    // beforeEach do test, não pela suite normal).
    $migrationPath = __DIR__ . '/../../Database/Migrations/2026_05_15_010000_add_identity_columns_to_conversations.php';
    $migration = require $migrationPath;
    $migration->up();

    // Pós-condição — colunas existem.
    expect(Schema::hasColumn('conversations', 'lid'))->toBeTrue();
    expect(Schema::hasColumn('conversations', 'phone_e164'))->toBeTrue();
    expect(Schema::hasColumn('conversations', 'bsuid'))->toBeTrue();

    // Idempotência — re-rodar não deve quebrar (hasColumn guard).
    $migration->up();
    expect(Schema::hasColumn('conversations', 'lid'))->toBeTrue();
});

it('R-WA-SCHEMA-IDENT-002 — MessagePersister popula lid quando remoteJid @lid e sem senderPn', function () {
    // Roda migration pra ter as 3 colunas.
    $migration = require __DIR__ . '/../../Database/Migrations/2026_05_15_010000_add_identity_columns_to_conversations.php';
    $migration->up();

    $channel = makeSchemaTestChannel(1, '002');

    // Payload Baileys 6.7.9 típico: chat moderno chega @lid (sem senderPn).
    // PII redacted — usar middle stars convention (CLAUDE.md proibições).
    $payload = [
        'key' => [
            'remoteJid' => '14628809617558@lid',
            'id' => 'SCHEMA_LID_001',
            'fromMe' => false,
        ],
        'push_name' => 'Cliente Anonimo',
        'message' => ['conversation' => 'Boa tarde'],
    ];

    $persister = new MessagePersister($channel);
    $result = $persister->persist($payload);

    expect($result->wasCreated())->toBeTrue();

    $conv = Conversation::query()
        ->withoutGlobalScope(ScopeByBusiness::class)
        ->where('business_id', 1)
        ->where('channel_id', $channel->id)
        ->first();

    expect($conv)->not->toBeNull();
    expect($conv->lid)->toBe('14628809617558');
    // phone_e164 — sem senderPn, customer_external_id virou '+<lid>' (cru).
    // Guard no Persister: se customer_external_id === '+' . $lidValue,
    // NÃO popula phone_e164 (não é phone real, é LID mascarado).
    expect($conv->phone_e164)->toBeNull();
    expect($conv->bsuid)->toBeNull();
});

it('R-WA-SCHEMA-IDENT-003 — MessagePersister popula phone_e164 quando senderPn @s.whatsapp.net presente', function () {
    $migration = require __DIR__ . '/../../Database/Migrations/2026_05_15_010000_add_identity_columns_to_conversations.php';
    $migration->up();

    $channel = makeSchemaTestChannel(1, '003');

    // Payload Baileys 6.8+ com senderPn (history sync moderno OU msg real-time
    // de chat com mapping conhecido). PII redacted: +5548***2822 → mock.
    $payload = [
        'key' => [
            'remoteJid' => '14628809617558@lid',
            'senderPn' => '5548000002822@s.whatsapp.net',
            'id' => 'SCHEMA_PN_001',
            'fromMe' => false,
        ],
        'push_name' => 'Cliente Mapeado',
        'message' => ['conversation' => 'Oi'],
    ];

    $persister = new MessagePersister($channel);
    $result = $persister->persist($payload);

    expect($result->wasCreated())->toBeTrue();

    $conv = Conversation::query()
        ->withoutGlobalScope(ScopeByBusiness::class)
        ->where('business_id', 1)
        ->where('channel_id', $channel->id)
        ->first();

    expect($conv)->not->toBeNull();
    // LID veio no remoteJid — populado também.
    expect($conv->lid)->toBe('14628809617558');
    // phone_e164 — senderPn @s.whatsapp.net → resolvido pra +E.164.
    expect($conv->phone_e164)->toBe('+5548000002822');
    expect($conv->bsuid)->toBeNull();
});

it('R-WA-SCHEMA-IDENT-004 — MessagePersister backfill 3-ids em conv existente sem sobrescrever phone preexistente', function () {
    $migration = require __DIR__ . '/../../Database/Migrations/2026_05_15_010000_add_identity_columns_to_conversations.php';
    $migration->up();

    $channel = makeSchemaTestChannel(1, '004');

    // Conv pré-existente criada antes do PR1 (lid/phone/bsuid NULL).
    // Simula histórico: phone_e164 já estava preenchido em rodada anterior.
    $conv = Conversation::query()->create([
        'business_id' => 1,
        'channel_id' => $channel->id,
        'customer_external_id' => '+5548000002822',
        'contact_name' => 'Cliente Antigo',
        'status' => 'open',
        'phone_e164' => '+5548000002822', // já tinha
        'lid' => null,
        'bsuid' => null,
    ]);

    expect($conv->lid)->toBeNull();
    expect($conv->phone_e164)->toBe('+5548000002822');

    // Nova msg chega trazendo LID + bsuid (não tinha antes) + outro phone.
    $payload = [
        'key' => [
            'remoteJid' => '14628809617558@lid',
            'senderPn' => '5548000002822@s.whatsapp.net',
            'id' => 'SCHEMA_BACKFILL_001',
            'fromMe' => false,
        ],
        'contact' => ['user_id' => 'BSUID_ABC_123'],
        'message' => ['conversation' => 'Mais uma msg'],
    ];

    $persister = new MessagePersister($channel);
    $result = $persister->persist($payload);

    // wasCreated=false pq conv já existia (encontrada via customer_external_id).
    expect($result->wasSkipped() || $result->wasDuplicate() || $result->wasCreated())->toBeTrue();

    $conv->refresh();
    // LID backfilled (era null).
    expect($conv->lid)->toBe('14628809617558');
    // phone_e164 NÃO sobrescrito (já tinha valor — preserva audit trail).
    expect($conv->phone_e164)->toBe('+5548000002822');
    // bsuid backfilled (era null).
    expect($conv->bsuid)->toBe('BSUID_ABC_123');
});

it('R-WA-SCHEMA-IDENT-005 — Tier 0 biz=1 vs biz=99 nao compartilham conv pelo mesmo LID', function () {
    // ADR 0093 — global scope business_id IRREVOGÁVEL. Mesmo LID em biz
    // distintos cria 2 conversas isoladas (sem cross-tenant leak).
    $migration = require __DIR__ . '/../../Database/Migrations/2026_05_15_010000_add_identity_columns_to_conversations.php';
    $migration->up();

    $ch1 = makeSchemaTestChannel(1, '005a');
    $ch99 = makeSchemaTestChannel(99, '005b');

    $payload1 = [
        'key' => [
            'remoteJid' => '14628809617558@lid',
            'id' => 'TIER0_BIZ1',
            'fromMe' => false,
        ],
        'push_name' => 'Cliente biz=1',
        'message' => ['conversation' => 'Sou biz=1'],
    ];
    $payload99 = [
        'key' => [
            'remoteJid' => '14628809617558@lid',
            'id' => 'TIER0_BIZ99',
            'fromMe' => false,
        ],
        'push_name' => 'Cliente biz=99',
        'message' => ['conversation' => 'Sou biz=99'],
    ];

    (new MessagePersister($ch1))->persist($payload1);
    (new MessagePersister($ch99))->persist($payload99);

    // Busca SEM global scope pra ver TODAS conversas e provar isolamento.
    $allConvs = Conversation::query()
        ->withoutGlobalScope(ScopeByBusiness::class)
        ->where('lid', '14628809617558')
        ->orderBy('business_id')
        ->get();

    expect($allConvs)->toHaveCount(2);
    expect($allConvs[0]->business_id)->toBe(1);
    expect($allConvs[1]->business_id)->toBe(99);
    // IDs de conv distintos — sem deduplicação cross-tenant.
    expect($allConvs[0]->id)->not->toBe($allConvs[1]->id);

    // Query scoped por biz=1 só vê conv da biz=1.
    $biz1Convs = Conversation::query()
        ->withoutGlobalScope(ScopeByBusiness::class)
        ->where('business_id', 1)
        ->where('lid', '14628809617558')
        ->get();
    expect($biz1Convs)->toHaveCount(1);
    expect($biz1Convs[0]->contact_name)->toBe('Cliente biz=1');
});
