<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Entities\ChannelUserAccess;
use Modules\Whatsapp\Entities\Conversation;
use Modules\Whatsapp\Entities\Tag;
use Modules\Whatsapp\Http\Controllers\Admin\InboxController;

uses(Tests\TestCase::class);

/**
 * R-WA-QUEUE-DERIVATION — heurística tag → fila Caixa Unificada v4.
 *
 * Wagner 2026-05-15 (handoff Claude Design `inbox-page.jsx`):
 * Filas APENAS visuais (sem DB). InboxController deriva `queue` per-conversa
 * via interseção entre `conversations.tags` e `config('whatsapp.queues').*.trigger_tags`.
 *
 * Cobre:
 *   1. Conv sem tag → fila default (`comercial`)
 *   2. Conv com tag `financeiro` → fila `financeiro`
 *   3. Conv com tag `cobranca` → fila `financeiro`
 *   4. Conv com tag unrelated → fila default
 *   5. Props `queues` + `defaultQueue` presentes na render
 *   6. Cross-tenant: biz=99 NÃO leak no inbox biz=1 (Tier 0 ADR 0093)
 *
 * @see memory/requisitos/Whatsapp/RUNBOOK-inbox-caixa-unificada-v4.md §4
 * @see Modules/Whatsapp/Http/Controllers/Admin/InboxController.php (deriveQueueFromTags)
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
 * Prefixo `iqt*` (InboxQueueTest) pra evitar colisão com helpers `if*` do
 * InboxFiltersTest (autoload Pest carrega ambos arquivos no mesmo processo).
 */
function iqtSetUserAndGrant(int $businessId, int $userId, array $channelIds): void
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

function iqtMakeChannel(int $businessId, string $uuid): Channel
{
    return Channel::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => $businessId,
        'channel_uuid' => $uuid,
        'label' => 'Suporte',
        'type' => Channel::TYPE_WHATSAPP_BAILEYS,
        'status' => 'active',
    ]);
}

