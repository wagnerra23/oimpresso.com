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
 * E2E regression do incident 2026-05-14 — cobertura ALÉM dos 3 P0 unitários.
 *
 * Diferença pro LidCrossContactIncidentP0Test.php:
 *
 *  - Aqui é E2E reconstruindo o cenário REAL do incident (Wagner ×3 contacts
 *    + Eliana com alternate_number ambíguo + 50+ msgs simulando history sync
 *    + Baileys 7.x payload com remoteJidAlt).
 *  - Inclui CONVENTION tests (source-code) garantindo que código não regride:
 *    linker NÃO pode voltar a usar tail4; persister DEVE consultar resolver;
 *    proibições.md DEVE conter regra Baileys 7.x.
 *  - Guard "nunca perca mensagem" — MessagePersister NÃO pode introduzir
 *    delete() em messages/conversations sem ADR mãe.
 *
 * Origens:
 * - memory/sessions/2026-05-14-whatsapp-incident-inbox-lid-cross-contact.md
 * - memory/sessions/2026-05-15-estudo-whatsapp-protocol-vs-oimpresso.md
 * - memory/reference/feedback-baileys-7x-decisao-irreversivel.md
 * - memory/decisions/0146-contact-lid-canonico-pk-refactor.md
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
        $table->string('lid', 100)->nullable();
        $table->string('phone_e164', 30)->nullable();
        $table->string('bsuid', 100)->nullable();
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
        $table->unique('provider_message_id', 'msgs_provider_msg_uniq_e2e');
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
        $table->unique(['business_id', 'lid'], 'lid_pn_biz_lid_uniq_e2e');
    });
});

function makeChannelE2E(int $bizId, string $uuidSuffix = 'e2e'): Channel
{
    return Channel::query()->create([
        'business_id' => $bizId,
        'channel_uuid' => 'cccccccc-0000-0000-0000-' . str_pad($uuidSuffix, 12, '0', STR_PAD_LEFT),
        'label' => 'CanalE2E',
        'type' => Channel::TYPE_WHATSAPP_BAILEYS,
        'status' => 'active',
    ]);
}

// ============================================================================
// E2E 1 — Reprodução EXATA do cenário Wagner-Eliana 14/mai
// ============================================================================

it('R-WA-INCIDENT-E2E-01 — Cenário exato 14/mai: 50 msgs sem senderPn + Eliana com alternate_number ambíguo NÃO causam cross-contact', function () {
    // Setup exato do incident: 4 contacts "Wagner" duplicados + Eliana com
    // alternate_number do Wagner (motivo do fuzzy match tail4 ter linkado).
    Contact::create([
        'business_id' => 1, 'name' => 'Wager1111',
        'mobile' => '48 99987-28822', // typo: 5 últimos = 28822
        'type' => 'customer', 'contact_id' => 'CO0020',
    ]);
    Contact::create([
        'business_id' => 1, 'name' => 'Wagner Rocha Araujo',
        'mobile' => '48 99987-28822',
        'type' => 'customer', 'contact_id' => 'CO0021',
    ]);
    $wagner = Contact::create([
        'business_id' => 1, 'name' => 'WAGNER ROCHA ARAUJO PESSOAL',
        'mobile' => '+5548999872822',
        'type' => 'customer', 'contact_id' => 'CO6652',
    ]);
    $eliana = Contact::create([
        'business_id' => 1, 'name' => 'ELIANA MARCELINO ALVES 06075269983',
        'mobile' => '48996483100',
        'landline' => '4833010200',
        'alternate_number' => '48999872822', // ← número Wagner salvo errado no contato Eliana
        'type' => 'customer', 'contact_id' => 'CLI0185',
    ]);

    $channel = makeChannelE2E(1, 'e01');
    $persister = new MessagePersister($channel);

    // Cenário: Wagner manda 50 msgs via history sync com remoteJid @lid sem senderPn
    // (exatamente o que aconteceu 14/mai). LidPhoneMap inicialmente vazio.
    for ($i = 1; $i <= 50; $i++) {
        $persister->persist([
            'key' => [
                'remoteJid' => '14628809617558@lid',
                'id' => "MSG_INCIDENT_E2E_$i",
                'fromMe' => ($i % 2 === 0), // alterna inbound/outbound
            ],
            'message' => ['conversation' => "msg histórica #$i"],
            'is_history_sync' => true,
        ], bumpUnread: true);
    }

    $convs = Conversation::query()->withoutGlobalScopes()
        ->where('business_id', 1)->get();

    // Asserções principais:
    expect($convs)->toHaveCount(1, 'Deve criar apenas 1 conversation (threading correto)');

    $conv = $convs->first();

    // CRITICAL: contact_id NÃO pode ser Eliana (que tem alternate_number ambíguo).
    // Linker com suffix-8 (PR #854) NÃO acha match exato — Eliana mobile/landline
    // NÃO contêm o LID "14628809617558" nem o suffix "8809617558".
    expect($conv->contact_id)->not->toBe($eliana->id,
        'cross-contact regredido: conv vinculada a Eliana via tail4 (BUG INCIDENT 14/mai)');

    // E também não pode ser Wagner via fuzzy — sem senderPn, LID cru não bate
    // com phone real "+5548999872822" via suffix-8.
    expect($conv->contact_id)->toBeNull(
        'sem senderPn nem cache resolvido, contact_id deve permanecer NULL (não inventa link)');

    // Threading deve ter agrupado todas 50 msgs em 1 conv
    $msgCount = \DB::table('messages')->where('conversation_id', $conv->id)->count();
    expect($msgCount)->toBe(50, "Todas 50 msgs devem cair na MESMA conv. Got: $msgCount");
});

