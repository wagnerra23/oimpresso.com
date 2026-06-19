<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Entities\Conversation;
use Modules\Whatsapp\Entities\SlaPolicy;
use Modules\Whatsapp\Services\Centrifugo\CentrifugoPublisher;
use Modules\Whatsapp\Services\Sla\SlaEnforcer;

uses(Tests\TestCase::class);

/**
 * R-WA-SLA — testes do scan SLA (CYCLE-07 PR-2 / Gap P0 #2).
 *
 * Cobre:
 *  001. Policy 60min, conv last_inbound 70min atrás sem reply → alerta dispara
 *  002. Conv já alertada nos últimos minutos → idempotência (lock segura)
 *  003. Cross-tenant biz=99 não vaza (Tier 0 IRREVOGÁVEL — ADR 0093)
 *  004. --dry-run não persiste lock nem publica Centrifugo
 *  005. triggers_on=open_aging + status=resolved → não dispara
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('era-sqlite: schema sintético manual incompatível com MySQL persistente — quarentena Onda 2 SDD floor; burn-down converte depois.');
    }

    Cache::flush();

    foreach (['sla_policies', 'whatsapp_conversation_tags', 'whatsapp_tags', 'conversations', 'channels'] as $t) {
        Schema::dropIfExists($t);
    }

    Schema::create('whatsapp_tags', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->string('slug', 40);
        $table->string('label', 80);
        $table->string('color', 20)->default('slate');
        $table->unsignedInteger('sort_order')->default(0);
        $table->timestamps();
    });

    Schema::create('whatsapp_conversation_tags', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedBigInteger('conversation_id');
        $table->unsignedBigInteger('tag_id');
        $table->timestamp('created_at')->useCurrent();
        $table->timestamp('updated_at')->nullable();
        $table->unsignedInteger('created_by_user_id')->nullable();
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

    Schema::create('sla_policies', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->string('label', 80);
        $table->unsignedInteger('threshold_minutes');
        $table->string('triggers_on', 30);
        $table->unsignedBigInteger('channel_id')->nullable();
        $table->unsignedBigInteger('tag_id')->nullable();
        $table->string('action_kind', 30);
        $table->text('action_params')->nullable();
        $table->boolean('active')->default(true);
        $table->timestamps();
    });

    // Bypass scope ScopeByBusiness pra setup determinístico cross-business.
    app()->forgetInstance(ScopeByBusiness::class);
});

// ---- Factories helper -------------------------------------------------------

function slaMakeChannel(int $businessId, ?string $uuid = null): Channel
{
    return Channel::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => $businessId,
        'channel_uuid' => $uuid ?? bin2hex(random_bytes(8)),
        'label' => 'Suporte',
        'type' => Channel::TYPE_WHATSAPP_BAILEYS,
        'status' => 'active',
    ]);
}

function slaMakeConv(int $businessId, int $channelId, array $attrs = []): Conversation
{
    return Conversation::withoutGlobalScope(ScopeByBusiness::class)->create(array_merge([
        'business_id' => $businessId,
        'channel_id' => $channelId,
        'customer_external_id' => '+5511' . str_pad((string) random_int(1, 99999999), 8, '0', STR_PAD_LEFT),
        'contact_name' => 'Cliente Teste',
        'status' => Conversation::STATUS_OPEN,
        'last_message_at' => now(),
    ], $attrs));
}

function slaMakePolicy(int $businessId, array $attrs = []): SlaPolicy
{
    return SlaPolicy::withoutGlobalScope(ScopeByBusiness::class)->create(array_merge([
        'business_id' => $businessId,
        'label' => 'First response 60min',
        'threshold_minutes' => 60,
        'triggers_on' => SlaPolicy::TRIGGER_FIRST_INBOUND_NO_REPLY,
        'channel_id' => null,
        'tag_id' => null,
        'action_kind' => SlaPolicy::ACTION_CENTRIFUGO_NOTIFY,
        'action_params' => null,
        'active' => true,
    ], $attrs));
}

/**
 * Mock CentrifugoPublisher capturando publishes.
 *
 * @return array{publisher:CentrifugoPublisher,calls:\ArrayObject<int,array<string,mixed>>}
 */
function slaMockPublisher(): array
{
    $calls = new \ArrayObject();

    $publisher = new class($calls) extends CentrifugoPublisher {
        public function __construct(public \ArrayObject $calls)
        {
        }

        public function publish(string $channel, array $data): bool
        {
            $this->calls[] = ['channel' => $channel, 'data' => $data];
            return true;
        }

        public function isEnabled(): bool
        {
            return true;
        }
    };

    return ['publisher' => $publisher, 'calls' => $calls];
}

// ---- Tests ------------------------------------------------------------------

it('R-WA-SLA-001 — policy 60min com conv last_inbound 70min atrás dispara alerta', function () {
    $ch = slaMakeChannel(1);
    $conv = slaMakeConv(1, $ch->id, [
        'status' => Conversation::STATUS_OPEN,
        'last_inbound_at' => now()->subMinutes(70),
        'last_outbound_at' => null,
        'last_message_at' => now()->subMinutes(70),
    ]);
    $policy = slaMakePolicy(1);

    $mock = slaMockPublisher();
    $enforcer = new SlaEnforcer($mock['publisher']);

    $result = $enforcer->scanAndAlert(businessId: 1, dryRun: false);

    expect($result['policies_scanned'])->toBe(1)
        ->and($result['alerts_fired'])->toBe(1)
        ->and($mock['calls']->count())->toBe(1);

    $call = $mock['calls'][0];
    expect($call['channel'])->toBe('omnichannel:business:1:sla_alerts')
        ->and($call['data']['type'])->toBe('sla_alert')
        ->and($call['data']['conversation_id'])->toBe($conv->id)
        ->and($call['data']['policy_id'])->toBe($policy->id);
});

