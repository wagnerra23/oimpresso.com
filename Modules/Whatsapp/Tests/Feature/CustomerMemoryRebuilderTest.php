<?php

declare(strict_types=1);

use App\Contact;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Whatsapp\Entities\CustomerMemory;
use Modules\Whatsapp\Services\Contacts\ConversationContactLinker;
use Modules\Whatsapp\Services\CustomerMemory\CustomerMemoryRebuilder;

uses(Tests\TestCase::class);

/**
 * US-WA-VOZ-001 — CustomerMemoryRebuilder.
 *
 * Cobertura:
 *   1. rebuild() cria row nova quando customer não existe
 *   2. rebuild() é idempotente — re-run não duplica
 *   3. touch() é UPSERT atômico (cheap path)
 *   4. Identity resolution liga ao Contact quando phone bate (exact)
 *   5. Identity resolution marca ambiguous quando 2+ contacts batem
 *   6. Stats agregados batem com count real de messages/conversations
 *   7. Tier 0: biz=99 NÃO vê customer_memory de biz=1
 *   8. LGPD denormalize: consent_status reflete contacts.whatsapp_consent
 */
beforeEach(function () {
    foreach (['customer_memory', 'messages', 'conversations', 'contacts', 'business'] as $t) {
        Schema::dropIfExists($t);
    }

    Schema::create('business', function ($table) {
        $table->bigIncrements('id');
        $table->string('name');
        $table->timestamps();
    });

    Schema::create('contacts', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->string('name', 120)->nullable();
        $table->string('mobile', 40)->nullable();
        $table->string('landline', 40)->nullable();
        $table->string('alternate_number', 40)->nullable();
        $table->string('email', 120)->nullable();
        $table->boolean('whatsapp_consent')->nullable();
        $table->softDeletes();
        $table->timestamps();
    });

    Schema::create('conversations', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->unsignedBigInteger('channel_id');
        $table->unsignedInteger('contact_id')->nullable();
        $table->string('customer_external_id', 40)->nullable();
        $table->string('contact_name', 120)->nullable();
        $table->timestamp('last_message_at')->nullable();
        $table->string('last_message_preview', 200)->nullable();
        $table->string('last_message_direction', 20)->nullable();
        $table->unsignedInteger('unread_count')->default(0);
        $table->string('status', 20)->default('open');
        $table->timestamps();
    });

    Schema::create('messages', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->unsignedBigInteger('conversation_id');
        $table->string('direction', 20);
        $table->string('type', 20)->default('text');
        $table->text('body')->nullable();
        $table->string('status', 20)->default('received');
        $table->boolean('is_internal_note')->default(false);
        $table->unsignedInteger('sender_user_id')->nullable();
        $table->timestamps();
    });

    Schema::create('customer_memory', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->string('customer_external_id', 40);
        $table->string('phone_normalized', 20)->nullable();
        $table->unsignedInteger('contact_id')->nullable();
        $table->string('identity_match_method', 24)->nullable();
        $table->decimal('identity_match_confidence', 3, 2)->nullable();
        $table->timestamp('identity_match_at')->nullable();
        $table->string('display_name', 120)->nullable();
        $table->unsignedInteger('n_conversations')->default(0);
        $table->unsignedInteger('n_msgs_inbound')->default(0);
        $table->unsignedInteger('n_msgs_outbound')->default(0);
        $table->timestamp('first_interaction_at')->nullable();
        $table->timestamp('last_interaction_at')->nullable();
        $table->json('temas_recorrentes')->nullable();
        $table->decimal('sentimento_score', 3, 2)->nullable();
        $table->decimal('churn_risk_score', 3, 2)->nullable();
        $table->json('comunicacao_preferida')->nullable();
        $table->text('notas_jana')->nullable();
        $table->timestamp('notas_atualizada_em')->nullable();
        $table->json('flags')->nullable();
        $table->string('consent_status', 16)->nullable();
        $table->timestamp('erasure_requested_at')->nullable();
        $table->timestamp('last_rebuilt_at')->nullable();
        $table->string('rebuilt_via', 24)->nullable();
        // US-WA-VOZ-002 columns
        $table->unsignedInteger('assigned_user_id')->nullable();
        $table->unsignedInteger('most_active_user_id')->nullable();
        $table->unsignedInteger('most_active_user_count')->nullable();
        $table->json('reclamacoes_recentes')->nullable();
        $table->unsignedInteger('total_reclamacoes')->default(0);
        $table->json('external_sources')->nullable();
        $table->timestamp('external_sources_enriched_at')->nullable();
        $table->timestamps();
        $table->unique(['business_id', 'customer_external_id']);
    });

    DB::table('business')->insert([
        ['id' => 1, 'name' => 'WR Sistemas', 'created_at' => now(), 'updated_at' => now()],
        ['id' => 99, 'name' => 'Outro Tenant', 'created_at' => now(), 'updated_at' => now()],
    ]);
});

