<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Entities\ChannelUserAccess;
use Modules\Whatsapp\Entities\Conversation;
use Modules\Whatsapp\Entities\Tag;
use Modules\Whatsapp\Http\Controllers\Admin\CaixaUnificadaController;

uses(Tests\TestCase::class);

/**
 * R-WA-CAIXA-UNIF — Caixa Unificada V4 Controller.
 *
 * Tela NOVA `/atendimento/caixa-unificada` (Cowork redesign omnichannel).
 * Coexiste com /atendimento/inbox durante canary 7d.
 *
 * Cobertura:
 *   1. Happy path — render com payload válido (props básicas + queue derivada)
 *   2. Cross-tenant Tier 0 ADR 0093 — biz=99 invisível pra biz=1
 *   3. Permission `whatsapp.access` — defesa em profundidade ACL canal=fila
 *      (user sem ACL no canal NÃO vê convs daquele canal)
 *
 * @see Modules/Whatsapp/Http/Controllers/Admin/CaixaUnificadaController.php
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/requisitos/Whatsapp/CaixaUnificadaV4-visual-comparison.md
 *
 * NUNCA usar biz=4 (ROTA LIVRE cliente real) em tests — ADR 0101.
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

/**
 * Prefixo `cuct*` (CaixaUnificadaControllerTest) pra evitar colisão com
 * helpers `iqt*`/`cfi*` em outros arquivos Pest do mesmo módulo.
 */
function cuctSetUserAndGrant(int $businessId, int $userId, array $channelIds): void
{
    $stub = new class extends \Illuminate\Foundation\Auth\User {
        protected $table = 'users';
        protected $guarded = [];
        public function can($abilities, $arguments = []): bool { return false; }
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

function cuctMakeChannel(int $businessId, string $uuid, string $status = 'active'): Channel
{
    return Channel::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => $businessId,
        'channel_uuid' => $uuid,
        'label' => 'Suporte',
        'type' => Channel::TYPE_WHATSAPP_BAILEYS,
        'status' => $status,
    ]);
}

function cuctMakeConv(int $businessId, int $channelId, array $tagSlugs = []): Conversation
{
    $conv = Conversation::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => $businessId,
        'channel_id' => $channelId,
        'customer_external_id' => '+5511' . str_pad((string) random_int(1, 99999999), 8, '0', STR_PAD_LEFT),
        'contact_name' => 'Cliente Teste',
        'status' => 'open',
        'last_message_at' => now(),
    ]);

    foreach ($tagSlugs as $slug) {
        $tag = Tag::withoutGlobalScope(ScopeByBusiness::class)->firstOrCreate(
            ['business_id' => $businessId, 'slug' => $slug],
            ['label' => ucfirst($slug), 'color' => 'slate']
        );
        $conv->tags()->attach($tag->id);
    }

    return $conv;
}

function cuctIndexProps(CaixaUnificadaController $controller, Request $request): array
{
    $token = Mockery::mock(\Modules\Whatsapp\Services\Centrifugo\CentrifugoTokenIssuer::class);
    $token->shouldReceive('issue')->andReturn(null);

    $response = $controller->index($request, $token);

    $reflection = new \ReflectionClass($response);
    $propsProp = $reflection->getProperty('props');
    $propsProp->setAccessible(true);
    return $propsProp->getValue($response);
}

function cuctBuildRequest(array $query = []): Request
{
    $qs = $query ? '?' . http_build_query($query) : '';
    $request = Request::create('/atendimento/caixa-unificada' . $qs, 'GET');
    $request->setLaravelSession(app('session.store'));
    return $request;
}

function cuctResolveDefer($prop): array
{
    if ($prop instanceof \Inertia\DeferProp || $prop instanceof \Inertia\OptionalProp) {
        $prop = $prop();
    }
    return is_array($prop) ? $prop : [];
}

// ============================================================================
// 1. Happy path — render com payload válido
// ============================================================================

