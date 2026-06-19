<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Entities\ChannelUserAccess;
use Modules\Whatsapp\Entities\Conversation;
use Modules\Whatsapp\Http\Controllers\Admin\InboxController;

uses(Tests\TestCase::class);

/**
 * R-WA-FILTERS — 5 filtros novos no Inbox `/atendimento/inbox`.
 *
 * Wagner 2026-05-12: complementa filtros existentes (tab=all/unread/assigned/
 * bot/resolved, q, channel, tags) com:
 *
 *   1. tab=awaiting_human  — bot escalou, fila humano
 *   2. tab=archived        — arquivada pelo atendente
 *   3. within_24h          — janela 24h Meta (true/false)
 *   4. unlinked            — sem Contact CRM (oportunidade cadastro)
 *   5. inbound_aging       — última msg do cliente > X (6h/12h/24h/48h/7d)
 *   6. orderBy             — `last_message` (default) | `inbound`
 *
 * Tier 0 IRREVOGÁVEL (ADR 0093): todos filtros DEPOIS do business_id global
 * scope. Cross-tenant biz=99 invisível mesmo com filtro aberto.
 *
 * Pattern reusa helpers cfi* do CanalFilaIsolationTest (PR #644) — User stub
 * + reflection de props pra evitar render Inertia em ambiente Pest.
 *
 * @see Modules/Whatsapp/Http/Controllers/Admin/InboxController.php
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('era-sqlite: schema sintético manual incompatível com MySQL persistente — quarentena Onda 2 SDD floor; burn-down converte depois.');
    }

    foreach (['messages', 'channel_user_access', 'whatsapp_conversation_tags', 'whatsapp_tags', 'conversations', 'channels'] as $t) {
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
        $table->unique(['business_id', 'slug'], 'wa_tags_biz_slug_uniq');
    });

    Schema::create('whatsapp_conversation_tags', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedBigInteger('conversation_id');
        $table->unsignedBigInteger('tag_id');
        $table->timestamp('created_at')->useCurrent();
        $table->timestamp('updated_at')->nullable();
        $table->unsignedInteger('created_by_user_id')->nullable();
        $table->unique(['conversation_id', 'tag_id'], 'wa_conv_tags_uniq');
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

    Schema::create('channel_user_access', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->unsignedBigInteger('channel_id');
        $table->unsignedInteger('user_id');
        $table->unsignedInteger('granted_by_user_id');
        $table->timestamp('granted_at');
        $table->timestamp('revoked_at')->nullable();
        $table->unsignedInteger('revoked_by_user_id')->nullable();
        $table->timestamps();
        $table->unique(['channel_id', 'user_id', 'revoked_at'], 'cua_channel_user_unq');
        $table->index(['business_id', 'user_id'], 'cua_biz_user_idx');
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
        $table->timestamp('created_at')->useCurrent();
        $table->timestamp('updated_at')->nullable();
    });
});

/**
 * User stub + sessão multi-tenant + grant canal pra simplificar:
 * todos os tests deste arquivo usam user.id=10 com acesso a TODOS os canais
 * criados (filtro ACL canal não é o objeto sob teste — é o filtro novo).
 */
function ifSetUserAndGrantAll(int $businessId, int $userId, array $channelIds): void
{
    $stub = new class extends \Illuminate\Foundation\Auth\User {
        protected $table = 'users';
        protected $guarded = [];
        public function can($abilities, $arguments = []): bool
        {
            return false; // sem bypass — força filtro ACL
        }
    };
    $stub->id = $userId;
    $stub->business_id = $businessId;
    auth()->setUser($stub);

    session()->put('user.business_id', $businessId);
    session()->put('user.id', $userId);
    app()->forgetInstance(ScopeByBusiness::class);

    foreach ($channelIds as $chId) {
        ChannelUserAccess::withoutGlobalScope(ScopeByBusiness::class)->create([
            'business_id' => $businessId,
            'channel_id' => $chId,
            'user_id' => $userId,
            'granted_by_user_id' => 1,
            'granted_at' => now(),
        ]);
    }
}

function ifMakeChannel(int $businessId, string $uuid): Channel
{
    return Channel::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => $businessId,
        'channel_uuid' => $uuid,
        'label' => 'Suporte',
        'type' => Channel::TYPE_WHATSAPP_BAILEYS,
        'status' => 'active',
    ]);
}