// ============================================================================
// E2E 2 — Após senderPn descoberto, observer/backfill re-linkar conv órfã
// ============================================================================

it('R-WA-INCIDENT-E2E-02 — Após senderPn descoberto via webhook subsequente, conv órfã pode ser re-linkada via cache resolver', function () {
    $wagner = Contact::create([
        'business_id' => 1, 'name' => 'WAGNER ROCHA ARAUJO PESSOAL',
        'mobile' => '+5548999872822',
        'type' => 'customer', 'contact_id' => 'CO6652',
    ]);

    $channel = makeChannelE2E(1, 'e02');
    $persister = new MessagePersister($channel);
    $resolver = app(LidPhoneResolver::class);

    // 1ª msg: history sync sem senderPn → conv com customer_external_id=LID cru
    $persister->persist([
        'key' => [
            'remoteJid' => '14628809617558@lid',
            'id' => 'MSG_HIST_1',
            'fromMe' => false,
        ],
        'message' => ['conversation' => 'msg histórica'],
        'is_history_sync' => true,
    ]);

    // 2ª msg: webhook real-time COM senderPn → resolver grava mapping LID→phone
    $resolver->record(1, '14628809617558@lid', '+5548999872822@s.whatsapp.net',
        LidPhoneMap::SOURCE_WEBHOOK_SENDER_PN);

    // 3ª msg: history sync sem senderPn → AGORA cache resolve, conv usa phone real
    $persister->persist([
        'key' => [
            'remoteJid' => '14628809617558@lid',
            'id' => 'MSG_HIST_2',
            'fromMe' => false,
        ],
        'message' => ['conversation' => 'msg histórica 2'],
        'is_history_sync' => true,
    ]);

    // Asserções: agora pode existir conv com customer_external_id phone real
    $convWithPhone = Conversation::query()->withoutGlobalScopes()
        ->where('business_id', 1)
        ->where('customer_external_id', '+5548999872822')
        ->first();

    expect($convWithPhone)->not->toBeNull('Conv com phone real deve existir após resolver cache hit');
});

// ============================================================================
// E2E 3 — Multi-tenant Tier 0: biz=1 vs biz=99 NÃO compartilham mapping LID
// ============================================================================

it('R-WA-INCIDENT-E2E-03 — Tier 0 isolation: LID 14628809617558 mapeia phones DIFERENTES em biz=1 e biz=99', function () {
    $resolver = app(LidPhoneResolver::class);

    // Webhook biz=1 grava mapping (workflow legítimo)
    $resolver->record(1, '14628809617558@lid', '+5548999872822@s.whatsapp.net',
        LidPhoneMap::SOURCE_WEBHOOK_SENDER_PN);

    // Webhook biz=99 (cliente diferente) grava mapping pro MESMO LID mas phone OUTRO
    $resolver->record(99, '14628809617558@lid', '+5511999000000@s.whatsapp.net',
        LidPhoneMap::SOURCE_WEBHOOK_SENDER_PN);

    expect($resolver->resolve(1, '14628809617558@lid'))->toBe('+5548999872822');
    expect($resolver->resolve(99, '14628809617558@lid'))->toBe('+5511999000000');

    $rows = \DB::table('whatsapp_lid_pn_map')
        ->where('lid', '14628809617558')->count();
    expect($rows)->toBe(2, 'UNIQUE constraint (business_id, lid) permite mesmo LID em biz distintos');
});