function iqtMakeConv(int $businessId, int $channelId, array $tagSlugs = []): Conversation
{
    $conv = Conversation::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => $businessId,
        'channel_id' => $channelId,
        'customer_external_id' => '+5511' . str_pad((string) random_int(1, 99999999), 8, '0', STR_PAD_LEFT),
        'contact_name' => 'Cliente',
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

function iqtIndexProps(InboxController $controller, Request $request): array
{
    $token = Mockery::mock(\Modules\Whatsapp\Services\Centrifugo\CentrifugoTokenIssuer::class);
    $token->shouldReceive('issue')->andReturn(null);

    $response = $controller->index($request, $token);

    $reflection = new \ReflectionClass($response);
    $propsProp = $reflection->getProperty('props');
    $propsProp->setAccessible(true);
    return $propsProp->getValue($response);
}

function iqtBuildRequest(array $query = []): Request
{
    $qs = $query ? '?' . http_build_query($query) : '';
    $request = Request::create('/atendimento/inbox' . $qs, 'GET');
    $request->setLaravelSession(app('session.store'));
    return $request;
}

function iqtConvData(array $props): array
{
    $convs = $props['conversations'] ?? null;
    // D-14 (PR pós-#871): `conversations` virou Inertia::defer(closure) — invocar pra resolver.
    if ($convs instanceof \Inertia\DeferProp || $convs instanceof \Inertia\OptionalProp) {
        $convs = $convs();
    }
    $data = $convs['data'] ?? [];
    if (is_callable($data)) $data = $data();
    if ($data instanceof \Illuminate\Support\Collection) $data = $data->all();
    return $data;
}

// ============================================================================
// 1. Conv sem tag → fila default
// ============================================================================

it('R-WA-QUEUE-001 — conv sem tag cai na fila default (comercial)', function () {
    $ch = iqtMakeChannel(1, 'queue-001-uuid');
    iqtMakeConv(1, $ch->id, []);  // sem tag
    iqtSetUserAndGrant(1, 10, [$ch->id]);

    $props = iqtIndexProps(new InboxController(), iqtBuildRequest());
    $convs = iqtConvData($props);

    expect($convs)->toHaveCount(1)
        ->and($convs[0]['queue']['slug'])->toBe('comercial')
        ->and($convs[0]['queue']['label'])->toBe('Comercial')
        ->and($convs[0]['queue']['hue'])->toBe(220);
});

// ============================================================================
// 2. Conv com tag financeiro → fila financeiro
// ============================================================================

it('R-WA-QUEUE-002 — tag `financeiro` derive fila financeiro', function () {
    $ch = iqtMakeChannel(1, 'queue-002-uuid');
    iqtMakeConv(1, $ch->id, ['financeiro']);
    iqtSetUserAndGrant(1, 10, [$ch->id]);

    $props = iqtIndexProps(new InboxController(), iqtBuildRequest());
    $convs = iqtConvData($props);

    expect($convs)->toHaveCount(1)
        ->and($convs[0]['queue']['slug'])->toBe('financeiro')
        ->and($convs[0]['queue']['label'])->toBe('Financeiro')
        ->and($convs[0]['queue']['hue'])->toBe(280);
});

// ============================================================================
// 3. Conv com tag cobranca → fila financeiro (trigger alias)
// ============================================================================

it('R-WA-QUEUE-003 — tag `cobranca` também deriva fila financeiro', function () {
    $ch = iqtMakeChannel(1, 'queue-003-uuid');
    iqtMakeConv(1, $ch->id, ['cobranca']);
    iqtSetUserAndGrant(1, 10, [$ch->id]);

    $props = iqtIndexProps(new InboxController(), iqtBuildRequest());
    $convs = iqtConvData($props);

    expect($convs[0]['queue']['slug'])->toBe('financeiro');
});

// ============================================================================
// 4. Conv com tag unrelated → fila default
// ============================================================================

it('R-WA-QUEUE-004 — tag não-trigger mantém default comercial', function () {
    $ch = iqtMakeChannel(1, 'queue-004-uuid');
    iqtMakeConv(1, $ch->id, ['vendas']);  // tag existe mas não é trigger
    iqtSetUserAndGrant(1, 10, [$ch->id]);

    $props = iqtIndexProps(new InboxController(), iqtBuildRequest());
    $convs = iqtConvData($props);

    expect($convs[0]['queue']['slug'])->toBe('comercial');
});

// ============================================================================
// 5. Props `queues` + `defaultQueue` presentes
// ============================================================================

it('R-WA-QUEUE-005 — Controller retorna props queues + defaultQueue', function () {
    $ch = iqtMakeChannel(1, 'queue-005-uuid');
    iqtMakeConv(1, $ch->id, []);
    iqtSetUserAndGrant(1, 10, [$ch->id]);

    $props = iqtIndexProps(new InboxController(), iqtBuildRequest());

    expect($props)->toHaveKey('queues')
        ->and($props)->toHaveKey('defaultQueue')
        ->and($props['queues'])->toHaveKey('comercial')
        ->and($props['queues'])->toHaveKey('financeiro')
        ->and($props['queues']['comercial']['label'])->toBe('Comercial')
        ->and($props['queues']['financeiro']['label'])->toBe('Financeiro')
        ->and($props['defaultQueue'])->toBe('comercial');
});

// ============================================================================
// 6. Cross-tenant Tier 0 ADR 0093 — biz=99 invisível pra biz=1
// ============================================================================

it('R-WA-QUEUE-006 — biz=99 conv com tag financeiro NÃO vaza pra biz=1', function () {
    // biz=99 (tenant outro) cria canal + conv com tag financeiro
    $ch99 = iqtMakeChannel(99, 'queue-006-other-uuid');
    iqtMakeConv(99, $ch99->id, ['financeiro']);

    // biz=1 cria seu próprio canal/conv com tag financeiro
    $ch1 = iqtMakeChannel(1, 'queue-006-self-uuid');
    $myConv = iqtMakeConv(1, $ch1->id, ['financeiro']);
    iqtSetUserAndGrant(1, 10, [$ch1->id]);

    $props = iqtIndexProps(new InboxController(), iqtBuildRequest());
    $convs = iqtConvData($props);

    // Apenas conv biz=1 visível. Tier 0 IRREVOGÁVEL.
    expect($convs)->toHaveCount(1)
        ->and($convs[0]['id'])->toBe($myConv->id)
        ->and($convs[0]['queue']['slug'])->toBe('financeiro');
});

// ============================================================================
// 7. Determinismo — mesmo conjunto de tags gera mesmo resultado
// ============================================================================

it('R-WA-QUEUE-007 — derivação é determinística (ordem trigger preserved)', function () {
    $ch = iqtMakeChannel(1, 'queue-007-uuid');
    iqtMakeConv(1, $ch->id, ['financeiro', 'cobranca']);  // 2 triggers
    iqtSetUserAndGrant(1, 10, [$ch->id]);

    $props1 = iqtIndexProps(new InboxController(), iqtBuildRequest());
    $props2 = iqtIndexProps(new InboxController(), iqtBuildRequest());

    expect(iqtConvData($props1)[0]['queue']['slug'])
        ->toBe(iqtConvData($props2)[0]['queue']['slug'])
        ->toBe('financeiro');
});