function ifMakeConv(int $businessId, int $channelId, array $attrs = []): Conversation
{
    return Conversation::withoutGlobalScope(ScopeByBusiness::class)->create(array_merge([
        'business_id' => $businessId,
        'channel_id' => $channelId,
        'customer_external_id' => '+5511' . str_pad((string) random_int(1, 99999999), 8, '0', STR_PAD_LEFT),
        'contact_name' => 'Cliente',
        'status' => 'open',
        'last_message_at' => now(),
    ], $attrs));
}

/**
 * Invoca index() e devolve props da Inertia\Response via reflection.
 */
function ifIndexProps(InboxController $controller, Request $request): array
{
    $token = Mockery::mock(\Modules\Whatsapp\Services\Centrifugo\CentrifugoTokenIssuer::class);
    $token->shouldReceive('issue')->andReturn(null);

    $response = $controller->index($request, $token);

    $reflection = new \ReflectionClass($response);
    $propsProp = $reflection->getProperty('props');
    $propsProp->setAccessible(true);

    return $propsProp->getValue($response);
}

function ifConvIds(array $props): array
{
    $data = $props['conversations']['data'] ?? [];
    if (is_callable($data)) $data = $data();
    if ($data instanceof \Illuminate\Support\Collection) $data = $data->all();
    return collect($data)->pluck('id')->map(fn ($id) => (int) $id)->all();
}

function ifBuildRequest(array $query = []): Request
{
    // Constrói query string explícita pra garantir que $request->input() lê
    // do query bag (alguns helpers do Laravel ignoram o 3º param array em
    // Request::create quando method=GET, dependendo da versão).
    $qs = $query ? '?' . http_build_query($query) : '';
    $request = Request::create('/atendimento/inbox' . $qs, 'GET');
    $request->setLaravelSession(app('session.store'));
    return $request;
}

// ============================================================================
// 1 & 2 — tab=awaiting_human + tab=archived
// ============================================================================

it('R-WA-FILTERS-001 — tab=awaiting_human filtra apenas status=awaiting_human', function () {
    $ch = ifMakeChannel(1, 'flt-001-uuid');
    $convOpen = ifMakeConv(1, $ch->id, ['status' => 'open']);
    $convAwaiting = ifMakeConv(1, $ch->id, ['status' => 'awaiting_human']);
    $convResolved = ifMakeConv(1, $ch->id, ['status' => 'resolved']);

    ifSetUserAndGrantAll(1, 10, [$ch->id]);

    $props = ifIndexProps(new InboxController(), ifBuildRequest(['tab' => 'awaiting_human']));

    expect(ifConvIds($props))->toBe([$convAwaiting->id])
        ->and($props['stats']['awaiting_human'])->toBe(1);
});

it('R-WA-FILTERS-002 — tab=archived filtra apenas status=archived', function () {
    $ch = ifMakeChannel(1, 'flt-002-uuid');
    $convOpen = ifMakeConv(1, $ch->id, ['status' => 'open']);
    $convArch1 = ifMakeConv(1, $ch->id, ['status' => 'archived']);
    $convArch2 = ifMakeConv(1, $ch->id, ['status' => 'archived']);

    ifSetUserAndGrantAll(1, 10, [$ch->id]);

    $props = ifIndexProps(new InboxController(), ifBuildRequest(['tab' => 'archived']));

    expect(ifConvIds($props))->toHaveCount(2)
        ->and(ifConvIds($props))->toContain($convArch1->id, $convArch2->id)
        ->and($props['stats']['archived'])->toBe(2);
});

// ============================================================================
// 3 — within_24h
// ============================================================================

it('R-WA-FILTERS-003 — within_24h=true retorna apenas convs com last_inbound_at < 24h', function () {
    $ch = ifMakeChannel(1, 'flt-003-uuid');
    $fresh = ifMakeConv(1, $ch->id, ['last_inbound_at' => now()->subHours(2)]);
    $stale = ifMakeConv(1, $ch->id, ['last_inbound_at' => now()->subHours(48)]);
    $never = ifMakeConv(1, $ch->id, ['last_inbound_at' => null]);

    ifSetUserAndGrantAll(1, 10, [$ch->id]);

    $props = ifIndexProps(new InboxController(), ifBuildRequest(['within_24h' => 'true']));

    expect(ifConvIds($props))->toBe([$fresh->id])
        ->and($props['within24h'])->toBeTrue();
});