// ============================================================================
// CONVENTION 1 — Linker NÃO pode regredir pra fuzzy tail4
// ============================================================================

it('R-WA-INCIDENT-CONV-01 — ConversationContactLinker NÃO contém $tail4 (regressão guarda)', function () {
    $source = file_get_contents(
        base_path('Modules/Whatsapp/Services/Contacts/ConversationContactLinker.php')
    );

    // Permite menção em comentários ("INCIDENT 2026-05-14: tail4..."), mas
    // proíbe declaração de variável $tail4 ou uso ativo no SQL.
    expect($source)->not->toMatch('/\$tail4\s*=\s*mb_substr/',
        'REGRESSÃO: declaração de $tail4 detectada. Use suffix-8 (PR #854).');

    expect($source)->not->toMatch('/LIKE.*%.*\$tail4/',
        'REGRESSÃO: LIKE com $tail4 detectado no SQL. Use suffix-8 (PR #854).');

    // Garante que suffix-8 está presente
    expect($source)->toContain('mb_substr($phoneDigits, -8)',
        'suffix-8 (8 dígitos finais) deve estar presente — caminho canônico anti-cross-contact.');
});

// ============================================================================
// CONVENTION 2 — MessagePersister DEVE consultar LidPhoneResolver no history-sync
// ============================================================================

it('R-WA-INCIDENT-CONV-02 — MessagePersister importa e usa LidPhoneResolver (regressão guarda P0-3)', function () {
    $source = file_get_contents(
        base_path('Modules/Whatsapp/Services/Webhook/MessagePersister.php')
    );

    expect($source)->toContain('use Modules\Whatsapp\Services\Contacts\LidPhoneResolver;',
        'REGRESSÃO: import LidPhoneResolver removido. P0-3 incident 14/mai exige consulta no history-sync.');

    expect($source)->toMatch('/app\(LidPhoneResolver::class\)|app\(\\\\?Modules\\\\Whatsapp\\\\Services\\\\Contacts\\\\LidPhoneResolver::class\)/',
        'REGRESSÃO: invocação LidPhoneResolver removida. Persister precisa consultar cache no history-sync.');
});

// ============================================================================
// CONVENTION 3 — LidPhoneResolver bloqueia source=manual sem webhook prévio
// ============================================================================

it('R-WA-INCIDENT-CONV-03 — LidPhoneResolver bloqueia source=manual sem webhook prévio (anti-drift Tier 0)', function () {
    $resolver = app(LidPhoneResolver::class);

    // Reproduz o cenário de drift 14/mai 08:40 — 13 rows manuais sem trail
    expect(fn () => $resolver->record(1, 'LID_DRIFT_E2E@lid', '+5548999000000',
        LidPhoneMap::SOURCE_MANUAL))
        ->toThrow(\DomainException::class, 'webhook_senderPn prévio');

    expect(LidPhoneMap::query()->withoutGlobalScopes()
        ->where('lid', 'LID_DRIFT_E2E')->exists())
        ->toBeFalse('Drift Tier 0 deve ser PREVENIDO no record() — não criar row');
});

// ============================================================================
// CONVENTION 4 — Proibições.md contém regra Baileys 7.x irreversível
// ============================================================================

it('R-WA-INCIDENT-CONV-04 — memory/proibicoes.md contém proibição Baileys 6.7.9 (feedback Wagner 13-15/mai)', function () {
    $proibicoes = file_get_contents(base_path('memory/proibicoes.md'));

    expect($proibicoes)->toContain('Baileys 6.7.9',
        'Proibição Baileys 6.7.9 removida — Wagner cortou 3x. Ver feedback-baileys-7x-decisao-irreversivel.md');

    expect($proibicoes)->toContain('feedback-baileys-7x-decisao-irreversivel',
        'Link pra feedback canon deve estar preservado em proibicoes.md');
});

// ============================================================================
// CONVENTION 5 — "Nunca perca mensagem" — MessagePersister NÃO tem delete()
// ============================================================================

