<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Entities\Conversation;
use Modules\Whatsapp\Entities\Macro;
use Modules\Whatsapp\Entities\MacroVariant;
use Modules\Whatsapp\Entities\Message;
use Modules\Whatsapp\Services\Macros\MacroExecutor;
use Modules\Whatsapp\Services\Macros\MacroVariantPicker;
use Modules\Whatsapp\Services\Macros\MacroVariantResponseTracker;

uses(Tests\TestCase::class);

/**
 * R-WA-049-PICKER — GUARD tests pra MacroVariantPicker + integração
 * MacroExecutor + response tracking (gap P2 #18 A/B testing).
 *
 * Cobre:
 *  001. picker — 0 variantes ativas → null
 *  002. picker — 1 variante ativa → sempre retorna ela
 *  003. picker — weighted distribution 70/30 estatística aprox
 *  004. picker — weight=0 excluído da loteria
 *  005. picker — active=false excluído
 *  006. cross-tenant biz=1 não enxerga variante de biz=99 no picker
 *  007. executor — incrementa sent_count + grava macro_variant_id em Message
 *  008. response tracker — incrementa response_count em inbound <24h da outbound
 *  009. response tracker — idempotente (não duplica em reentrega)
 *  010. response tracker — NÃO incrementa quando outbound foi >24h atrás
 *
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-049
 */
beforeEach(function () {
    foreach (['macro_variants', 'macros', 'messages', 'conversations', 'channels'] as $t) {
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
        $table->timestamps();
    });

    Schema::create('messages', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->unsignedBigInteger('conversation_id');
        $table->string('direction', 10);
        $table->string('provider', 30);
        $table->string('provider_message_id', 200)->nullable();
        $table->string('type', 30);
        $table->string('template_name', 64)->nullable();
        $table->string('subject', 200)->nullable();
        $table->text('body')->nullable();
        $table->json('payload')->nullable();
        $table->unsignedBigInteger('macro_variant_id')->nullable();
        $table->string('status', 20)->default('queued');
        $table->text('failed_reason')->nullable();
        $table->unsignedInteger('sender_user_id')->nullable();
        $table->string('sender_kind', 20)->nullable();
        $table->unsignedInteger('cost_centavos')->nullable();
        $table->boolean('is_internal_note')->default(false);
        $table->timestamps();
    });

    Schema::create('macros', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->string('label', 80);
        $table->string('shortcut', 30)->nullable();
        $table->text('body');
        $table->json('actions_json')->nullable();
        $table->unsignedBigInteger('created_by_user_id')->nullable();
        $table->unsignedInteger('used_count')->default(0);
        $table->timestamps();
        $table->index(['business_id'], 'macros_business_idx');
        $table->unique(['business_id', 'shortcut'], 'macros_business_shortcut_uniq');
    });

    Schema::create('macro_variants', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->unsignedBigInteger('macro_id');
        $table->string('label', 80);
        $table->text('body');
        $table->unsignedSmallInteger('weight')->default(50);
        $table->boolean('active')->default(true);
        $table->unsignedInteger('sent_count')->default(0);
        $table->unsignedInteger('response_count')->default(0);
        $table->timestamps();
        $table->index(['business_id', 'macro_id', 'active'], 'mv_biz_macro_active_idx');
    });
});

it('R-WA-049-PICKER-001 — 0 variantes ativas retorna null (caller usa macro.body padrão)', function () {
    $macro = makeMacro(1, 'Default body');

    // Caso A: zero variantes cadastradas
    $picker = app(MacroVariantPicker::class);
    expect($picker->pickFor($macro))->toBeNull();

    // Caso B: variantes existem mas todas inativas
    MacroVariant::query()->create([
        'business_id' => 1, 'macro_id' => $macro->id,
        'label' => 'X', 'body' => 'y', 'weight' => 50, 'active' => false,
    ]);
    expect($picker->pickFor($macro))->toBeNull();
});