it('R-WA-FILTERS-004 — within_24h=false retorna convs com last_inbound_at >= 24h OU null', function () {
    $ch = ifMakeChannel(1, 'flt-004-uuid');
    $fresh = ifMakeConv(1, $ch->id, ['last_inbound_at' => now()->subHours(2)]);
    $stale = ifMakeConv(1, $ch->id, ['last_inbound_at' => now()->subHours(48)]);
    $never = ifMakeConv(1, $ch->id, ['last_inbound_at' => null]);

    ifSetUserAndGrantAll(1, 10, [$ch->id]);

    $props = ifIndexProps(new InboxController(), ifBuildRequest(['within_24h' => 'false']));

    $ids = ifConvIds($props);
    expect($ids)->toHaveCount(2)
        ->and($ids)->toContain($stale->id, $never->id)
        ->and($ids)->not->toContain($fresh->id)
        ->and($props['within24h'])->toBeFalse();
});

// ============================================================================
// 4 — unlinked (sem Contact CRM)
// ============================================================================

it('R-WA-FILTERS-005 — unlinked=true retorna apenas convs com contact_id=null', function () {
    $ch = ifMakeChannel(1, 'flt-005-uuid');
    $linked = ifMakeConv(1, $ch->id, ['contact_id' => 42]);
    $unlinked1 = ifMakeConv(1, $ch->id, ['contact_id' => null]);
    $unlinked2 = ifMakeConv(1, $ch->id, ['contact_id' => null]);

    ifSetUserAndGrantAll(1, 10, [$ch->id]);

    $props = ifIndexProps(new InboxController(), ifBuildRequest(['unlinked' => 'true']));

    $ids = ifConvIds($props);
    expect($ids)->toHaveCount(2)
        ->and($ids)->toContain($unlinked1->id, $unlinked2->id)
        ->and($ids)->not->toContain($linked->id)
        ->and($props['unlinked'])->toBeTrue();
});

// ============================================================================
// 5 — inbound_aging
// ============================================================================

it('R-WA-FILTERS-006 — inbound_aging=24h lista convs com cliente esperando > 24h E cliente foi último a falar', function () {
    $ch = ifMakeChannel(1, 'flt-006-uuid');
    // Cliente falou há 48h, atendente ainda não respondeu (last_outbound_at=null)
    $waiting = ifMakeConv(1, $ch->id, [
        'last_inbound_at' => now()->subHours(48),
        'last_outbound_at' => null,
    ]);
    // Cliente falou há 48h MAS atendente respondeu há 1h (resolvido — não conta)
    $answered = ifMakeConv(1, $ch->id, [
        'last_inbound_at' => now()->subHours(48),
        'last_outbound_at' => now()->subHours(1),
    ]);
    // Cliente falou há 1h — não passou no aging
    $tooFresh = ifMakeConv(1, $ch->id, [
        'last_inbound_at' => now()->subHours(1),
        'last_outbound_at' => null,
    ]);

    ifSetUserAndGrantAll(1, 10, [$ch->id]);

    $props = ifIndexProps(new InboxController(), ifBuildRequest(['inbound_aging' => '24h']));

    expect(ifConvIds($props))->toBe([$waiting->id])
        ->and($props['inboundAging'])->toBe('24h');
});

it('R-WA-FILTERS-007 — inbound_aging SKIP conversa onde atendente respondeu após msg do cliente', function () {
    $ch = ifMakeChannel(1, 'flt-007-uuid');
    // Cliente falou há 48h, atendente respondeu há 24h (depois) — SKIP
    $answered = ifMakeConv(1, $ch->id, [
        'last_inbound_at' => now()->subHours(48),
        'last_outbound_at' => now()->subHours(24),
    ]);

    ifSetUserAndGrantAll(1, 10, [$ch->id]);

    $props = ifIndexProps(new InboxController(), ifBuildRequest(['inbound_aging' => '6h']));

    expect(ifConvIds($props))->toBe([]); // nenhuma — atendente já respondeu
});

it('R-WA-FILTERS-008 — inbound_aging valor inválido (ex: "1y") é ignorado (sem SQL injection)', function () {
    $ch = ifMakeChannel(1, 'flt-008-uuid');
    $c1 = ifMakeConv(1, $ch->id);

    ifSetUserAndGrantAll(1, 10, [$ch->id]);

    // valor fora da whitelist (6h/12h/24h/48h/7d) — controller cai no `default => null`
    // e NÃO aplica filtro algum. Lista retorna tudo.
    $props = ifIndexProps(new InboxController(), ifBuildRequest(['inbound_aging' => "1y' OR 1=1--"]));

    expect(ifConvIds($props))->toBe([$c1->id]); // 1 conv visível, sem 500
});