it('R-WA-SLA-002 — re-scan dentro da janela de lock NÃO duplica alerta', function () {
    $ch = slaMakeChannel(1);
    slaMakeConv(1, $ch->id, [
        'last_inbound_at' => now()->subMinutes(70),
        'last_outbound_at' => null,
    ]);
    slaMakePolicy(1);

    $mock = slaMockPublisher();
    $enforcer = new SlaEnforcer($mock['publisher']);

    // 1ª varredura — dispara
    $r1 = $enforcer->scanAndAlert(businessId: 1, dryRun: false);
    expect($r1['alerts_fired'])->toBe(1);

    // 2ª varredura imediata — lock segura, conv já alertada nos últimos N min
    $r2 = $enforcer->scanAndAlert(businessId: 1, dryRun: false);
    expect($r2['alerts_fired'])->toBe(0)
        ->and($r2['locked_skipped'])->toBe(1)
        ->and($mock['calls']->count())->toBe(1); // só 1 publish total
});

it('R-WA-SLA-003 — cross-tenant biz=99 não vaza pra biz=1', function () {
    // biz=1: tem policy ativa, mas só uma conv em-dia
    $ch1 = slaMakeChannel(1);
    slaMakeConv(1, $ch1->id, [
        'last_inbound_at' => now()->subMinutes(10), // só 10min — abaixo do threshold 60
    ]);
    slaMakePolicy(1);

    // biz=99: tem conv MUITO atrasada + policy própria
    $ch99 = slaMakeChannel(99);
    $conv99 = slaMakeConv(99, $ch99->id, [
        'last_inbound_at' => now()->subMinutes(200),
        'last_outbound_at' => null,
    ]);
    slaMakePolicy(99, ['label' => 'Biz99 60min']);

    $mock = slaMockPublisher();
    $enforcer = new SlaEnforcer($mock['publisher']);

    // Scan APENAS biz=1: não pode tocar em conv biz=99 nem disparar alerta lá
    $result = $enforcer->scanAndAlert(businessId: 1, dryRun: false);

    expect($result['policies_scanned'])->toBe(1)
        ->and($result['alerts_fired'])->toBe(0)
        ->and($mock['calls']->count())->toBe(0);

    // Sanity: scan biz=99 isolado vê o atraso só lá
    $r99 = $enforcer->scanAndAlert(businessId: 99, dryRun: false);
    expect($r99['alerts_fired'])->toBe(1);
    $call99 = $mock['calls'][0];
    expect($call99['channel'])->toBe('omnichannel:business:99:sla_alerts')
        ->and($call99['data']['conversation_id'])->toBe($conv99->id);
});

it('R-WA-SLA-004 — --dry-run não persiste lock nem publica Centrifugo', function () {
    $ch = slaMakeChannel(1);
    slaMakeConv(1, $ch->id, [
        'last_inbound_at' => now()->subMinutes(70),
        'last_outbound_at' => null,
    ]);
    slaMakePolicy(1);

    $mock = slaMockPublisher();
    $enforcer = new SlaEnforcer($mock['publisher']);

    $result = $enforcer->scanAndAlert(businessId: 1, dryRun: true);

    expect($result['alerts_fired'])->toBe(1)        // contabiliza intent
        ->and($mock['calls']->count())->toBe(0);    // mas NÃO publica

    // 2ª varredura ainda em dry-run — sem lock persistido, conta de novo
    $r2 = $enforcer->scanAndAlert(businessId: 1, dryRun: true);
    expect($r2['alerts_fired'])->toBe(1)
        ->and($r2['locked_skipped'])->toBe(0);      // lock nunca foi tomado
});

it('R-WA-SLA-005 — triggers_on=open_aging + status=resolved NÃO dispara', function () {
    $ch = slaMakeChannel(1);
    // Conv resolved há muito tempo — não deveria virar alerta
    slaMakeConv(1, $ch->id, [
        'status' => Conversation::STATUS_RESOLVED,
        'last_message_at' => now()->subMinutes(200),
    ]);
    // Conv open há muito tempo — DEVE virar alerta
    $convOpen = slaMakeConv(1, $ch->id, [
        'status' => Conversation::STATUS_OPEN,
        'last_message_at' => now()->subMinutes(200),
    ]);

    slaMakePolicy(1, [
        'label' => 'Open aging 60min',
        'triggers_on' => SlaPolicy::TRIGGER_OPEN_AGING,
    ]);

    $mock = slaMockPublisher();
    $enforcer = new SlaEnforcer($mock['publisher']);

    $result = $enforcer->scanAndAlert(businessId: 1, dryRun: false);

    expect($result['alerts_fired'])->toBe(1)
        ->and($mock['calls']->count())->toBe(1)
        ->and($mock['calls'][0]['data']['conversation_id'])->toBe($convOpen->id);
});