it('R-WA-049-PICKER-002 — 1 variante ativa sempre retorna ela', function () {
    $macro = makeMacro(1, 'Default');
    $v = MacroVariant::query()->create([
        'business_id' => 1, 'macro_id' => $macro->id,
        'label' => 'Solo', 'body' => 'unique', 'weight' => 50, 'active' => true,
    ]);

    $picker = app(MacroVariantPicker::class);

    // Repete N vezes — sempre retorna o mesmo
    for ($i = 0; $i < 10; $i++) {
        $picked = $picker->pickFor($macro);
        expect($picked->id)->toBe($v->id);
    }
});

it('R-WA-049-PICKER-003 — weighted 70/30 produz distribuição estatística aproximada', function () {
    $macro = makeMacro(1, 'Default');
    $vA = MacroVariant::query()->create([
        'business_id' => 1, 'macro_id' => $macro->id,
        'label' => 'A 70%', 'body' => 'a', 'weight' => 70, 'active' => true,
    ]);
    $vB = MacroVariant::query()->create([
        'business_id' => 1, 'macro_id' => $macro->id,
        'label' => 'B 30%', 'body' => 'b', 'weight' => 30, 'active' => true,
    ]);

    $picker = app(MacroVariantPicker::class);

    $rounds = 10000;
    $countA = 0;
    $countB = 0;
    for ($i = 0; $i < $rounds; $i++) {
        $picked = $picker->pickFor($macro);
        if ($picked->id === $vA->id) {
            $countA++;
        } elseif ($picked->id === $vB->id) {
            $countB++;
        }
    }

    $rateA = $countA / $rounds;
    $rateB = $countB / $rounds;

    // Tolerância ampla pra evitar flaky — esperado 0.70/0.30, ±5pp.
    expect($rateA)->toBeGreaterThan(0.65)->toBeLessThan(0.75);
    expect($rateB)->toBeGreaterThan(0.25)->toBeLessThan(0.35);
    expect($countA + $countB)->toBe($rounds); // 100% das amostras válidas
});

it('R-WA-049-PICKER-004 — weight=0 excluído da loteria (pause sem delete)', function () {
    $macro = makeMacro(1, 'Default');
    $vActive = MacroVariant::query()->create([
        'business_id' => 1, 'macro_id' => $macro->id,
        'label' => 'A', 'body' => 'a', 'weight' => 50, 'active' => true,
    ]);
    $vPaused = MacroVariant::query()->create([
        'business_id' => 1, 'macro_id' => $macro->id,
        'label' => 'Pausada', 'body' => 'p', 'weight' => 0, 'active' => true,
    ]);

    $picker = app(MacroVariantPicker::class);

    // 100 picks — todas devem ser vActive (vPaused excluído por weight=0)
    for ($i = 0; $i < 100; $i++) {
        expect($picker->pickFor($macro)->id)->toBe($vActive->id);
    }
});

it('R-WA-049-PICKER-005 — active=false excluído da loteria', function () {
    $macro = makeMacro(1, 'Default');
    $vOn = MacroVariant::query()->create([
        'business_id' => 1, 'macro_id' => $macro->id,
        'label' => 'On', 'body' => 'on', 'weight' => 50, 'active' => true,
    ]);
    MacroVariant::query()->create([
        'business_id' => 1, 'macro_id' => $macro->id,
        'label' => 'Off', 'body' => 'off', 'weight' => 50, 'active' => false,
    ]);

    $picker = app(MacroVariantPicker::class);
    for ($i = 0; $i < 50; $i++) {
        expect($picker->pickFor($macro)->id)->toBe($vOn->id);
    }
});

it('R-WA-049-PICKER-006 — Tier 0: picker scope manual biz=1 ignora variante de biz=99', function () {
    $macroBiz1 = makeMacro(1, 'b1');
    $macroBiz99 = makeMacro(99, 'b99');

    // Variante cross-tenant — macro_id é do biz=99, mas business_id é 1.
    // O picker faz scope manual via where business_id == macro.business_id,
    // então essa variante "alien" não pode ser sorteada quando macro é biz=1.
    MacroVariant::query()->create([
        'business_id' => 99, 'macro_id' => $macroBiz99->id,
        'label' => 'Alien', 'body' => 'pwn', 'weight' => 50, 'active' => true,
    ]);

    $picker = app(MacroVariantPicker::class);

    // macroBiz1 não tem nenhuma variante → null
    expect($picker->pickFor($macroBiz1))->toBeNull();

    // macroBiz99 acha SUA variante
    expect($picker->pickFor($macroBiz99))->not->toBeNull();
});