// ============================================================================
// 6 — orderBy
// ============================================================================

it('R-WA-FILTERS-009 — orderBy=inbound ordena por last_inbound_at DESC (não last_message_at)', function () {
    $ch = ifMakeChannel(1, 'flt-009-uuid');
    // C1: msg recente do atendente (last_message_at recente) mas cliente mudo há tempo
    $c1 = ifMakeConv(1, $ch->id, [
        'last_message_at' => now()->subMinutes(5),
        'last_inbound_at' => now()->subHours(72),
    ]);
    // C2: cliente acabou de falar (last_inbound_at recente)
    $c2 = ifMakeConv(1, $ch->id, [
        'last_message_at' => now()->subHours(2),
        'last_inbound_at' => now()->subMinutes(2),
    ]);

    ifSetUserAndGrantAll(1, 10, [$ch->id]);

    // Default (last_message_at desc): C1 primeiro (5min < 2h)
    $defaultProps = ifIndexProps(new InboxController(), ifBuildRequest());
    expect(ifConvIds($defaultProps))->toBe([$c1->id, $c2->id]);

    // orderBy=inbound: C2 primeiro (cliente mais recente)
    $inboundProps = ifIndexProps(new InboxController(), ifBuildRequest(['orderBy' => 'inbound']));
    expect(ifConvIds($inboundProps))->toBe([$c2->id, $c1->id])
        ->and($inboundProps['orderBy'])->toBe('inbound');
});

// ============================================================================
// Multi-tenant Tier 0 — ADR 0093
// ============================================================================

it('R-WA-FILTERS-010 — Tier 0: biz=99 invisível mesmo com filtros abertos (cross-tenant blocked)', function () {
    $ch1 = ifMakeChannel(1, 'flt-010-biz1-uuid');
    $ch99 = ifMakeChannel(99, 'flt-010-biz99-uuid');

    $convBiz1 = ifMakeConv(1, $ch1->id, ['status' => 'archived', 'contact_id' => null]);
    $convBiz99 = ifMakeConv(99, $ch99->id, ['status' => 'archived', 'contact_id' => null]);

    // User logado em biz=1 com acesso ao canal biz=1 apenas
    ifSetUserAndGrantAll(1, 10, [$ch1->id]);

    // Mesmo filtrando archived + unlinked, NÃO deve aparecer conv biz=99
    $props = ifIndexProps(new InboxController(), ifBuildRequest([
        'tab' => 'archived',
        'unlinked' => 'true',
    ]));

    expect(ifConvIds($props))->toBe([$convBiz1->id])
        ->and(ifConvIds($props))->not->toContain($convBiz99->id);
});

// ============================================================================
// Combinações múltiplas — AND lógico
// ============================================================================

it('R-WA-FILTERS-011 — combinação tab=awaiting_human + within_24h=true + unlinked=true é AND-ed', function () {
    $ch = ifMakeChannel(1, 'flt-011-uuid');

    // Match: awaiting_human + last_inbound_at < 24h + sem contact
    $match = ifMakeConv(1, $ch->id, [
        'status' => 'awaiting_human',
        'last_inbound_at' => now()->subHours(2),
        'contact_id' => null,
    ]);
    // Falha: status open (não awaiting_human)
    $miss1 = ifMakeConv(1, $ch->id, [
        'status' => 'open',
        'last_inbound_at' => now()->subHours(2),
        'contact_id' => null,
    ]);
    // Falha: awaiting_human mas tem contact
    $miss2 = ifMakeConv(1, $ch->id, [
        'status' => 'awaiting_human',
        'last_inbound_at' => now()->subHours(2),
        'contact_id' => 42,
    ]);
    // Falha: awaiting_human + unlinked, mas fora 24h
    $miss3 = ifMakeConv(1, $ch->id, [
        'status' => 'awaiting_human',
        'last_inbound_at' => now()->subHours(48),
        'contact_id' => null,
    ]);

    ifSetUserAndGrantAll(1, 10, [$ch->id]);

    $props = ifIndexProps(new InboxController(), ifBuildRequest([
        'tab' => 'awaiting_human',
        'within_24h' => 'true',
        'unlinked' => 'true',
    ]));

    expect(ifConvIds($props))->toBe([$match->id]);
});
