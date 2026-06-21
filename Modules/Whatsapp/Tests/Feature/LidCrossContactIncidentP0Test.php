<?php

declare(strict_types=1);

use App\Contact;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Entities\Conversation;
use Modules\Whatsapp\Entities\LidPhoneMap;
use Modules\Whatsapp\Services\Contacts\ConversationContactLinker;
use Modules\Whatsapp\Services\Contacts\LidPhoneResolver;
use Modules\Whatsapp\Services\Webhook\MessagePersister;

uses(Tests\TestCase::class);

/**
 * Regression P0 do incident 2026-05-14 — todas as 81 msgs do Wagner caíram
 * no contact Eliana via 3 falhas combinadas:
 *
 *   P0-1: ConversationContactLinker fuzzy LIKE tail4 (4 dígitos) → cross-contact.
 *   P0-2: LidPhoneResolver::record(source=manual) sem evidência webhook prévia.
 *   P0-3: MessagePersister NÃO consultava LidPhoneResolver no path history-sync.
 *
 * Cada `it` aqui prova UMA das 3 defesas — se algum patch reverter, o teste
 * quebra. Mantém o cenário do incident catalogado em
 * `memory/sessions/2026-05-14-whatsapp-incident-inbox-lid-cross-contact.md`.
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('era-sqlite: schema sintético manual incompatível com MySQL persistente — quarentena Onda 2 SDD floor; burn-down converte depois.');
    }

    foreach (['conversations', 'channels', 'messages', 'contacts', 'whatsapp_lid_pn_map'] as $t) {
        Schema::dropIfExists($t);
    }

    Schema::create('contacts', function ($table) {
        $table->increments('id');
        $table->unsignedInteger('business_id');
        $table->string('contact_id', 191)->nullable();
        $table->string('name', 191);
        $table->string('mobile', 191)->nullable();
        $table->string('landline', 191)->nullable();
        $table->string('alternate_number', 191)->nullable();
        $table->string('type', 191)->default('customer');
        $table->string('contact_status', 20)->default('active');
        $table->unsignedInteger('created_by')->nullable();
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
        $table->string('channel_health', 20)->default('never_checked');
        $table->unsignedInteger('channel_health_consecutive_failures')->default(0);
        $table->timestamp('last_health_check_at')->nullable();
        $table->text('last_health_message')->nullable();
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
        $table->text('body')->nullable();
        $table->json('payload')->nullable();
        $table->string('status', 20);
        $table->string('sender_kind', 20)->nullable();
        $table->string('media_url', 500)->nullable();
        $table->string('media_mime', 100)->nullable();
        $table->unsignedBigInteger('media_size_bytes')->nullable();
        $table->unsignedSmallInteger('media_duration_s')->nullable();
        $table->string('media_filename', 255)->nullable();
        $table->string('media_download_status', 30)->default('pending');
        $table->timestamp('created_at')->useCurrent();
        $table->timestamp('updated_at')->nullable();
        $table->unique('provider_message_id', 'msgs_provider_msg_uniq2');
    });

    Schema::create('whatsapp_lid_pn_map', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->string('lid', 100);
        $table->string('phone_e164', 30)->nullable();
        $table->string('source', 30);
        $table->timestamp('first_seen_at');
        $table->timestamp('last_seen_at');
        $table->timestamps();
        $table->unique(['business_id', 'lid'], 'lid_pn_biz_lid_uniq');
    });
});

function lidMakeChannel(int $bizId, string $uuidSuffix = '000'): Channel
{
    return Channel::query()->create([
        'business_id' => $bizId,
        'channel_uuid' => 'aaaaaaaa-0000-0000-0000-' . str_pad($uuidSuffix, 12, '0', STR_PAD_LEFT),
        'label' => 'X',
        'type' => Channel::TYPE_WHATSAPP_BAILEYS,
        'status' => 'active',
    ]);
}

// ---------------------------------------------------------------------------
// P0-1: ConversationContactLinker — fuzzy tail4 NÃO pode cross-contact
// ---------------------------------------------------------------------------

it('R-WA-INCIDENT-2026-05-14-P0-1 — Linker NÃO faz cross-contact via tail4 coincidente', function () {
    // Cenário reconstruído: Wagner com phone +5548999872822 (suffix8 = 99872822).
    // Eliana com alternate_number antigo "(48) 999872822 - errado salvo nele"
    // (suffix8 idêntico). ANTES do patch P0-1, `tail4=2822` bateria também em
    // Larissa que tivesse 2822 em qualquer phone — falso positivo.
    Contact::create([
        'business_id' => 1, 'name' => 'Larissa Vestuário',
        'mobile' => '+5548999111****2822', // contém 2822 mas suffix8 ≠ Wagner
        'type' => 'customer', 'contact_id' => 'CO_LARISSA',
    ]);
    $wagner = Contact::create([
        'business_id' => 1, 'name' => 'WAGNER ROCHA ARAUJO',
        'mobile' => '+5548999872822',
        'type' => 'customer', 'contact_id' => 'CO_WAGNER',
    ]);

    $channel = lidMakeChannel(1, '101');
    $conv = Conversation::query()->create([
        'business_id' => 1, 'channel_id' => $channel->id,
        'customer_external_id' => '+5548999872822',
        'contact_name' => '+5548999872822',
        'contact_id' => null, 'status' => 'open',
    ]);

    app(ConversationContactLinker::class)->tryLink($conv);
    $conv->refresh();

    // Patch P0-1 garante: suffix8 = 99872822 exact match → linka Wagner, NÃO Larissa
    expect($conv->contact_id)->toBe($wagner->id);
});

it('R-WA-INCIDENT-2026-05-14-P0-1b — Linker mantém match exact E.164 sem ambiguidade', function () {
    $contact = Contact::create([
        'business_id' => 1, 'name' => 'Match Exato',
        'mobile' => '+5548999872822',
        'type' => 'customer', 'contact_id' => 'CO_EXATO',
    ]);
    $channel = lidMakeChannel(1, '102');
    $conv = Conversation::query()->create([
        'business_id' => 1, 'channel_id' => $channel->id,
        'customer_external_id' => '+5548999872822',
        'contact_name' => '+5548999872822',
        'contact_id' => null, 'status' => 'open',
    ]);

    app(ConversationContactLinker::class)->tryLink($conv);

    expect($conv->fresh()->contact_id)->toBe($contact->id);
});

// ---------------------------------------------------------------------------
// P0-2: LidPhoneResolver — source=manual exige evidência webhook prévia
// ---------------------------------------------------------------------------

it('R-WA-INCIDENT-2026-05-14-P0-2 — Resolver REJEITA source=manual sem webhook prévio', function () {
    $resolver = app(LidPhoneResolver::class);

    // Tenta criar manual direto (cenário drift: alguém SSH/CLI tentando cadastrar)
    expect(fn () => $resolver->record(1, '14628809617558@lid', '+5548999872822', LidPhoneMap::SOURCE_MANUAL))
        ->toThrow(\DomainException::class, 'webhook_senderPn prévio');

    // Defesa: row não foi criada (rollback de DomainException)
    expect(LidPhoneMap::query()->withoutGlobalScopes()->where('lid', '14628809617558')->exists())->toBeFalse();
});

it('R-WA-INCIDENT-2026-05-14-P0-2b — Resolver ACEITA source=manual após webhook ter visto o LID', function () {
    $resolver = app(LidPhoneResolver::class);

    // Webhook real cadastra LID com phone=null (fluxo normal sem senderPn)
    $resolver->record(1, '14628809617558@lid', null, LidPhoneMap::SOURCE_WEBHOOK_SENDER_PN);

    // Agora operador pode atualizar manualmente — webhook serve como atestado
    $row = $resolver->record(1, '14628809617558@lid', '+5548999872822', LidPhoneMap::SOURCE_MANUAL);

    expect($row)->not->toBeNull();
    expect($row->phone_e164)->toBe('+5548999872822');
});

it('R-WA-INCIDENT-2026-05-14-P0-2c — Tier 0: webhook evidence biz=1 não autoriza manual biz=99', function () {
    $resolver = app(LidPhoneResolver::class);

    // Webhook só viu LID em biz=1
    $resolver->record(1, '14628809617558@lid', null, LidPhoneMap::SOURCE_WEBHOOK_SENDER_PN);

    // Tentativa manual em biz=99 sem webhook prévio biz=99 → bloqueia
    expect(fn () => $resolver->record(99, '14628809617558@lid', '+5548999872822', LidPhoneMap::SOURCE_MANUAL))
        ->toThrow(\DomainException::class);
});

// ---------------------------------------------------------------------------
// P0-3: MessagePersister consulta LidPhoneResolver no path history-sync
// ---------------------------------------------------------------------------

it('R-WA-INCIDENT-2026-05-14-P0-3 — MessagePersister history-sync resolve LID via cache do Resolver', function () {
    // Pre-cond: webhook anterior já registrou LID + senderPn → cache populado
    app(LidPhoneResolver::class)->record(
        1, '14628809617558@lid', '+5548999872822@s.whatsapp.net',
        LidPhoneMap::SOURCE_WEBHOOK_SENDER_PN,
    );

    $channel = lidMakeChannel(1, '301');

    $persister = new MessagePersister($channel);
    $result = $persister->persist([
        'key' => [
            'remoteJid' => '14628809617558@lid',
            'id' => 'MSG_ABC_HIST_001',
            'fromMe' => false,
            // SEM senderPn — cenário history sync
        ],
        'message' => ['conversation' => 'Oi (histórico)'],
        'is_history_sync' => true,
    ], bumpUnread: false);

    expect($result->conversation)->not->toBeNull();
    // Patch P0-3 garante: customer_external_id veio do cache do resolver,
    // NÃO do LID cru (+14628809617558).
    expect($result->conversation->customer_external_id)->toBe('+5548999872822');
});

it('R-WA-INCIDENT-2026-05-14-P0-3b — history-sync sem cache REGISTRA LID com phone=NULL pra rastreio', function () {
    $channel = lidMakeChannel(1, '302');
    $persister = new MessagePersister($channel);

    $persister->persist([
        'key' => [
            'remoteJid' => '99999999999999@lid',
            'id' => 'MSG_NEW_LID_001',
            'fromMe' => false,
        ],
        'message' => ['conversation' => 'Mensagem de LID nunca visto antes'],
        'is_history_sync' => true,
    ], bumpUnread: false);

    // Resolver foi acionado pra cachear LID novo (phone=null = pendente descoberta)
    expect(LidPhoneMap::query()->withoutGlobalScopes()
        ->where('business_id', 1)
        ->where('lid', '99999999999999')
        ->exists())->toBeTrue();
});