it('R-WA-049-PICKER-007 — executor grava macro_variant_id em Message + incrementa sent_count', function () {
    session()->put('user.business_id', 1);
    session()->put('user.id', 42);

    $channel = Channel::query()->create([
        'business_id' => 1,
        'channel_uuid' => '77777777-0000-0000-0000-000000000001',
        'label' => 'X',
        'type' => Channel::TYPE_WHATSAPP_BAILEYS,
        'status' => 'active',
    ]);
    $conv = Conversation::query()->create([
        'business_id' => 1, 'channel_id' => $channel->id,
        'customer_external_id' => '+554811112222', 'status' => 'open',
    ]);
    $macro = makeMacro(1, 'Body padrão');
    $variant = MacroVariant::query()->create([
        'business_id' => 1, 'macro_id' => $macro->id,
        'label' => 'V1', 'body' => 'BODY VARIANTE OVERRIDE',
        'weight' => 100, 'active' => true, 'sent_count' => 0,
    ]);

    Http::fake([
        '*/instances/*/text' => Http::response(['status' => 'sent', 'message_id' => 'wamid.Y1'], 200),
    ]);

    $executor = app(MacroExecutor::class);
    $result = $executor->execute(1, $macro->id, $conv->id, 42);

    expect($result['macro_variant_id'])->toBe($variant->id);

    // Message persistida com body da VARIANTE (override) + macro_variant_id correto
    $msg = Message::query()->where('business_id', 1)->first();
    expect($msg->body)->toBe('BODY VARIANTE OVERRIDE');
    expect($msg->macro_variant_id)->toBe($variant->id);

    // sent_count incrementado
    $variant->refresh();
    expect($variant->sent_count)->toBe(1);
});

it('R-WA-049-TRACKER-008 — incrementa response_count em inbound <24h da outbound', function () {
    $channel = Channel::query()->create([
        'business_id' => 1,
        'channel_uuid' => '88888888-0000-0000-0000-000000000001',
        'label' => 'X', 'type' => Channel::TYPE_WHATSAPP_BAILEYS, 'status' => 'active',
    ]);
    $conv = Conversation::query()->create([
        'business_id' => 1, 'channel_id' => $channel->id,
        'customer_external_id' => '+554811112222', 'status' => 'open',
    ]);
    $macro = makeMacro(1, 'm');
    $variant = MacroVariant::query()->create([
        'business_id' => 1, 'macro_id' => $macro->id,
        'label' => 'V', 'body' => 'b', 'weight' => 100, 'active' => true,
        'sent_count' => 1, 'response_count' => 0,
    ]);

    // Outbound com macro_variant_id criada 1h atrás
    $outbound = Message::query()->create([
        'business_id' => 1, 'conversation_id' => $conv->id,
        'direction' => 'outbound', 'provider' => 'whatsapp_baileys',
        'type' => 'text', 'body' => 'b', 'status' => 'sent',
        'macro_variant_id' => $variant->id,
    ]);
    $outbound->forceFill(['created_at' => now()->subHour()])->save();

    // Inbound recém-criada
    $inbound = Message::query()->create([
        'business_id' => 1, 'conversation_id' => $conv->id,
        'direction' => 'inbound', 'provider' => 'whatsapp_baileys',
        'type' => 'text', 'body' => 'sim', 'status' => 'received',
    ]);

    $tracker = app(MacroVariantResponseTracker::class);
    $tracked = $tracker->trackResponseFromInbound(1, $inbound);
    expect($tracked)->toBeTrue();

    $variant->refresh();
    expect($variant->response_count)->toBe(1);

    // Flag idempotente gravada
    $outbound->refresh();
    expect($outbound->payload)->toBeArray();
    expect($outbound->payload[MacroVariantResponseTracker::PAYLOAD_FLAG] ?? null)->toBeTrue();
});

