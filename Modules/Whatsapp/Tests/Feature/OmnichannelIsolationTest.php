<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Entities\Conversation;
use Modules\Whatsapp\Entities\Message;
use Modules\Whatsapp\Services\Drivers\ChannelDriverFactory;
use Modules\Whatsapp\Services\Drivers\MetaCloudDriver;
use Modules\Whatsapp\Services\Drivers\NotImplementedDriverException;
use Modules\Whatsapp\Services\Drivers\ZapiDriver;

uses(Tests\TestCase::class);

/**
 * R-OMNI-001 · Multi-tenant Tier 0 omnichannel (ADR 0093 + ADR 0135).
 *
 * Cross-tenant isolation biz=1 vs biz=99 (ADR 0101 — biz=99 é o sentinel
 * de cross-tenant em tests, biz=1 é o "self" default).
 *
 * Cobre:
 *   - Channel + Conversation + Message respeitam BusinessIdScope global
 *   - Channel::config_json é cifrado em DB (encrypted:array cast)
 *   - ChannelDriverFactory mapeia 3 types Whatsapp pros drivers existentes
 *   - Channel types Fase 1-3 (Insta/Messenger/Email/ML) lançam NotImplementedDriverException
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('era-sqlite: schema sintético manual incompatível com MySQL persistente — quarentena Onda 2 SDD floor; burn-down converte depois.');
    }

    foreach (['messages', 'conversations', 'channels'] as $t) {
        Schema::dropIfExists($t);
    }

    // Espelha migration `2026_05_11_000001_create_omnichannel_tables.php`
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
});

function omniSetBiz(int $businessId): void
{
    // ScopeByBusiness exige auth()->check() — pattern existente falha em
    // MultiTenantIsolationTest.php (5 falhos em main, ADR followup pendente).
    // Aqui usamos User stub não-persistido pra forçar auth check + session.
    $user = new class extends \Illuminate\Foundation\Auth\User {
        protected $table = 'users';
        protected $guarded = [];
        public function can($abilities, $arguments = []): bool { return false; }
    };
    $user->id = 1;
    $user->business_id = $businessId;
    auth()->setUser($user);

    session()->put('user.business_id', $businessId);
    app()->forgetInstance(ScopeByBusiness::class);
}

it('R-OMNI-001 — Channel respeita BusinessIdScope global (biz=1 não vê biz=99)', function () {
    Channel::query()->create([
        'business_id' => 1,
        'label' => 'Vendas',
        'type' => Channel::TYPE_WHATSAPP_ZAPI,
        'status' => 'active',
    ]);
    Channel::query()->create([
        'business_id' => 99,
        'label' => 'Cross-tenant',
        'type' => Channel::TYPE_WHATSAPP_BAILEYS,
        'status' => 'active',
    ]);

    omniSetBiz(1);

    $channels = Channel::query()->get();
    expect($channels)->toHaveCount(1);
    expect($channels->first()->label)->toBe('Vendas');
    expect($channels->first()->business_id)->toBe(1);
});

it('R-OMNI-002 — Conversation respeita BusinessIdScope (biz=1 não vê biz=99)', function () {
    $ch1 = Channel::query()->create([
        'business_id' => 1, 'label' => 'Vendas',
        'type' => Channel::TYPE_WHATSAPP_ZAPI, 'status' => 'active',
    ]);
    $ch99 = Channel::query()->create([
        'business_id' => 99, 'label' => 'Cross',
        'type' => Channel::TYPE_WHATSAPP_BAILEYS, 'status' => 'active',
    ]);

    Conversation::query()->create([
        'business_id' => 1, 'channel_id' => $ch1->id,
        'customer_external_id' => '+5511988880001', 'status' => 'open',
    ]);
    Conversation::query()->create([
        'business_id' => 99, 'channel_id' => $ch99->id,
        'customer_external_id' => '+5511988880099', 'status' => 'open',
    ]);

    omniSetBiz(1);

    $convs = Conversation::query()->get();
    expect($convs)->toHaveCount(1);
    expect($convs->first()->customer_external_id)->toBe('+5511988880001');
});

it('R-OMNI-003 — Message respeita BusinessIdScope (biz=1 não vê biz=99)', function () {
    $ch1 = Channel::query()->create([
        'business_id' => 1, 'label' => 'V', 'type' => Channel::TYPE_WHATSAPP_ZAPI, 'status' => 'active',
    ]);
    $conv1 = Conversation::query()->create([
        'business_id' => 1, 'channel_id' => $ch1->id,
        'customer_external_id' => '+5511988880001', 'status' => 'open',
    ]);

    $ch99 = Channel::query()->create([
        'business_id' => 99, 'label' => 'X', 'type' => Channel::TYPE_WHATSAPP_BAILEYS, 'status' => 'active',
    ]);
    $conv99 = Conversation::query()->create([
        'business_id' => 99, 'channel_id' => $ch99->id,
        'customer_external_id' => '+5511988880099', 'status' => 'open',
    ]);

    Message::query()->create([
        'business_id' => 1, 'conversation_id' => $conv1->id,
        'direction' => 'inbound', 'provider' => 'whatsapp_zapi',
        'body' => 'oi self', 'status' => 'received',
    ]);
    Message::query()->create([
        'business_id' => 99, 'conversation_id' => $conv99->id,
        'direction' => 'inbound', 'provider' => 'whatsapp_baileys',
        'body' => 'NÃO DEVE VAZAR', 'status' => 'received',
    ]);

    omniSetBiz(1);

    $msgs = Message::query()->get();
    expect($msgs)->toHaveCount(1);
    expect($msgs->first()->body)->toBe('oi self');
});

it('R-OMNI-004 — Channel.config_json é cifrado em DB (encrypted:array cast)', function () {
    omniSetBiz(1);

    Channel::query()->create([
        'business_id' => 1,
        'label' => 'Z',
        'type' => Channel::TYPE_WHATSAPP_ZAPI,
        'status' => 'active',
        'config_json' => [
            'zapi_instance_id' => 'INSTANCE_PUBLIC',
            'zapi_instance_token' => 'SECRET_TOKEN_NAO_DEVE_APARECER_EM_DB',
        ],
    ]);

    // Lê via Eloquent (decifrado pelo cast)
    $channel = Channel::query()->first();
    expect($channel->config_json)
        ->toBeArray()
        ->and($channel->config_json['zapi_instance_token'])
        ->toBe('SECRET_TOKEN_NAO_DEVE_APARECER_EM_DB');

    // Lê via query SQL bruta (sem decifragem) — token NÃO deve aparecer plain
    $raw = \DB::table('channels')->where('id', $channel->id)->value('config_json');
    expect($raw)->not->toContain('SECRET_TOKEN_NAO_DEVE_APARECER_EM_DB');
});

it('R-OMNI-005 — ChannelDriverFactory mapeia 3 types Whatsapp pros drivers existentes', function () {
    omniSetBiz(1);

    $meta = Channel::query()->create([
        'business_id' => 1, 'label' => 'Meta', 'type' => Channel::TYPE_WHATSAPP_META, 'status' => 'active',
    ]);
    $zapi = Channel::query()->create([
        'business_id' => 1, 'label' => 'Zapi', 'type' => Channel::TYPE_WHATSAPP_ZAPI, 'status' => 'active',
    ]);
    $baileys = Channel::query()->create([
        'business_id' => 1, 'label' => 'Baileys legacy', 'type' => Channel::TYPE_WHATSAPP_BAILEYS, 'status' => 'active',
    ]);

    expect(ChannelDriverFactory::resolve($meta))->toBeInstanceOf(MetaCloudDriver::class);
    expect(ChannelDriverFactory::resolve($zapi))->toBeInstanceOf(ZapiDriver::class);
    // ADR 0202 — TYPE_WHATSAPP_BAILEYS resolve lança NotImplementedDriverException
    expect(fn () => ChannelDriverFactory::resolve($baileys))->toThrow(NotImplementedDriverException::class);
});

it('R-OMNI-006 — ChannelDriverFactory lança NotImplementedDriverException pra Fases 1-3', function () {
    omniSetBiz(1);

    foreach ([
        Channel::TYPE_INSTAGRAM,
        Channel::TYPE_MESSENGER,
        Channel::TYPE_EMAIL_IMAP,
        Channel::TYPE_EMAIL_SMTP,
        Channel::TYPE_MERCADOLIVRE,
    ] as $type) {
        $ch = Channel::query()->create([
            'business_id' => 1, 'label' => $type, 'type' => $type, 'status' => 'setup',
        ]);
        expect(fn () => ChannelDriverFactory::resolve($ch))
            ->toThrow(NotImplementedDriverException::class);
    }
});
