<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Entities\Conversation;
use Modules\Whatsapp\Jobs\PersistContactsFromHistorySyncJob;

uses(Tests\TestCase::class);

/**
 * R-WA-HSC-001/002 — Persist contacts do payload `messaging-history.set`
 * Baileys hidrata `Conversation.contact_name` quando vazio/E.164 fallback.
 *
 * Wagner reportou 2026-05-15 09:25: "sincronia dos contatos não trouxe contatos"
 * pós re-pareamento Baileys 7.x ROTA LIVRE biz=1.
 *
 * Root cause: daemon Node ENVIA `contacts` no payload (Instance.ts:243-288,
 * chunk_index=0) mas backend PHP ignorava — só processava `messages`.
 *
 * Fix coberto aqui:
 *   1. Happy-path: contact com `id=PHONE@s.whatsapp.net` + `name=Maria` →
 *      Conversation com customer_external_id matching ganha contact_name='Maria'.
 *   2. Cross-tenant: contacts do biz=99 NÃO afetam Conversation do biz=1 — convs
 *      biz=1 com phone idêntico mantêm contact_name original (Tier 0 ADR 0093).
 *      Tests usam biz=1 vs biz=99 (NUNCA biz=4 ROTA LIVRE per ADR 0101).
 *
 * @see Modules/Whatsapp/Jobs/PersistContactsFromHistorySyncJob.php
 * @see Modules/Whatsapp/Http/Controllers/Api/ChannelBaileysWebhookController.php
 * @see memory/sessions/2026-05-15-agent-c-contact-sync-history-sync.md
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('era-sqlite: schema sintético manual incompatível com MySQL persistente — quarentena Onda 2 SDD floor; burn-down converte depois.');
    }

    foreach (['conversations', 'channels', 'lid_phone_maps'] as $t) {
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
        $table->string('last_message_preview', 200)->nullable();
        $table->string('last_message_direction', 10)->nullable();
        $table->string('lid', 100)->nullable();
        $table->string('phone_e164', 20)->nullable();
        $table->string('bsuid', 100)->nullable();
        $table->timestamps();
    });

    // LidPhoneResolver usa essa tabela — Job instancia o resolver, então
    // mesmo que o test não use LID, o cache `Cache::remember` pode tocar a
    // tabela. Schema minimal.
    Schema::create('lid_phone_maps', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->string('lid', 100);
        $table->string('phone_e164', 20)->nullable();
        $table->string('source', 30);
        $table->timestamp('first_seen_at')->nullable();
        $table->timestamp('last_seen_at')->nullable();
        $table->timestamps();
        $table->unique(['business_id', 'lid']);
    });
});

function makeContactsTestChannel(int $businessId = 1, string $label = 'Test'): Channel
{
    return Channel::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => $businessId,
        'channel_uuid' => 'contacts-test-' . uniqid(),
        'label' => $label,
        'type' => Channel::TYPE_WHATSAPP_BAILEYS,
        'status' => 'active',
        'display_identifier' => '5511999998888',
    ]);
}

it('R-WA-HSC-001 — happy-path: contacts payload hidrata Conversation.contact_name', function () {
    $channel = makeContactsTestChannel(1);

    // Conv pre-existente sem nome (só E.164 cru — fallback quando push_name
    // não chegou ainda na 1ª msg recebida). Cenário canônico pós re-pareamento.
    $conv = Conversation::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'channel_id' => $channel->id,
        'customer_external_id' => '+5511999998888',
        'contact_name' => '+5511999998888', // fallback E.164 do MessagePersister:232
        'status' => 'open',
    ]);

    // Conv com nome curado pelo atendente — NÃO deve ser sobrescrita.
    $convCurada = Conversation::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'channel_id' => $channel->id,
        'customer_external_id' => '+5511777776666',
        'contact_name' => 'João VIP (atendente)',
        'status' => 'open',
    ]);

    // Payload shape igual ao daemon Instance.ts envia
    $contacts = [
        [
            'id' => '5511999998888@s.whatsapp.net',
            'name' => 'Maria da Silva',
            'notify' => 'Maria',
            'verifiedName' => null,
        ],
        [
            // Nome diferente pra conv curada — deve ser ignorado (preserva nome)
            'id' => '5511777776666@s.whatsapp.net',
            'name' => 'João Comum',
            'notify' => 'João',
            'verifiedName' => null,
        ],
        [
            // Contato sem nome em nenhum campo — skip silencioso
            'id' => '5511555554444@s.whatsapp.net',
            'name' => null,
            'notify' => '',
            'verifiedName' => null,
        ],
        [
            // Contato com verifiedName prioritário (Business Profile oficial)
            'id' => '5511333332222@s.whatsapp.net',
            'name' => 'Loja Marca',
            'notify' => 'Marca',
            'verifiedName' => 'Loja Oficial Marca Ltda',
        ],
    ];

    // Conv pra testar prioridade verifiedName
    $convVerified = Conversation::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'channel_id' => $channel->id,
        'customer_external_id' => '+5511333332222',
        'contact_name' => null, // vazio
        'status' => 'open',
    ]);

    // Job é dispatchado SYNC via handle() direto (sem queue)
    $job = new PersistContactsFromHistorySyncJob(
        businessId: 1,
        channelId: $channel->id,
        syncType: 2,
        contacts: $contacts,
    );
    $job->handle();

    // Conv fallback E.164 → ganhou nome Maria
    $conv->refresh();
    expect($conv->contact_name)->toBe('Maria da Silva');

    // Conv curada → preservada (não sobrescrita por "João Comum")
    $convCurada->refresh();
    expect($convCurada->contact_name)->toBe('João VIP (atendente)');

    // Conv vazia + verifiedName → ganhou nome verificado (prioridade > name > notify)
    $convVerified->refresh();
    expect($convVerified->contact_name)->toBe('Loja Oficial Marca Ltda');
});

it('R-WA-HSC-002 — Tier 0 cross-tenant: contacts biz=99 NÃO afetam biz=1', function () {
    $channelBiz1 = makeContactsTestChannel(1, 'Biz1');
    $channelBiz99 = makeContactsTestChannel(99, 'Biz99');

    // Conv biz=1 com phone idêntico ao que vai vir no payload biz=99.
    // Após executar Job pra biz=99, esta conv DEVE manter contact_name original.
    $convBiz1 = Conversation::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'channel_id' => $channelBiz1->id,
        'customer_external_id' => '+5511999998888',
        'contact_name' => '+5511999998888', // fallback — alvo potencial de update
        'status' => 'open',
    ]);

    // Conv biz=99 com mesmo phone — esta SIM deve ser atualizada pelo Job biz=99
    $convBiz99 = Conversation::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 99,
        'channel_id' => $channelBiz99->id,
        'customer_external_id' => '+5511999998888',
        'contact_name' => null,
        'status' => 'open',
    ]);

    // Job pro biz=99 — contacts trazem nome
    $jobBiz99 = new PersistContactsFromHistorySyncJob(
        businessId: 99,
        channelId: $channelBiz99->id,
        syncType: 2,
        contacts: [
            [
                'id' => '5511999998888@s.whatsapp.net',
                'name' => 'Cliente Tenant 99',
                'notify' => null,
                'verifiedName' => null,
            ],
        ],
    );
    $jobBiz99->handle();

    // Conv biz=1 → INTOCADA (Tier 0 isolation)
    $convBiz1->refresh();
    expect($convBiz1->contact_name)
        ->toBe('+5511999998888')
        ->not->toBe('Cliente Tenant 99');

    // Conv biz=99 → atualizada (mesmo phone, mas business_id diferente)
    $convBiz99->refresh();
    expect($convBiz99->contact_name)->toBe('Cliente Tenant 99');
});
