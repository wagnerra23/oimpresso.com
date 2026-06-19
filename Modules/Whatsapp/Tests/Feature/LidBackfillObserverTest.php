<?php

declare(strict_types=1);

use App\Contact;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Entities\Conversation;
use Modules\Whatsapp\Entities\LidPhoneMap;
use Modules\Whatsapp\Jobs\BackfillLidConversationsJob;
use Modules\Whatsapp\Observers\LidPhoneMapObserver;

uses(Tests\TestCase::class);

/**
 * R-WA-LID-BACKFILL — wave-protocol-stack PR2 (sessão 2026-05-15).
 *
 * Cobre LidPhoneMapObserver + BackfillLidConversationsJob — loop fechado
 * "1ª msg @lid sem senderPn cria conv órfã" → "2ª msg COM senderPn descobre
 * phone" → observer dispara job → job re-linka conv órfã.
 *
 * 4 testes:
 *  T1. Observer dispara job em NULL→valor (Queue::fake assertPushed)
 *  T2. Observer NÃO dispara em bump last_seen_at sem mudar phone
 *  T3. Job re-linka conversations órfãs via ConversationContactLinker
 *  T4. Tier 0: job biz=1 não toca convs biz=99 com mesmo lid (cross-tenant)
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('era-sqlite: schema sintético manual incompatível com MySQL persistente — quarentena Onda 2 SDD floor; burn-down converte depois.');
    }

    foreach ([
        'conversations', 'channels', 'contacts', 'whatsapp_lid_pn_map',
        'activity_log', 'ref_count_details',
    ] as $t) {
        Schema::dropIfExists($t);
    }

    Schema::create('whatsapp_lid_pn_map', function ($table) {
        $table->id();
        $table->unsignedInteger('business_id');
        $table->string('lid', 100);
        $table->string('phone_e164', 32)->nullable();
        $table->enum('source', ['webhook_senderPn', 'manual', 'baileys_lookup'])
            ->default('webhook_senderPn');
        $table->timestamp('first_seen_at')->useCurrent();
        $table->timestamp('last_seen_at')->useCurrent();
        $table->timestamps();
        $table->unique(['business_id', 'lid'], 'wa_lid_pn_business_lid_uniq');
    });

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
        $table->boolean('handles_repair_status')->default(false);
        $table->boolean('handles_billing')->default(false);
        $table->boolean('handles_jana_bot')->default(true);
        $table->boolean('handles_outbound_default')->default(false);
        $table->boolean('bot_enabled')->default(false);
        $table->string('channel_health', 20)->default('never_checked');
        $table->unsignedInteger('channel_health_consecutive_failures')->default(0);
        $table->timestamps();
    });

    // Schema 'conversations' SEM coluna `lid` (testa o fallback pré-PR1
    // do Job — Schema::hasColumn detecta ausência e usa customer_external_id
    // LIKE '%<lid>@lid'). Quando PR1 mergear adicionando `lid`, este schema
    // pode receber a coluna e o caminho canônico passa a ser exercido.
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

    // Observer pode não estar registrado em test env (cada test boot reseta
    // o model). Registra explicitamente — idempotente.
    LidPhoneMap::observe(LidPhoneMapObserver::class);
});

/** Helper — cria Channel + Conversation órfã (contact_id=null) pra LID. */
function makeLidOrphanConv(int $bizId, string $lid, string $uuidSuffix): Conversation
{
    $channel = Channel::query()->create([
        'business_id' => $bizId,
        'channel_uuid' => 'aaaaaaaa-0000-0000-0000-' . str_pad($uuidSuffix, 12, '0', STR_PAD_LEFT),
        'label' => 'X',
        'type' => Channel::TYPE_WHATSAPP_BAILEYS,
        'status' => 'active',
    ]);

    return Conversation::query()->create([
        'business_id' => $bizId,
        'channel_id' => $channel->id,
        // Formato canônico LID legado — `<lid>@lid` no customer_external_id
        'customer_external_id' => $lid . '@lid',
        'contact_id' => null,
        'contact_name' => $lid . '@lid',
        'status' => 'open',
    ]);
}