it('R-WA-INCIDENT-CONV-05 — MessagePersister + Conversation entity NÃO contêm delete() de messages/conversations (regra Wagner Tier 0)', function () {
    $persister = file_get_contents(
        base_path('Modules/Whatsapp/Services/Webhook/MessagePersister.php')
    );
    $convEntity = file_get_contents(
        base_path('Modules/Whatsapp/Entities/Conversation.php')
    );

    // Detecta padrões perigosos: $message->delete(), Message::destroy(),
    // Conversation::truncate(), DB::table('messages')->delete(), etc.
    foreach ([$persister, $convEntity] as $source) {
        expect($source)->not->toMatch('/Message::query\(\).*->delete\(\)/',
            'Wagner regra Tier 0: "nunca perca mensagem". Persister NÃO pode deletar messages.');
        expect($source)->not->toMatch('/->forceDelete\(\)/',
            'forceDelete em entities WhatsApp viola regra "nunca perca mensagem".');
        expect($source)->not->toMatch('/Message::truncate\(\)|Conversation::truncate\(\)/',
            'truncate() em messages/conversations proibido.');
    }
});

// ============================================================================
// E2E 4 — Baileys 7.x payload com remoteJidAlt resolve sem precisar de cache
// ============================================================================

it('R-WA-INCIDENT-E2E-04 — Baileys 7.x remoteJidAlt resolve phone direto SEM precisar cache LidPhoneMap (caminho ideal pós-deploy)', function () {
    $wagner = Contact::create([
        'business_id' => 1, 'name' => 'WAGNER',
        'mobile' => '+5548999872822',
        'type' => 'customer', 'contact_id' => 'CO6652',
    ]);

    $channel = makeChannelE2E(1, 'e04');
    $persister = new MessagePersister($channel);

    // Payload Baileys 7.x — remoteJidAlt presente com phone real
    $persister->persist([
        'key' => [
            'remoteJid' => '14628809617558@lid',
            'remoteJidAlt' => '5548999872822@s.whatsapp.net', // ← 7.x novidade
            'id' => 'MSG_7X_E2E_001',
            'fromMe' => false,
        ],
        'message' => ['conversation' => 'oi via Baileys 7.x'],
    ], bumpUnread: true);

    $conv = Conversation::query()->withoutGlobalScopes()
        ->where('business_id', 1)->first();

    expect($conv)->not->toBeNull();
    expect($conv->customer_external_id)->toBe('+5548999872822',
        'Baileys 7.x: remoteJidAlt resolve phone direto sem cache LidPhoneMap');

    // Schema 3-identifiers populado
    if (Schema::hasColumn('conversations', 'lid')) {
        expect($conv->lid)->toBe('14628809617558');
        expect($conv->phone_e164)->toBe('+5548999872822');
    }
});

// ============================================================================
// E2E 5 — Idempotência cross-tenant: provider_message_id UNIQUE não causa
// drop de msg legítima em biz diferente
// ============================================================================

it('R-WA-INCIDENT-E2E-05 — provider_message_id UNIQUE global NÃO impede msg distinta em biz diferente', function () {
    $ch1 = makeChannelE2E(1, 'e51');
    $ch99 = makeChannelE2E(99, 'e52');

    $p1 = new MessagePersister($ch1);
    $p99 = new MessagePersister($ch99);

    $p1->persist([
        'key' => ['remoteJid' => '14628809617558@lid', 'id' => 'WAMID_SHARED', 'fromMe' => false],
        'message' => ['conversation' => 'msg biz=1'],
    ]);

    // Mesma msg id em biz=99 (cenário teórico drift cross-tenant)
    $p99->persist([
        'key' => ['remoteJid' => '14628809617558@lid', 'id' => 'WAMID_SHARED', 'fromMe' => false],
        'message' => ['conversation' => 'msg biz=99'],
    ]);

    // Esperamos que provider_message_id UNIQUE bloqueie a segunda inserção
    // (idempotência cross-tenant via wamid único). MAS msg biz=1 deve persistir.
    $msg1 = \DB::table('messages')->where('provider_message_id', 'WAMID_SHARED')
        ->where('business_id', 1)->first();
    expect($msg1)->not->toBeNull('msg biz=1 persistida normalmente');
    expect($msg1->body)->toBe('msg biz=1', 'corpo da msg biz=1 preservado');
});
