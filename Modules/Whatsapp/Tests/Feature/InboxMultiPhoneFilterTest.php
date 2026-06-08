<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Entities\ChannelUserAccess;
use Modules\Whatsapp\Entities\Conversation;
use Modules\Whatsapp\Http\Controllers\Admin\InboxController;

uses(Tests\TestCase::class);

/**
 * US-WA-040 (CYCLE-08 PR-A) — Multi-phone UI dropdown topbar.
 *
 * Garante o filtro `?channel_id=N` no Inbox e a composição com ACL:
 *
 *   1. User com acesso a 2 canais (A+B) sem `?channel_id` → vê convs dos 2
 *   2. User com `?channel_id=A` → vê só convs de A (filtro per-canal aplicado)
 *   3. User com `?channel_id=C` (sem acesso) → 403 (fail-loud)
 *   4. Cross-tenant biz=99 — user biz=1 com `?channel_id=999` (canal biz=99) → 403
 *   5. User com 0 canais → `availableChannels=[]` + estado UX vazio
 *   6. availableChannels inclui display_identifier, channel_health, unread_count
 *
 * Pattern segue CanalFilaIsolationTest (PR #644) — User stub sem persist +
 * auth()->setUser + session.business_id + forgetInstance ScopeByBusiness.
 *
 * @see memory/decisions/0117-multiplos-numeros-whatsapp-por-business.md
 * @see memory/decisions/0135-omnichannel-inbox-arquitetura.md
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-040
 */
beforeEach(function () {
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

function mpfSetUser(int $businessId, int $userId, bool $canSeeAll = false): void
{
    $stub = new class extends \Illuminate\Foundation\Auth\User {
        protected $table = 'users';
        protected $guarded = [];
        public bool $canSeeAllStub = false;
        public function can($abilities, $arguments = []): bool
        {
            if ($abilities === 'whatsapp.view-all-phones') {
                return $this->canSeeAllStub;
            }
            return false;
        }
    };
    $stub->id = $userId;
    $stub->business_id = $businessId;
    $stub->canSeeAllStub = $canSeeAll;
    auth()->setUser($stub);

    session()->put('user.business_id', $businessId);
    session()->put('user.id', $userId);
    app()->forgetInstance(ScopeByBusiness::class);
}

function mpfMakeChannel(int $businessId, string $label, string $uuid, ?string $phone = null, string $health = 'healthy', int $unread = 0): Channel
{
    return Channel::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => $businessId,
        'channel_uuid' => $uuid,
        'label' => $label,
        'type' => Channel::TYPE_WHATSAPP_BAILEYS,
        'status' => 'active',
        'display_identifier' => $phone,
        'channel_health' => $health,
    ]);
}

function mpfMakeConv(int $businessId, int $channelId, string $phone, string $name, int $unread = 0): Conversation
{
    return Conversation::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => $businessId,
        'channel_id' => $channelId,
        'customer_external_id' => $phone,
        'contact_name' => $name,
        'status' => 'open',
        'unread_count' => $unread,
        'last_message_at' => now(),
    ]);
}

function mpfGrant(int $businessId, int $channelId, int $userId): void
{
    ChannelUserAccess::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => $businessId,
        'channel_id' => $channelId,
        'user_id' => $userId,
        'granted_by_user_id' => 1,
        'granted_at' => now(),
    ]);
}

/**
 * Invoca index() e retorna props (conversations.data + availableChannels +
 * selectedChannelId) via reflection. Mesma técnica de CanalFilaIsolationTest.
 */
function mpfIndexProps(InboxController $controller, Request $request): array
{
    $token = Mockery::mock(\Modules\Whatsapp\Services\Centrifugo\CentrifugoTokenIssuer::class);
    $token->shouldReceive('issue')->andReturn(null);

    $response = $controller->index($request, $token);

    $reflection = new \ReflectionClass($response);
    $propsProp = $reflection->getProperty('props');
    $propsProp->setAccessible(true);
    $renderedProps = $propsProp->getValue($response);

    $convs = $renderedProps['conversations']['data'] ?? [];
    if (is_callable($convs)) {
        $convs = $convs();
    }
    if ($convs instanceof \Illuminate\Support\Collection) {
        $convs = $convs->all();
    }

    $availableChannels = $renderedProps['availableChannels'] ?? [];
    if (is_callable($availableChannels)) {
        $availableChannels = $availableChannels();
    }
    if ($availableChannels instanceof \Illuminate\Support\Collection) {
        $availableChannels = $availableChannels->all();
    }

    return [
        'conv_ids' => collect($convs)->pluck('id')->map(fn ($id) => (int) $id)->all(),
        'available_channels' => collect($availableChannels)->map(fn ($c) => is_array($c) ? $c : (array) $c)->all(),
        'selected_channel_id' => $renderedProps['selectedChannelId'] ?? null,
    ];
}

function mpfBuildRequest(array $query = []): Request
{
    $request = Request::create('/atendimento/inbox', 'GET', $query);
    $request->setLaravelSession(app('session.store'));
    return $request;
}

it('R-WA-040-001 — User com acesso a 2 canais sem ?channel_id vê convs dos 2', function () {
    $chA = mpfMakeChannel(1, 'Suporte', 'mpf-001-a', '+5511900000001');
    $chB = mpfMakeChannel(1, 'Financeiro', 'mpf-001-b', '+5511900000002');
    $c1 = mpfMakeConv(1, $chA->id, '+5599100000001', 'Cliente A');
    $c2 = mpfMakeConv(1, $chB->id, '+5599100000002', 'Cliente B');

    mpfGrant(1, $chA->id, 10);
    mpfGrant(1, $chB->id, 10);
    mpfSetUser(1, 10);

    $props = mpfIndexProps(new InboxController(), mpfBuildRequest());
    expect($props['conv_ids'])->toContain($c1->id);
    expect($props['conv_ids'])->toContain($c2->id);
    expect($props['selected_channel_id'])->toBeNull();
    expect(count($props['available_channels']))->toBe(2);
});