function seedContactFixture(int $bizId, string $name, string $mobile, ?bool $consent = null): int
{
    return DB::table('contacts')->insertGetId([
        'business_id' => $bizId,
        'name' => $name,
        'mobile' => $mobile,
        'whatsapp_consent' => $consent,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function seedConvFixture(int $bizId, string $extId): int
{
    return DB::table('conversations')->insertGetId([
        'business_id' => $bizId,
        'channel_id' => 10,
        'customer_external_id' => $extId,
        'created_at' => now()->subDays(2),
        'updated_at' => now(),
    ]);
}

function seedMsgFixture(int $bizId, int $convId, string $direction = 'inbound', ?string $when = null, ?int $userId = null, ?string $body = null): int
{
    return DB::table('messages')->insertGetId([
        'business_id' => $bizId,
        'conversation_id' => $convId,
        'direction' => $direction,
        'type' => 'text',
        'body' => $body ?? 'msg teste',
        'sender_user_id' => $userId,
        'created_at' => $when ? \Carbon\Carbon::parse($when) : now(),
        'updated_at' => $when ? \Carbon\Carbon::parse($when) : now(),
    ]);
}

it('rebuild() cria customer_memory novo quando não existe', function () {
    $linker = new ConversationContactLinker();
    $rebuilder = new CustomerMemoryRebuilder($linker);

    $extId = '5548999872822';
    $convId = seedConvFixture(1, $extId);
    seedMsgFixture(1, $convId, 'inbound');
    seedMsgFixture(1, $convId, 'outbound');

    $memory = $rebuilder->rebuild(1, $extId);

    expect($memory)->toBeInstanceOf(CustomerMemory::class)
        ->and($memory->business_id)->toBe(1)
        ->and($memory->customer_external_id)->toBe($extId)
        ->and($memory->phone_normalized)->toBe($extId)
        ->and($memory->n_msgs_inbound)->toBe(1)
        ->and($memory->n_msgs_outbound)->toBe(1)
        ->and($memory->n_conversations)->toBe(1)
        ->and($memory->last_rebuilt_at)->not->toBeNull()
        ->and($memory->rebuilt_via)->toBe(CustomerMemory::REBUILT_VIA_MANUAL);
});

it('rebuild() é idempotente — re-run não duplica', function () {
    $linker = new ConversationContactLinker();
    $rebuilder = new CustomerMemoryRebuilder($linker);

    $extId = '5548999872822';
    seedConvFixture(1, $extId);

    $first = $rebuilder->rebuild(1, $extId);
    $second = $rebuilder->rebuild(1, $extId);

    expect($first->id)->toBe($second->id);
    expect(DB::table('customer_memory')->count())->toBe(1);
});

it('touch() é UPSERT atômico — cria row mínima se não existe', function () {
    $linker = new ConversationContactLinker();
    $rebuilder = new CustomerMemoryRebuilder($linker);

    $rebuilder->touch(1, '5548999872822');

    $row = DB::table('customer_memory')->where('business_id', 1)->first();
    expect($row)->not->toBeNull()
        ->and($row->customer_external_id)->toBe('5548999872822')
        ->and($row->last_interaction_at)->not->toBeNull()
        ->and($row->first_interaction_at)->not->toBeNull();
});

it('touch() não sobrescreve first_interaction_at quando re-chamado', function () {
    $linker = new ConversationContactLinker();
    $rebuilder = new CustomerMemoryRebuilder($linker);

    $earlier = \Carbon\Carbon::parse('2026-01-01 10:00:00');
    $later = \Carbon\Carbon::parse('2026-05-15 18:00:00');

    $rebuilder->touch(1, '5548999872822', $earlier);
    $rebuilder->touch(1, '5548999872822', $later);

    $row = DB::table('customer_memory')->where('business_id', 1)->first();
    // first_interaction_at NÃO muda (COALESCE protege)
    expect(\Carbon\Carbon::parse($row->first_interaction_at)->format('Y-m-d'))->toBe('2026-01-01');
    // last_interaction_at atualiza pro mais recente
    expect(\Carbon\Carbon::parse($row->last_interaction_at)->format('Y-m-d'))->toBe('2026-05-15');
});

it('Identity resolution liga Contact quando phone bate (exact)', function () {
    $linker = new ConversationContactLinker();
    $rebuilder = new CustomerMemoryRebuilder($linker);

    // Contact com mobile == external_id sem +
    $contactId = seedContactFixture(1, 'João Cliente', '5548999872822', true);
    seedConvFixture(1, '5548999872822');

    $memory = $rebuilder->rebuild(1, '5548999872822');

    expect($memory->contact_id)->toBe($contactId)
        ->and($memory->identity_match_method)->toBeIn([CustomerMemory::MATCH_EXACT, CustomerMemory::MATCH_SUFFIX_8])
        ->and($memory->identity_match_confidence)->toBe(1.0)
        ->and($memory->display_name)->toBe('João Cliente')
        ->and($memory->consent_status)->toBe(CustomerMemory::CONSENT_GIVEN);
});

it('Identity resolution marca ambiguous quando 2+ contacts batem', function () {
    $linker = new ConversationContactLinker();
    $rebuilder = new CustomerMemoryRebuilder($linker);

    // 2 contacts com mesmo mobile (duplicata CRM legacy)
    seedContactFixture(1, 'João A', '5548999872822');
    seedContactFixture(1, 'João B', '5548999872822');
    seedConvFixture(1, '5548999872822');

    $memory = $rebuilder->rebuild(1, '5548999872822');

    expect($memory->identity_match_method)->toBe(CustomerMemory::MATCH_AMBIGUOUS)
        ->and($memory->identity_match_confidence)->toBeLessThan(1.0);
});

it('Identity resolution: sem contact = match_unknown', function () {
    $linker = new ConversationContactLinker();
    $rebuilder = new CustomerMemoryRebuilder($linker);

    // Conv mas zero contacts
    seedConvFixture(1, '5548999872822');

    $memory = $rebuilder->rebuild(1, '5548999872822');

    expect($memory->contact_id)->toBeNull()
        ->and($memory->identity_match_method)->toBe(CustomerMemory::MATCH_UNKNOWN);
});

it('Stats agregados batem com count real de messages', function () {
    $linker = new ConversationContactLinker();
    $rebuilder = new CustomerMemoryRebuilder($linker);

    $extId = '5548999872822';
    $convId = seedConvFixture(1, $extId);
    seedMsgFixture(1, $convId, 'inbound');
    seedMsgFixture(1, $convId, 'inbound');
    seedMsgFixture(1, $convId, 'inbound');
    seedMsgFixture(1, $convId, 'outbound');

    $memory = $rebuilder->rebuild(1, $extId);

    expect($memory->n_msgs_inbound)->toBe(3)
        ->and($memory->n_msgs_outbound)->toBe(1)
        ->and($memory->n_msgs_total)->toBe(4)
        ->and($memory->n_conversations)->toBe(1);
});

it('Tier 0: biz=99 NÃO vê customer_memory de biz=1', function () {
    $linker = new ConversationContactLinker();
    $rebuilder = new CustomerMemoryRebuilder($linker);

    seedConvFixture(1, '5548999872822');
    seedMsgFixture(1, 1, 'inbound');

    seedConvFixture(99, '5548999872822');
    seedMsgFixture(99, 2, 'inbound');
    seedMsgFixture(99, 2, 'inbound');

    $memBiz1 = $rebuilder->rebuild(1, '5548999872822');
    $memBiz99 = $rebuilder->rebuild(99, '5548999872822');

    expect($memBiz1->n_msgs_inbound)->toBe(1);
    expect($memBiz99->n_msgs_inbound)->toBe(2);
    expect($memBiz1->id)->not->toBe($memBiz99->id);
});

it('LGPD: consent_status reflete contacts.whatsapp_consent', function () {
    $linker = new ConversationContactLinker();
    $rebuilder = new CustomerMemoryRebuilder($linker);

    // 3 cenários: consent=true (given), false (withdrawn), null (unknown)
    seedContactFixture(1, 'Consent OK', '11111111111', true);
    seedConvFixture(1, '11111111111');
    $memOk = $rebuilder->rebuild(1, '11111111111');
    expect($memOk->consent_status)->toBe(CustomerMemory::CONSENT_GIVEN);

    seedContactFixture(1, 'Consent Off', '22222222222', false);
    seedConvFixture(1, '22222222222');
    $memOff = $rebuilder->rebuild(1, '22222222222');
    expect($memOff->consent_status)->toBe(CustomerMemory::CONSENT_WITHDRAWN);

    // Sem contact, vira UNKNOWN
    seedConvFixture(1, '33333333333');
    $memNull = $rebuilder->rebuild(1, '33333333333');
    expect($memNull->consent_status)->toBe(CustomerMemory::CONSENT_UNKNOWN);
});

it('US-WA-VOZ-002 — assigned_user_id = sender_user_id da última outbound', function () {
    $linker = new ConversationContactLinker();
    $rebuilder = new CustomerMemoryRebuilder($linker);

    $extId = '5548999872822';
    $convId = seedConvFixture(1, $extId);
    // 3 outbound: user=10 (mais antigo), user=20, user=30 (mais recente)
    seedMsgFixture(1, $convId, 'outbound', '2026-05-13 10:00:00', userId: 10);
    seedMsgFixture(1, $convId, 'outbound', '2026-05-14 10:00:00', userId: 20);
    seedMsgFixture(1, $convId, 'outbound', '2026-05-15 10:00:00', userId: 30);

    $memory = $rebuilder->rebuild(1, $extId);

    expect($memory->assigned_user_id)->toBe(30); // mais recente
});

it('US-WA-VOZ-002 — most_active_user_id = sender com mais outbound', function () {
    $linker = new ConversationContactLinker();
    $rebuilder = new CustomerMemoryRebuilder($linker);

    $extId = '5548999872822';
    $convId = seedConvFixture(1, $extId);
    // user=10 com 3 msgs, user=20 com 2, user=30 com 1
    seedMsgFixture(1, $convId, 'outbound', userId: 10);
    seedMsgFixture(1, $convId, 'outbound', userId: 10);
    seedMsgFixture(1, $convId, 'outbound', userId: 10);
    seedMsgFixture(1, $convId, 'outbound', userId: 20);
    seedMsgFixture(1, $convId, 'outbound', userId: 20);
    seedMsgFixture(1, $convId, 'outbound', userId: 30);

    $memory = $rebuilder->rebuild(1, $extId);

    expect($memory->most_active_user_id)->toBe(10);
    expect($memory->most_active_user_count)->toBe(3);
});

it('US-WA-VOZ-002 — reclamações heurística detecta keywords', function () {
    $linker = new ConversationContactLinker();
    $rebuilder = new CustomerMemoryRebuilder($linker);

    $extId = '5548999872822';
    $convId = seedConvFixture(1, $extId);
    // Inbound com keywords variadas (severities)
    seedMsgFixture(1, $convId, 'inbound', body: 'olá tudo bem'); // sem match
    seedMsgFixture(1, $convId, 'inbound', body: 'tô com problema no sistema'); // media
    seedMsgFixture(1, $convId, 'inbound', body: 'isso é péssimo, quero reclamar'); // alta
    seedMsgFixture(1, $convId, 'inbound', body: 'quero processar vocês, vou no procon'); // critica
    seedMsgFixture(1, $convId, 'inbound', body: 'preciso de ajuda'); // baixa

    $memory = $rebuilder->rebuild(1, $extId);

    expect($memory->total_reclamacoes)->toBe(4); // 4 com match
    expect($memory->reclamacoes_recentes)->not->toBeNull();
    expect(count($memory->reclamacoes_recentes))->toBeGreaterThanOrEqual(4);

    // Verifica severities presentes
    $severities = collect($memory->reclamacoes_recentes)->pluck('severity')->all();
    expect($severities)->toContain('critica')->toContain('alta')->toContain('media');
});

it('US-WA-VOZ-002 — reclamações ignora msgs OUTBOUND (só cliente conta)', function () {
    $linker = new ConversationContactLinker();
    $rebuilder = new CustomerMemoryRebuilder($linker);

    $extId = '5548999872822';
    $convId = seedConvFixture(1, $extId);
    // Atendente outbound usa palavra "problema" — NÃO conta como reclamação cliente
    seedMsgFixture(1, $convId, 'outbound', userId: 10, body: 'qual é o problema?');

    $memory = $rebuilder->rebuild(1, $extId);

    expect($memory->total_reclamacoes)->toBe(0);
});

it('CustomerMemory entity helpers: n_msgs_total + daysSinceLastInteraction + isErasureRequested', function () {
    $linker = new ConversationContactLinker();
    $rebuilder = new CustomerMemoryRebuilder($linker);

    $extId = '5548999872822';
    $convId = seedConvFixture(1, $extId);
    seedMsgFixture(1, $convId, 'inbound', now()->subDays(5)->format('Y-m-d H:i:s'));
    seedMsgFixture(1, $convId, 'outbound', now()->subDays(5)->format('Y-m-d H:i:s'));

    $memory = $rebuilder->rebuild(1, $extId);

    expect($memory->n_msgs_total)->toBe(2);
    expect($memory->daysSinceLastInteraction())->toBeGreaterThanOrEqual(4)->toBeLessThanOrEqual(6);
    expect($memory->isErasureRequested())->toBeFalse();

    $memory->erasure_requested_at = now();
    expect($memory->isErasureRequested())->toBeTrue();
});