it('T1 — Observer dispara BackfillLidConversationsJob quando phone_e164 muda NULL→valor', function () {
    Queue::fake();

    // 1ª insert — phone_e164 NULL (cache miss inicial)
    $map = LidPhoneMap::query()->create([
        'business_id' => 1,
        'lid' => '5196915463394@lid',
        'phone_e164' => null,
        'source' => LidPhoneMap::SOURCE_WEBHOOK_SENDER_PN,
        'first_seen_at' => now(),
        'last_seen_at' => now(),
    ]);

    // Observer ignora insert com phone NULL — não foi descoberto ainda
    Queue::assertNotPushed(BackfillLidConversationsJob::class);

    // 2ª save — descobre phone via senderPn (NULL→valor)
    $map->phone_e164 = '+554899872***';
    $map->save();

    Queue::assertPushed(BackfillLidConversationsJob::class, function ($job) {
        return $job->businessId === 1
            && $job->lid === '5196915463394@lid'
            && $job->phoneE164 === '+554899872***';
    });
});

it('T2 — Observer NÃO dispara em bump last_seen_at sem mudar phone_e164', function () {
    Queue::fake();

    // Cria com phone já preenchido (descoberta passada). Insert também
    // conta como "phone_e164 mudou de NULL→valor" — observer dispara aqui.
    $map = LidPhoneMap::query()->create([
        'business_id' => 1,
        'lid' => '5196915463394@lid',
        'phone_e164' => '+554899872***',
        'source' => LidPhoneMap::SOURCE_WEBHOOK_SENDER_PN,
        'first_seen_at' => now()->subDay(),
        'last_seen_at' => now()->subDay(),
    ]);

    // Sanity — insert disparou 1x (NULL→valor na criação)
    Queue::assertPushed(BackfillLidConversationsJob::class, 1);

    // Webhook re-vê o mesmo LID — bump last_seen_at, phone_e164 inalterado.
    $map->last_seen_at = now();
    $map->save();

    // Observer ignorou o save — count continua 1 (não subiu pra 2)
    Queue::assertPushed(BackfillLidConversationsJob::class, 1);
});

it('T3 — Job re-linka conversations órfãs via ConversationContactLinker', function () {
    // Contact CRM já existe com o phone que vai ser descoberto
    $contact = Contact::create([
        'business_id' => 1,
        'name' => 'Wagner Rocha',
        'mobile' => '+5548999872***',
        'type' => 'customer',
        'contact_id' => 'CO0001',
    ]);

    // Conv órfã foi criada na 1ª msg @lid (sem senderPn)
    $orphan = makeLidOrphanConv(1, '5196915463394@lid', '301');
    expect($orphan->contact_id)->toBeNull();

    // Job roda manualmente — simula execução pós-dispatch do observer.
    // Construtor recebe (biz, lid, phoneE164) já descoberto.
    $job = new BackfillLidConversationsJob(
        businessId: 1,
        lid: '5196915463394@lid',
        phoneE164: '+5548999872***',
    );
    $job->handle(app(\Modules\Whatsapp\Services\Contacts\ConversationContactLinker::class));

    // Conv órfã deve ter contact_id preenchido agora
    $orphan->refresh();
    expect($orphan->contact_id)->toBe($contact->id);
});

it('T4 — Tier 0: job biz=1 NÃO toca conversations biz=99 com mesmo lid (cross-tenant)', function () {
    // Mesmo phone em 2 businesses — Contacts e convs separados
    Contact::create([
        'business_id' => 1,
        'name' => 'Wagner Biz1',
        'mobile' => '+5548999872***',
        'type' => 'customer',
        'contact_id' => 'CO0001',
    ]);
    Contact::create([
        'business_id' => 99,
        'name' => 'Alien Biz99',
        'mobile' => '+5548999872***',
        'type' => 'customer',
        'contact_id' => 'CO9999',
    ]);

    // Conv órfã biz=1 (alvo do job)
    $convBiz1 = makeLidOrphanConv(1, '5196915463394@lid', '401');
    // Conv órfã biz=99 (NÃO deve ser tocada)
    $convBiz99 = makeLidOrphanConv(99, '5196915463394@lid', '402');

    expect($convBiz1->contact_id)->toBeNull();
    expect($convBiz99->contact_id)->toBeNull();

    // Job rodando SÓ pra biz=1
    $job = new BackfillLidConversationsJob(
        businessId: 1,
        lid: '5196915463394@lid',
        phoneE164: '+5548999872***',
    );
    $job->handle(app(\Modules\Whatsapp\Services\Contacts\ConversationContactLinker::class));

    // Re-fetch sem global scope (test sem session business) — verifica
    // que conv biz=99 ficou intacta
    $convBiz1->refresh();
    $convBiz99Fresh = Conversation::query()
        ->withoutGlobalScope(ScopeByBusiness::class)
        ->find($convBiz99->id);

    expect($convBiz1->contact_id)->not->toBeNull(); // linkada
    expect($convBiz99Fresh->contact_id)->toBeNull(); // intocada — Tier 0 OK
});