it('R-WA-CAIXA-UNIF-001 — happy path render com props básicas + queue derivada', function () {
    $ch = cuctMakeChannel(1, 'caixa-unif-001-uuid');
    cuctMakeConv(1, $ch->id, ['financeiro']);  // tag financeiro → fila financeiro
    cuctSetUserAndGrant(1, 10, [$ch->id]);

    $props = cuctIndexProps(new CaixaUnificadaController(), cuctBuildRequest());

    // Eager props presentes
    expect($props)->toHaveKey('businessId')
        ->and($props['businessId'])->toBe(1)
        ->and($props)->toHaveKey('queues')
        ->and($props)->toHaveKey('defaultQueue')
        ->and($props['defaultQueue'])->toBe('comercial')
        ->and($props)->toHaveKey('statusFilter')
        ->and($props['statusFilter'])->toBe('abertas');

    // Conversations payload (defer resolve)
    $convs = cuctResolveDefer($props['conversations']);
    expect($convs)->toHaveKey('data')
        ->and($convs['data'])->toHaveCount(1)
        ->and($convs['data'][0]['queue']['slug'])->toBe('financeiro')
        ->and($convs['data'][0]['queue']['label'])->toBe('Financeiro')
        ->and($convs['data'][0]['queue']['hue'])->toBe(280);

    // Channels catalog (7 tipos canônicos)
    $channels = cuctResolveDefer($props['availableChannels']);
    expect($channels)->toHaveCount(7)
        ->and(collect($channels)->pluck('id')->all())
        ->toContain('whatsapp_baileys', 'whatsapp_meta', 'instagram_dm', 'email_imap', 'mercadolivre');

    // Baileys deve estar 'ativo' (criamos 1 channel ativo), outros 'em_breve'
    $baileysChan = collect($channels)->firstWhere('id', 'whatsapp_baileys');
    expect($baileysChan['status'])->toBe('ativo');
    $metaChan = collect($channels)->firstWhere('id', 'whatsapp_meta');
    expect($metaChan['status'])->toBe('em_breve');
});

// ============================================================================
// 2. Cross-tenant Tier 0 (ADR 0093) — biz=99 NUNCA vaza pra biz=1
// ============================================================================

it('R-WA-CAIXA-UNIF-002 — cross-tenant biz=99 invisível pra biz=1 (Tier 0)', function () {
    // biz=99 (outro tenant) cria canal + conv
    $ch99 = cuctMakeChannel(99, 'caixa-unif-002-other-uuid');
    cuctMakeConv(99, $ch99->id, ['vendas']);

    // biz=1 cria seu próprio canal/conv
    $ch1 = cuctMakeChannel(1, 'caixa-unif-002-self-uuid');
    $myConv = cuctMakeConv(1, $ch1->id, ['suporte']);
    cuctSetUserAndGrant(1, 10, [$ch1->id]);

    $props = cuctIndexProps(new CaixaUnificadaController(), cuctBuildRequest());
    $convs = cuctResolveDefer($props['conversations']);

    // SÓ conv biz=1 visível
    expect($convs['data'])->toHaveCount(1)
        ->and($convs['data'][0]['id'])->toBe($myConv->id);

    // Accounts payload também filtrado
    $accounts = cuctResolveDefer($props['availableAccounts']);
    expect($accounts)->toHaveCount(1)
        ->and($accounts[0]['id'])->toBe($ch1->id);

    // Stats também isoladas
    $stats = cuctResolveDefer($props['stats']);
    expect($stats['abertas'])->toBe(1)
        ->and($stats['active_accounts'])->toBe(1);
});

// ============================================================================
// 3. Permission ACL canal=fila — user sem ACL não vê conversas do canal
// ============================================================================

it('R-WA-CAIXA-UNIF-003 — user sem ACL no canal NÃO vê convs daquele canal', function () {
    // 2 canais no mesmo business
    $chAllowed = cuctMakeChannel(1, 'caixa-unif-003-allowed-uuid');
    $chForbidden = cuctMakeChannel(1, 'caixa-unif-003-forbidden-uuid');

    cuctMakeConv(1, $chAllowed->id, ['vendas']);
    cuctMakeConv(1, $chForbidden->id, ['financeiro']);  // user 10 não tem ACL aqui

    // User 10 só tem ACL no chAllowed
    cuctSetUserAndGrant(1, 10, [$chAllowed->id]);

    $props = cuctIndexProps(new CaixaUnificadaController(), cuctBuildRequest());
    $convs = cuctResolveDefer($props['conversations']);

    // Só conv do canal autorizado
    expect($convs['data'])->toHaveCount(1)
        ->and($convs['data'][0]['channel_id'])->toBe($chAllowed->id);

    // Tentativa de filtrar por canal proibido via ?account_id → 403 (fail-loud)
    $forbiddenRequest = cuctBuildRequest(['account_id' => $chForbidden->id]);
    expect(fn () => cuctIndexProps(new CaixaUnificadaController(), $forbiddenRequest))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});