it('R-WA-040-002 — User com ?channel_id=A vê SÓ convs do canal A (filtro per-canal)', function () {
    $chA = mpfMakeChannel(1, 'Suporte', 'mpf-002-a', '+5511900000003');
    $chB = mpfMakeChannel(1, 'Financeiro', 'mpf-002-b', '+5511900000004');
    $c1 = mpfMakeConv(1, $chA->id, '+5599200000001', 'Cliente A');
    $c2 = mpfMakeConv(1, $chB->id, '+5599200000002', 'Cliente B');

    mpfGrant(1, $chA->id, 11);
    mpfGrant(1, $chB->id, 11);
    mpfSetUser(1, 11);

    $props = mpfIndexProps(new InboxController(), mpfBuildRequest(['channel_id' => $chA->id]));
    expect($props['conv_ids'])->toEqual([$c1->id]);
    expect($props['conv_ids'])->not->toContain($c2->id);
    expect($props['selected_channel_id'])->toBe($chA->id);
});

it('R-WA-040-003 — User com ?channel_id=C (canal SEM acesso) → 403 fail-loud', function () {
    $chA = mpfMakeChannel(1, 'Suporte', 'mpf-003-a');
    $chC = mpfMakeChannel(1, 'Vendas', 'mpf-003-c');
    mpfMakeConv(1, $chC->id, '+5599300000001', 'Cliente C');

    // User só tem acesso ao canal A — tenta filtrar pelo C
    mpfGrant(1, $chA->id, 12);
    mpfSetUser(1, 12);

    expect(fn () => mpfIndexProps(new InboxController(), mpfBuildRequest(['channel_id' => $chC->id])))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});

it('R-WA-040-004 — Cross-tenant: user biz=1 com ?channel_id=N (canal biz=99) → 403', function () {
    $chBiz1 = mpfMakeChannel(1, 'Biz 1', 'mpf-004-biz1');
    $chBiz99 = mpfMakeChannel(99, 'Biz 99', 'mpf-004-biz99');
    mpfMakeConv(99, $chBiz99->id, '+5599400000099', 'Cliente Cross');

    mpfGrant(1, $chBiz1->id, 13);
    mpfSetUser(1, 13);

    // Atacante passa channel_id de outro business — controller deve abortar
    // ANTES da query (no validate ensureChannelIdAccessOrAbort)
    expect(fn () => mpfIndexProps(new InboxController(), mpfBuildRequest(['channel_id' => $chBiz99->id])))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});

it('R-WA-040-005 — User com 0 canais → availableChannels=[] + lista vazia (estado UX vazio)', function () {
    $ch = mpfMakeChannel(1, 'Canal sem acesso', 'mpf-005-orfao');
    mpfMakeConv(1, $ch->id, '+5599500000001', 'Conv invisível');

    mpfSetUser(1, 14); // SEM nenhum grant

    $props = mpfIndexProps(new InboxController(), mpfBuildRequest());
    expect($props['available_channels'])->toBe([]);
    expect($props['conv_ids'])->toBe([]);
    expect($props['selected_channel_id'])->toBeNull();
});

it('R-WA-040-006 — availableChannels inclui display_identifier, channel_health, unread_count', function () {
    $chA = mpfMakeChannel(1, 'Suporte', 'mpf-006-a', '+5511900000010', 'healthy');
    $chB = mpfMakeChannel(1, 'Financeiro', 'mpf-006-b', '+5511900000020', 'degraded');

    // Cria conversas com unread_count pra validar agregação per-canal
    mpfMakeConv(1, $chA->id, '+5599600000001', 'Conv 1', unread: 3);
    mpfMakeConv(1, $chA->id, '+5599600000002', 'Conv 2', unread: 2);
    mpfMakeConv(1, $chB->id, '+5599600000003', 'Conv 3', unread: 7);

    mpfGrant(1, $chA->id, 15);
    mpfGrant(1, $chB->id, 15);
    mpfSetUser(1, 15);

    $props = mpfIndexProps(new InboxController(), mpfBuildRequest());
    $byId = collect($props['available_channels'])->keyBy('id');

    expect(count($props['available_channels']))->toBe(2);

    expect($byId[$chA->id]['label'])->toBe('Suporte');
    expect($byId[$chA->id]['display_identifier'])->toBe('+5511900000010');
    expect($byId[$chA->id]['channel_health'])->toBe('healthy');
    expect($byId[$chA->id]['unread_count'])->toBe(5); // 3 + 2

    expect($byId[$chB->id]['display_identifier'])->toBe('+5511900000020');
    expect($byId[$chB->id]['channel_health'])->toBe('degraded');
    expect($byId[$chB->id]['unread_count'])->toBe(7);
});

it('R-WA-040-007 — Superadmin (Gate whatsapp.view-all-phones) com ?channel_id válido vê convs do canal', function () {
    $ch = mpfMakeChannel(1, 'Canal X', 'mpf-007-admin');
    $c = mpfMakeConv(1, $ch->id, '+5599700000001', 'Conv admin');

    // Admin sem grant explícito mas com Gate true — bypass passa por
    // ensureChannelIdAccessOrAbort (Channel existe no biz, Gate true)
    mpfSetUser(1, 99, canSeeAll: true);

    $props = mpfIndexProps(new InboxController(), mpfBuildRequest(['channel_id' => $ch->id]));
    expect($props['conv_ids'])->toEqual([$c->id]);
    expect($props['selected_channel_id'])->toBe($ch->id);
});