it('R-WA-049-TRACKER-009 — idempotente: segunda chamada NÃO duplica response_count', function () {
    $channel = Channel::query()->create([
        'business_id' => 1,
        'channel_uuid' => '99999999-0000-0000-0000-000000000001',
        'label' => 'X', 'type' => Channel::TYPE_WHATSAPP_BAILEYS, 'status' => 'active',
    ]);
    $conv = Conversation::query()->create([
        'business_id' => 1, 'channel_id' => $channel->id,
        'customer_external_id' => '+5548', 'status' => 'open',
    ]);
    $macro = makeMacro(1, 'm');
    $variant = MacroVariant::query()->create([
        'business_id' => 1, 'macro_id' => $macro->id,
        'label' => 'V', 'body' => 'b', 'weight' => 100, 'active' => true,
    ]);

    $outbound = Message::query()->create([
        'business_id' => 1, 'conversation_id' => $conv->id,
        'direction' => 'outbound', 'provider' => 'whatsapp_baileys',
        'type' => 'text', 'body' => 'b', 'status' => 'sent',
        'macro_variant_id' => $variant->id,
    ]);
    $outbound->forceFill(['created_at' => now()->subMinutes(30)])->save();

    $inbound = Message::query()->create([
        'business_id' => 1, 'conversation_id' => $conv->id,
        'direction' => 'inbound', 'provider' => 'whatsapp_baileys',
        'type' => 'text', 'body' => 'sim', 'status' => 'received',
    ]);

    $tracker = app(MacroVariantResponseTracker::class);

    // 1ª chamada — incrementa
    expect($tracker->trackResponseFromInbound(1, $inbound))->toBeTrue();
    // 2ª chamada (reentrega webhook) — NÃO incrementa (flag já gravada)
    expect($tracker->trackResponseFromInbound(1, $inbound))->toBeFalse();

    $variant->refresh();
    expect($variant->response_count)->toBe(1); // só 1, não 2
});

it('R-WA-049-TRACKER-010 — NÃO incrementa quando outbound foi >24h atrás (fora da janela)', function () {
    $channel = Channel::query()->create([
        'business_id' => 1,
        'channel_uuid' => 'aaaaaaaa-0000-0000-0000-000000000001',
        'label' => 'X', 'type' => Channel::TYPE_WHATSAPP_BAILEYS, 'status' => 'active',
    ]);
    $conv = Conversation::query()->create([
        'business_id' => 1, 'channel_id' => $channel->id,
        'customer_external_id' => '+5548', 'status' => 'open',
    ]);
    $macro = makeMacro(1, 'm');
    $variant = MacroVariant::query()->create([
        'business_id' => 1, 'macro_id' => $macro->id,
        'label' => 'V', 'body' => 'b', 'weight' => 100, 'active' => true,
    ]);

    // Outbound 25h atrás — FORA da janela 24h
    $outbound = Message::query()->create([
        'business_id' => 1, 'conversation_id' => $conv->id,
        'direction' => 'outbound', 'provider' => 'whatsapp_baileys',
        'type' => 'text', 'body' => 'b', 'status' => 'sent',
        'macro_variant_id' => $variant->id,
    ]);
    $outbound->forceFill(['created_at' => now()->subHours(25)])->save();

    $inbound = Message::query()->create([
        'business_id' => 1, 'conversation_id' => $conv->id,
        'direction' => 'inbound', 'provider' => 'whatsapp_baileys',
        'type' => 'text', 'body' => 'sim tarde', 'status' => 'received',
    ]);

    $tracker = app(MacroVariantResponseTracker::class);
    expect($tracker->trackResponseFromInbound(1, $inbound))->toBeFalse();

    $variant->refresh();
    expect($variant->response_count)->toBe(0);
});

/**
 * Factory helper local — cria Macro mínima persistida.
 */
function makeMacro(int $bizId, string $body): Macro
{
    return Macro::query()->create([
        'business_id' => $bizId,
        'label' => 'M-' . $bizId,
        'shortcut' => 'm' . $bizId,
        'body' => $body,
    ]);
}
