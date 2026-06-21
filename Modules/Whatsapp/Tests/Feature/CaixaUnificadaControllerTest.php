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
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('era-sqlite: schema sintético manual incompatível com MySQL persistente — quarentena Onda 2 SDD floor; burn-down converte depois.');
    }

    // Channel/Conversation/ChannelUserAccess/WhatsappMessage usam Spatie LogsActivity:
    // sem isto, todo ::create tenta `insert into activity_log` (tabela fora do schema
    // sintético) e estoura QueryException. Mesmo pattern das guards Jana já na lane
    // sqlite (AcceptanceRefTest, FsmTransitionGuardTest). O suite não asserta sobre
    // activity_log, então desligar não enfraquece nenhuma checagem.
    config(['activitylog.enabled' => false]);

    foreach (['messages', 'channel_user_access', 'whatsapp_conversation_tags', 'whatsapp_tags', 'conversations', 'channels', 'users', 'whatsapp_templates', 'whatsapp_queues', 'whatsapp_broadcasts', 'contacts', 'transactions', 'transaction_payments'] as $t) {
        Schema::dropIfExists($t);
    }

    // US-WA-306 (ADR 0268) — broadcast fase 1 + contacts mínima (opt-in LGPD)
    Schema::create('whatsapp_broadcasts', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->unsignedBigInteger('channel_id');
        $table->unsignedInteger('created_by_user_id');
        $table->string('kind', 10)->default('freeform');
        $table->string('template_name', 64)->nullable();
        $table->text('body')->nullable();
        $table->string('status', 20)->default('draft');
        $table->json('audience_snapshot');
        $table->json('recipient_conversation_ids');
        $table->timestamp('dispatched_at')->nullable();
        $table->timestamps();
    });
    Schema::create('contacts', function ($table) {
        $table->increments('id');
        $table->unsignedInteger('business_id');
        $table->string('name', 120)->nullable();
        $table->string('mobile', 30)->nullable();
        $table->timestamp('whatsapp_opt_in_at')->nullable();
        $table->softDeletes();
        $table->timestamps();
    });

    // Onda 3 — Saldo + Histórico (transactions UPOS + transaction_payments p/ saldo)
    Schema::create('transactions', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->unsignedInteger('contact_id')->nullable();
        $table->string('type', 20)->default('sell');
        $table->string('status', 20)->default('final');
        $table->string('payment_status', 20)->default('paid');
        $table->decimal('final_total', 22, 4)->default(0);
        $table->timestamps();
    });
    Schema::create('transaction_payments', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedBigInteger('transaction_id');
        $table->decimal('amount', 22, 4)->default(0);
        $table->timestamps();
    });

    // US-WA-301 (ADR 0267) — filas persistidas (espelho da migration 2026_06_10_000001)
    Schema::create('whatsapp_queues', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->string('slug', 40);
        $table->string('label', 80);
        $table->unsignedSmallInteger('hue')->default(220);
        $table->unsignedInteger('sla_minutes')->nullable();
        $table->string('dist', 20)->default('manual');
        $table->json('trigger_tags');
        $table->json('members');
        $table->unsignedInteger('sort_order')->default(0);
        $table->timestamps();
        $table->unique(['business_id', 'slug'], 'wq_biz_slug_uniq');
    });

    // US-WA-303 — templates ready pro picker ⌘T (espelho da migration 2026_05_07_000004)
    Schema::create('whatsapp_templates', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->string('provider', 20)->default('zapi');
        $table->string('meta_template_id', 64)->nullable();
        $table->string('name', 64);
        $table->string('language', 10)->default('pt_BR');
        $table->string('category', 20);
        $table->string('status', 20);
        $table->json('components');
        $table->string('rejection_reason', 255)->nullable();
        $table->timestamp('last_synced_at')->nullable();
        $table->timestamps();
    });

    // US-WA-302 — users mínima pro payload availableAssignees + endpoint assign
    // (pattern BackfillChannelAccessCommandTest + colunas de nome do App\User).
    Schema::create('users', function ($table) {
        $table->increments('id');
        $table->unsignedInteger('business_id');
        $table->string('first_name', 100)->nullable();
        $table->string('surname', 100)->nullable();
        $table->string('last_name', 100)->nullable();
        $table->string('username', 100)->nullable();
        $table->string('email', 100)->nullable();
        $table->string('password')->nullable();
        $table->softDeletes();
        $table->timestamps();
    });

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
        $table->string('queue_override', 40)->nullable();
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
        // Type LIVE real (whatsmeow/WuzAPI, ADR 0204). Baileys foi deletado (ADR 0202) —
        // seedar o type morto mascararia o bug dos chips (PARTE 4 caixa unificada).
        'type' => Channel::TYPE_WHATSAPP_WHATSMEOW,
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
        // Wave 2 F1: o filtro default virou a aba `tab=all` (7 abas substituíram os 4
        // status). Sem `tab`/`status` no request, o controller resolve 'all' — o alias
        // legacy `statusFilter` espelha isso. Antes do Wave 2 o default era 'abertas';
        // a asserção ficou stale porque o suite nunca rodava em CI.
        ->and($props['statusFilter'])->toBe('all');

    // Conversations payload (defer resolve)
    $convs = cuctResolveDefer($props['conversations']);
    expect($convs)->toHaveKey('data')
        ->and($convs['data'])->toHaveCount(1)
        ->and($convs['data'][0]['queue']['slug'])->toBe('financeiro')
        ->and($convs['data'][0]['queue']['label'])->toBe('Financeiro')
        ->and($convs['data'][0]['queue']['hue'])->toBe(280);

    // Channels catalog (7 tipos canônicos — WhatsApp LIVE = whatsmeow, ADR 0204)
    $channels = cuctResolveDefer($props['availableChannels']);
    expect($channels)->toHaveCount(7)
        ->and(collect($channels)->pluck('id')->all())
        ->toContain('whatsapp_whatsmeow', 'whatsapp_meta', 'instagram_dm', 'email_imap', 'mercadolivre')
        ->and(collect($channels)->pluck('id')->all())
        ->not->toContain('whatsapp_baileys'); // Baileys morto (ADR 0202) saiu do catálogo

    // WhatsApp (whatsmeow) deve estar 'ativo' (criamos 1 channel ativo), outros 'em_breve'
    $waChan = collect($channels)->firstWhere('id', 'whatsapp_whatsmeow');
    expect($waChan['status'])->toBe('ativo');
    $metaChan = collect($channels)->firstWhere('id', 'whatsapp_meta');
    expect($metaChan['status'])->toBe('em_breve');
});

// ============================================================================
// 1-bis. PARTE 4 (regressão) — canal WhatsApp LIVE (whatsmeow) vira chip 'ativo'
// ============================================================================

/**
 * R-WA-CAIXA-UNIF-013 — BUG fix PARTE 4: o catálogo de chips definia o WhatsApp como
 * `whatsapp_baileys` (provider morto, ADR 0202), então o Channel ATIVO real
 * (`whatsapp_whatsmeow`, WuzAPI/whatsmeow ADR 0204) nunca casava com nenhuma row e
 * TODOS os chips caíam em 'em_breve' — escondendo justamente o canal de onde as
 * conversas chegam. Após o fix a row é `whatsapp_whatsmeow`: o chip mostra 'ativo' com
 * a contagem real, e o filtro `?channel=whatsapp_whatsmeow` aplica via whereHas type.
 *
 * red-first: contra o catálogo antigo (row whatsapp_baileys) este teste FALHA — o chip
 * whatsmeow inexistente → null no firstWhere; é o discriminador do bug, não tautológico.
 */
it('R-WA-CAIXA-UNIF-013 — canal whatsmeow ativo vira chip ativo com count real (PARTE 4)', function () {
    $ch = cuctMakeChannel(1, 'caixa-unif-013-uuid'); // type whatsmeow, status active
    cuctMakeConv(1, $ch->id, ['vendas']);
    cuctMakeConv(1, $ch->id, ['suporte']);
    cuctSetUserAndGrant(1, 10, [$ch->id]);

    $props = cuctIndexProps(new CaixaUnificadaController(), cuctBuildRequest());
    $channels = cuctResolveDefer($props['availableChannels']);

    // O chip do canal LIVE existe e está 'ativo' com a contagem real (2 convs)
    $wa = collect($channels)->firstWhere('id', 'whatsapp_whatsmeow');
    expect($wa)->not->toBeNull()
        ->and($wa['status'])->toBe('ativo')
        ->and($wa['count'])->toBe(2);

    // A row morta de Baileys não existe mais no catálogo (ADR 0202)
    expect(collect($channels)->pluck('id')->all())->not->toContain('whatsapp_baileys');

    // O `id` da row é o valor do filtro `?channel=` — whereHas channel.type bate certo
    $filtered = cuctResolveDefer(
        cuctIndexProps(new CaixaUnificadaController(), cuctBuildRequest(['channel' => 'whatsapp_whatsmeow']))['conversations']
    );
    expect($filtered['data'])->toHaveCount(2);

    // Filtro por type inexistente devolve vazio (prova que filtra por type, não no-op)
    $none = cuctResolveDefer(
        cuctIndexProps(new CaixaUnificadaController(), cuctBuildRequest(['channel' => 'whatsapp_baileys']))['conversations']
    );
    expect($none['data'])->toHaveCount(0);
});

/**
 * R-WA-CAIXA-UNIF-015 — US-WA-308/309 (incidente 2026-06-18): canal ATIVO com saúde
 * caída entra no payload eager `unhealthyChannels` (banner "religar"), **business-wide**
 * — aparece MESMO sem grant ACL no canal (Wagner 2026-06-18: alerta de queda é pra
 * todos do business). Canal saudável NÃO entra; Tier 0 (ADR 0093) impede vazar canal
 * caído de OUTRO business.
 *
 * red-first: o user NÃO tem grant em canal nenhum e `can()`=false (fake user) → com o
 * filtro ACL antigo o canal caído sumiria (0 rows); business-wide mantém ele. É o
 * discriminador da mudança, não tautológico.
 */
it('R-WA-CAIXA-UNIF-015 — canal caído entra business-wide (sem grant) + saudável fora + Tier 0', function () {
    // biz=1 caído, SEM grant pro user — DEVE aparecer (business-wide)
    $down = cuctMakeChannel(1, 'caixa-unif-015-down');
    $down->forceFill(['channel_health' => 'disconnected', 'last_health_message' => 'whatsmeow disconnected: provision_pending'])->save();

    // biz=1 saudável — NÃO aparece
    $ok = cuctMakeChannel(1, 'caixa-unif-015-ok');
    $ok->forceFill(['channel_health' => 'healthy'])->save();

    // biz=99 caído — Tier 0 impede vazar pra biz=1
    $cross = cuctMakeChannel(99, 'caixa-unif-015-cross');
    $cross->forceFill(['channel_health' => 'disconnected'])->save();

    // user do biz=1 SEM grant em canal nenhum (fake user retorna can()=false)
    cuctSetUserAndGrant(1, 10, []);

    $props = cuctIndexProps(new CaixaUnificadaController(), cuctBuildRequest());
    $unhealthy = cuctResolveDefer($props['unhealthyChannels']); // eager — array direto

    $ids = collect($unhealthy)->pluck('id')->all();
    expect($ids)->toContain($down->id)         // aparece mesmo sem grant (business-wide)
        ->and($ids)->not->toContain($ok->id)    // saudável fora
        ->and($ids)->not->toContain($cross->id); // Tier 0

    $row = collect($unhealthy)->firstWhere('id', $down->id);
    expect($row['channel_health'])->toBe('disconnected')
        ->and($row['label'])->toBe('Suporte');
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

// ============================================================================
// 4. US-WA-302 — assignee picker: payload Tier 0 + endpoint assign
// ============================================================================

/** Cria user row direto na tabela (sem model events — pattern BackfillChannelAccessCommandTest). */
function cuctMakeUserRow(int $businessId, int $id, string $firstName, ?string $lastName = null): void
{
    \Illuminate\Support\Facades\DB::table('users')->insert([
        'id' => $id,
        'business_id' => $businessId,
        'first_name' => $firstName,
        'last_name' => $lastName,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function cuctPatchRequest(string $uri, array $body = []): Request
{
    $request = Request::create($uri, 'PATCH', $body);
    $request->setLaravelSession(app('session.store'));
    return $request;
}

it('R-WA-CAIXA-UNIF-004 — availableAssignees só lista operadores do business atual (Tier 0)', function () {
    $ch = cuctMakeChannel(1, 'caixa-unif-004-uuid');
    cuctMakeConv(1, $ch->id);

    // 2 operadores biz=1 com grant ativo + 1 user biz=99 (cross-tenant) + 1 biz=1 SEM grant
    cuctMakeUserRow(1, 10, 'Maiara', 'Silva');
    cuctMakeUserRow(1, 11, 'Felipe', 'Souza');
    cuctMakeUserRow(99, 90, 'Intruso', 'Tenant');
    cuctMakeUserRow(1, 12, 'Sem', 'Grant');
    cuctSetUserAndGrant(1, 10, [$ch->id]);
    \Modules\Whatsapp\Entities\ChannelUserAccess::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1, 'channel_id' => $ch->id, 'user_id' => 11,
        'granted_by_user_id' => 1, 'granted_at' => now(),
    ]);

    $props = cuctIndexProps(new CaixaUnificadaController(), cuctBuildRequest());
    $assignees = cuctResolveDefer($props['availableAssignees']);

    $ids = collect($assignees)->pluck('id')->all();
    expect($ids)->toContain(10, 11)
        ->and($ids)->not->toContain(90)   // Tier 0 — cross-tenant invisível
        ->and($ids)->not->toContain(12);  // sem grant + sem permission → fora

    $maiara = collect($assignees)->firstWhere('id', 10);
    expect($maiara['name'])->toBe('Maiara Silva');
});

it('R-WA-CAIXA-UNIF-005 — assign atribui/remove operador + bloqueia cross-tenant (Tier 0)', function () {
    $ch = cuctMakeChannel(1, 'caixa-unif-005-uuid');
    $conv = cuctMakeConv(1, $ch->id);

    cuctMakeUserRow(1, 10, 'Maiara', 'Silva');
    cuctMakeUserRow(1, 11, 'Felipe', 'Souza');
    cuctMakeUserRow(99, 90, 'Intruso', 'Tenant');
    cuctSetUserAndGrant(1, 10, [$ch->id]);

    $inbox = new \Modules\Whatsapp\Http\Controllers\Admin\InboxController();

    // Atribui pro user 11 (mesmo business)
    $inbox->assign(cuctPatchRequest("/atendimento/inbox/{$conv->id}/assign", ['assigned_user_id' => 11]), $conv->id);
    expect((int) $conv->fresh()->assigned_user_id)->toBe(11);

    // Thread payload expõe assigned_user_id + nome resolvido
    $props = cuctIndexProps(new CaixaUnificadaController(), cuctBuildRequest(['thread' => $conv->id]));
    expect($props['thread']['assigned_user_id'])->toBe(11)
        ->and($props['thread']['assigned_user_name'])->toBe('Felipe Souza');

    // Cross-tenant assignment → ValidationException fail-loud (Tier 0)
    expect(fn () => $inbox->assign(
        cuctPatchRequest("/atendimento/inbox/{$conv->id}/assign", ['assigned_user_id' => 90]),
        $conv->id,
    ))->toThrow(\Illuminate\Validation\ValidationException::class);
    expect((int) $conv->fresh()->assigned_user_id)->toBe(11); // intacto

    // Remove atribuição (null)
    $inbox->assign(cuctPatchRequest("/atendimento/inbox/{$conv->id}/assign", ['assigned_user_id' => null]), $conv->id);
    expect($conv->fresh()->assigned_user_id)->toBeNull();
});

// ============================================================================
// 5. US-WA-303 — availableTemplates: só ready (LOCAL/APPROVED) + Tier 0
// ============================================================================

function cuctMakeTemplate(int $businessId, string $name, string $provider, string $status, string $body = 'Olá {{nome}}!'): void
{
    \Illuminate\Support\Facades\DB::table('whatsapp_templates')->insert([
        'business_id' => $businessId,
        'provider' => $provider,
        'name' => $name,
        'language' => 'pt_BR',
        'category' => 'UTILITY',
        'status' => $status,
        'components' => json_encode([['type' => 'BODY', 'text' => $body]]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('R-WA-CAIXA-UNIF-006 — availableTemplates só ready (LOCAL/APPROVED) do business atual (Tier 0)', function () {
    $ch = cuctMakeChannel(1, 'caixa-unif-006-uuid');
    cuctSetUserAndGrant(1, 10, [$ch->id]);

    cuctMakeTemplate(1, 'boas_vindas', 'baileys', 'LOCAL', 'Olá {{nome}}, bem-vindo!');
    cuctMakeTemplate(1, 'cobranca_hsm', 'meta_cloud', 'APPROVED');
    cuctMakeTemplate(1, 'pendente_meta', 'meta_cloud', 'PENDING');   // não-ready → fora
    cuctMakeTemplate(1, 'rejeitado', 'meta_cloud', 'REJECTED');      // não-ready → fora
    cuctMakeTemplate(99, 'intruso_tpl', 'baileys', 'LOCAL');         // cross-tenant → fora

    $props = cuctIndexProps(new CaixaUnificadaController(), cuctBuildRequest());
    $templates = cuctResolveDefer($props['availableTemplates']);

    $names = collect($templates)->pluck('name')->all();
    expect($names)->toContain('boas_vindas', 'cobranca_hsm')
        ->and($names)->not->toContain('pendente_meta')
        ->and($names)->not->toContain('rejeitado')
        ->and($names)->not->toContain('intruso_tpl'); // Tier 0 ADR 0093

    // Shape ReadyTemplate do frontend: body cru com placeholders preservados
    $bv = collect($templates)->firstWhere('name', 'boas_vindas');
    expect($bv['body'])->toBe('Olá {{nome}}, bem-vindo!')
        ->and($bv['provider'])->toBe('baileys')
        ->and($bv['status'])->toBe('LOCAL');
});

// ============================================================================
// 6. US-WA-301 (ADR 0267) — filas em DB: seed lazy + leitura DB + Tier 0 + CRUD
// ============================================================================

it('R-WA-CAIXA-UNIF-007 — filas: seed lazy idempotente do config + payload lê DB + Tier 0', function () {
    $ch = cuctMakeChannel(1, 'caixa-unif-007-uuid');
    cuctMakeConv(1, $ch->id, ['financeiro']);
    cuctSetUserAndGrant(1, 10, [$ch->id]);

    // Fila de OUTRO tenant pré-existente — não pode vazar nem inibir o seed
    \Modules\Whatsapp\Entities\WhatsappQueue::withoutGlobalScopes()->create([
        'business_id' => 99, 'slug' => 'intrusa', 'label' => 'Intrusa', 'hue' => 10,
        'trigger_tags' => [], 'members' => [],
    ]);

    // 1ª visita seeda do config (comercial + financeiro)
    $props = cuctIndexProps(new CaixaUnificadaController(), cuctBuildRequest());
    expect(array_keys($props['queues']))->toContain('comercial', 'financeiro')
        ->and(array_keys($props['queues']))->not->toContain('intrusa') // Tier 0
        ->and($props['queues']['financeiro']['hue'])->toBe(280)
        ->and($props['queues']['financeiro']['sla'])->toBe('4h'); // 240min humanizado

    // Idempotente: 2ª visita NÃO duplica
    cuctIndexProps(new CaixaUnificadaController(), cuctBuildRequest());
    $count = \Modules\Whatsapp\Entities\WhatsappQueue::withoutGlobalScopes()
        ->where('business_id', 1)->count();
    expect($count)->toBe(2);

    // Edição no DB reflete no payload (prova que lê DB, não config)
    \Modules\Whatsapp\Entities\WhatsappQueue::withoutGlobalScopes()
        ->where('business_id', 1)->where('slug', 'financeiro')
        ->update(['label' => 'Cobranças VIP', 'hue' => 300]);
    $props2 = cuctIndexProps(new CaixaUnificadaController(), cuctBuildRequest());
    expect($props2['queues']['financeiro']['label'])->toBe('Cobranças VIP')
        ->and($props2['queues']['financeiro']['hue'])->toBe(300);

    // deriveQueueFromTags acompanha o DB (conv com tag financeiro)
    $convs = cuctResolveDefer($props2['conversations']);
    expect($convs['data'][0]['queue']['label'])->toBe('Cobranças VIP');

    // queuesAdmin marca a default e expõe rows completas
    $admin = cuctResolveDefer($props2['queuesAdmin']);
    $comercial = collect($admin)->firstWhere('slug', 'comercial');
    expect($comercial['is_default'])->toBeTrue()
        ->and(collect($admin)->pluck('slug')->all())->not->toContain('intrusa');
});

it('R-WA-CAIXA-UNIF-008 — CRUD filas: store/update/destroy + default protegida + Tier 0', function () {
    $ch = cuctMakeChannel(1, 'caixa-unif-008-uuid');
    cuctSetUserAndGrant(1, 10, [$ch->id]);
    // Seed inicial
    cuctIndexProps(new CaixaUnificadaController(), cuctBuildRequest());

    $queuesCtrl = new \Modules\Whatsapp\Http\Controllers\Admin\QueuesController();

    // STORE — slug auto do label
    $storeReq = Request::create('/atendimento/filas', 'POST', [
        'label' => 'Suporte Técnico', 'hue' => 150, 'sla_minutes' => 90,
        'dist' => 'manual', 'trigger_tags' => ['suporte'],
    ]);
    $storeReq->setLaravelSession(app('session.store'));
    $queuesCtrl->store($storeReq);
    $created = \Modules\Whatsapp\Entities\WhatsappQueue::withoutGlobalScopes()
        ->where('business_id', 1)->where('slug', 'suporte-tecnico')->first();
    expect($created)->not->toBeNull()
        ->and($created->sla_minutes)->toBe(90)
        ->and($created->trigger_tags)->toBe(['suporte']);

    // UPDATE — slug imutável, label/hue mudam
    $updateReq = Request::create("/atendimento/filas/{$created->id}", 'PUT', [
        'label' => 'Suporte N2', 'hue' => 200, 'sla_minutes' => null,
        'dist' => 'round_robin', 'slug' => 'hack-tentativa',
    ]);
    $updateReq->setLaravelSession(app('session.store'));
    $queuesCtrl->update($updateReq, $created->id);
    $fresh = $created->fresh();
    expect($fresh->label)->toBe('Suporte N2')
        ->and($fresh->slug)->toBe('suporte-tecnico') // imutável
        ->and($fresh->dist)->toBe('round_robin');

    // DESTROY default bloqueado (comercial = config default_queue)
    $comercial = \Modules\Whatsapp\Entities\WhatsappQueue::withoutGlobalScopes()
        ->where('business_id', 1)->where('slug', 'comercial')->first();
    $delReq = Request::create("/atendimento/filas/{$comercial->id}", 'DELETE');
    $delReq->setLaravelSession(app('session.store'));
    $queuesCtrl->destroy($delReq, $comercial->id);
    expect(\Modules\Whatsapp\Entities\WhatsappQueue::withoutGlobalScopes()->find($comercial->id))
        ->not->toBeNull(); // ainda existe

    // DESTROY não-default funciona
    $delReq2 = Request::create("/atendimento/filas/{$created->id}", 'DELETE');
    $delReq2->setLaravelSession(app('session.store'));
    $queuesCtrl->destroy($delReq2, $created->id);
    expect(\Modules\Whatsapp\Entities\WhatsappQueue::withoutGlobalScopes()->find($created->id))->toBeNull();

    // Tier 0 — mutar fila de outro tenant = 404 fail-loud
    $alien = \Modules\Whatsapp\Entities\WhatsappQueue::withoutGlobalScopes()->create([
        'business_id' => 99, 'slug' => 'alien', 'label' => 'Alien', 'hue' => 10,
        'trigger_tags' => [], 'members' => [],
    ]);
    $alienReq = Request::create("/atendimento/filas/{$alien->id}", 'DELETE');
    $alienReq->setLaravelSession(app('session.store'));
    expect(fn () => $queuesCtrl->destroy($alienReq, $alien->id))
        ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
});

// ============================================================================
// 7. US-WA-305 — queue_override: mover entre filas vence heurística
// ============================================================================

it('R-WA-CAIXA-UNIF-009 — moveQueue: override vence heurística, null volta, slug inválido 422', function () {
    $ch = cuctMakeChannel(1, 'caixa-unif-009-uuid');
    $conv = cuctMakeConv(1, $ch->id, ['financeiro']); // heurística → fila financeiro
    cuctSetUserAndGrant(1, 10, [$ch->id]);

    $inbox = new \Modules\Whatsapp\Http\Controllers\Admin\InboxController();

    // Baseline: heurística deriva financeiro
    $props = cuctIndexProps(new CaixaUnificadaController(), cuctBuildRequest(['thread' => $conv->id]));
    expect($props['thread']['queue']['slug'])->toBe('financeiro')
        ->and($props['thread']['queue_is_override'])->toBeFalse();

    // Move pra comercial (override manual) — vence a heurística sem re-tagar
    $inbox->moveQueue(cuctPatchRequest("/atendimento/inbox/{$conv->id}/queue", ['queue_slug' => 'comercial']), $conv->id);
    expect($conv->fresh()->queue_override)->toBe('comercial');

    $props2 = cuctIndexProps(new CaixaUnificadaController(), cuctBuildRequest(['thread' => $conv->id]));
    expect($props2['thread']['queue']['slug'])->toBe('comercial')
        ->and($props2['thread']['queue_is_override'])->toBeTrue();

    // Lista também reflete o override
    $convs = cuctResolveDefer($props2['conversations']);
    expect($convs['data'][0]['queue']['slug'])->toBe('comercial');

    // Slug inexistente → 422 fail-loud (Tier 0 — não aceita fila de outro tenant)
    expect(fn () => $inbox->moveQueue(
        cuctPatchRequest("/atendimento/inbox/{$conv->id}/queue", ['queue_slug' => 'fila-fantasma']),
        $conv->id,
    ))->toThrow(\Illuminate\Validation\ValidationException::class);
    expect($conv->fresh()->queue_override)->toBe('comercial'); // intacto

    // null volta pra heurística
    $inbox->moveQueue(cuctPatchRequest("/atendimento/inbox/{$conv->id}/queue", ['queue_slug' => null]), $conv->id);
    expect($conv->fresh()->queue_override)->toBeNull();
    $props3 = cuctIndexProps(new CaixaUnificadaController(), cuctBuildRequest(['thread' => $conv->id]));
    expect($props3['thread']['queue']['slug'])->toBe('financeiro')
        ->and($props3['thread']['queue_is_override'])->toBeFalse();
});

// ============================================================================
// 8. US-WA-307 — + Nova conversa: find-or-create + guards Tier 0
// ============================================================================

function cuctPostRequest(string $uri, array $body = []): Request
{
    $request = Request::create($uri, 'POST', $body);
    $request->setLaravelSession(app('session.store'));
    return $request;
}

it('R-WA-CAIXA-UNIF-010 — startConversation: cria, reabre (não duplica) + guards canal/phone', function () {
    $chActive = cuctMakeChannel(1, 'caixa-unif-010-active-uuid');           // status=active
    $chSetup = cuctMakeChannel(1, 'caixa-unif-010-setup-uuid', 'setup');    // não-ativo
    $chNoAcl = cuctMakeChannel(1, 'caixa-unif-010-noacl-uuid');             // sem grant
    cuctSetUserAndGrant(1, 10, [$chActive->id, $chSetup->id]);

    $inbox = new \Modules\Whatsapp\Http\Controllers\Admin\InboxController();

    // Cria nova conversa — phone normalizado pra +<digits>
    $resp = $inbox->startConversation(cuctPostRequest('/atendimento/inbox/conversations', [
        'channel_id' => $chActive->id,
        'phone' => '+55 (48) 9999-0001',
        'name' => 'Cliente Novo',
    ]));
    $conv = Conversation::withoutGlobalScope(ScopeByBusiness::class)
        ->where('business_id', 1)
        ->where('customer_external_id', '+554899990001')
        ->first();
    expect($conv)->not->toBeNull()
        ->and($conv->contact_name)->toBe('Cliente Novo')
        ->and($conv->status)->toBe('open')
        ->and($resp->getTargetUrl())->toContain('thread=' . $conv->id);

    // Mesmo número de novo → REABRE (não duplica)
    $inbox->startConversation(cuctPostRequest('/atendimento/inbox/conversations', [
        'channel_id' => $chActive->id,
        'phone' => '554899990001',
    ]));
    $count = Conversation::withoutGlobalScope(ScopeByBusiness::class)
        ->where('business_id', 1)
        ->where('customer_external_id', '+554899990001')
        ->count();
    expect($count)->toBe(1);

    // Canal não-ativo → 422
    expect(fn () => $inbox->startConversation(cuctPostRequest('/atendimento/inbox/conversations', [
        'channel_id' => $chSetup->id, 'phone' => '+5548999990002',
    ])))->toThrow(\Illuminate\Validation\ValidationException::class);

    // Canal sem ACL → 403 fail-loud (US-WA-069)
    expect(fn () => $inbox->startConversation(cuctPostRequest('/atendimento/inbox/conversations', [
        'channel_id' => $chNoAcl->id, 'phone' => '+5548999990003',
    ])))->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);

    // Telefone curto → 422
    expect(fn () => $inbox->startConversation(cuctPostRequest('/atendimento/inbox/conversations', [
        'channel_id' => $chActive->id, 'phone' => '123',
    ])))->toThrow(\Illuminate\Validation\ValidationException::class);
});

// ============================================================================
// 9. US-WA-306 (ADR 0268) — broadcast fase 1: pre-flight LGPD/janela + draft
// ============================================================================

it('R-WA-CAIXA-UNIF-011 — broadcast pre-flight: opt-in LGPD + janela 24h + draft auditável', function () {
    $ch = cuctMakeChannel(1, 'caixa-unif-011-uuid');
    cuctSetUserAndGrant(1, 10, [$ch->id]);

    // 4 conversas: opt-in+janela | opt-in+fora-janela | SEM opt-in | bloqueada
    $mk = function (int $contactId, ?string $optIn, int $inboundHoursAgo, bool $blocked = false) use ($ch) {
        \Illuminate\Support\Facades\DB::table('contacts')->insert([
            'id' => $contactId, 'business_id' => 1, 'name' => "C{$contactId}",
            'mobile' => "+554899{$contactId}", 'whatsapp_opt_in_at' => $optIn,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $conv = cuctMakeConv(1, $ch->id);
        $conv->forceFill([
            'contact_id' => $contactId,
            'last_inbound_at' => now()->subHours($inboundHoursAgo),
            'is_blocked' => $blocked,
        ])->save();
        return $conv;
    };
    $inWindow = $mk(101, now()->toDateTimeString(), 2);          // opt-in + janela aberta
    $outWindow = $mk(102, now()->toDateTimeString(), 72);        // opt-in + fora (só HSM)
    $mk(103, null, 1);                                           // SEM opt-in → fora da lista
    $mk(104, now()->toDateTimeString(), 1, true);                // bloqueada → fora de tudo

    $bcast = new \Modules\Whatsapp\Http\Controllers\Admin\BroadcastController();

    $resp = $bcast->preflight(cuctPostRequest('/atendimento/broadcast/preflight', ['channel_id' => $ch->id]));
    $pf = $resp->getData(true);
    expect($pf['total'])->toBe(3)            // bloqueada fora
        ->and($pf['with_opt_in'])->toBe(2)   // 101 + 102 (LGPD)
        ->and($pf['without_opt_in'])->toBe(1)
        ->and($pf['in_window'])->toBe(1)     // só 101
        ->and($pf['hsm_only'])->toBe(1)      // 102
        ->and($pf['recipient_conversation_ids'])->toContain($inWindow->id, $outWindow->id);

    // Draft: snapshot recalculado server-side + status draft (fase 1 — sem disparo)
    $bcast->store(cuctPostRequest('/atendimento/broadcast', [
        'channel_id' => $ch->id, 'kind' => 'freeform', 'body' => 'Promoção da semana!',
    ]));
    $draft = \Modules\Whatsapp\Entities\WhatsappBroadcast::withoutGlobalScopes()
        ->where('business_id', 1)->first();
    expect($draft)->not->toBeNull()
        ->and($draft->status)->toBe('draft')
        ->and($draft->audience_snapshot['with_opt_in'])->toBe(2)
        ->and($draft->recipient_conversation_ids)->toHaveCount(2)
        ->and($draft->dispatched_at)->toBeNull(); // fase 1 NUNCA dispara

    // Canal de outro tenant → 403 fail-loud (Tier 0)
    $chAlien = cuctMakeChannel(99, 'caixa-unif-011-alien-uuid');
    expect(fn () => $bcast->preflight(cuctPostRequest('/atendimento/broadcast/preflight', ['channel_id' => $chAlien->id])))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});

// ============================================================================
// 10. PR-9 brief [CC] — IA na thread: dry_run gateia custo + Tier 0/ACL
// ============================================================================

it('R-WA-CAIXA-UNIF-012 — inbox AI: dry_run devolve fixture sem LLM + ACL canal fail-loud', function () {
    config(['copiloto.dry_run' => true]); // NUNCA toca provider em teste

    $ch = cuctMakeChannel(1, 'caixa-unif-012-uuid');
    $chNoAcl = cuctMakeChannel(1, 'caixa-unif-012-noacl-uuid');
    $conv = cuctMakeConv(1, $ch->id);
    $convNoAcl = cuctMakeConv(1, $chNoAcl->id);
    cuctSetUserAndGrant(1, 10, [$ch->id]);

    \Illuminate\Support\Facades\DB::table('messages')->insert([
        'business_id' => 1, 'conversation_id' => $conv->id, 'direction' => 'inbound',
        'provider' => 'whatsapp_baileys', 'type' => 'text',
        'body' => 'Quero orçamento de 500 cartões de visita pra minha loja',
        'status' => 'received', 'created_at' => now(),
    ]);

    $ai = new \Modules\Whatsapp\Http\Controllers\Admin\InboxAiController();

    // summarize dry-run → fixture (com contagem real de msgs), sem provider
    $resp = $ai->summarize(cuctPostRequest("/atendimento/inbox/{$conv->id}/ai/summarize"), $conv->id);
    expect($resp->getData(true)['text'])->toContain('[dry-run]')
        ->and($resp->getData(true)['text'])->toContain('1 mensagens');

    // ask dry-run ecoa a pergunta na fixture
    $resp2 = $ai->ask(cuctPostRequest("/atendimento/inbox/{$conv->id}/ai/ask", ['question' => 'qual o pedido?']), $conv->id);
    expect($resp2->getData(true)['text'])->toContain('qual o pedido?');

    // suggest-reply dry-run devolve resposta plausível
    $resp3 = $ai->suggestReply(cuctPostRequest("/atendimento/inbox/{$conv->id}/ai/suggest-reply"), $conv->id);
    expect($resp3->getData(true)['text'])->not->toBe('');

    // ACL: conversa de canal sem grant → 403 fail-loud (US-WA-069)
    expect(fn () => $ai->summarize(cuctPostRequest("/atendimento/inbox/{$convNoAcl->id}/ai/summarize"), $convNoAcl->id))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);

    // Tier 0: conversa de outro tenant → 404
    $chAlien = cuctMakeChannel(99, 'caixa-unif-012-alien-uuid');
    $convAlien = cuctMakeConv(99, $chAlien->id);
    expect(fn () => $ai->summarize(cuctPostRequest("/atendimento/inbox/{$convAlien->id}/ai/summarize"), $convAlien->id))
        ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
});

// ============================================================================
// 13. Onda 3 — customerContext (Saldo + Histórico) agregado do contact, Tier 0
// ============================================================================

it('R-WA-CAIXA-UNIF-013 — customerContext agrega Saldo+Histórico do contact (Tier 0) + fallback sem contact', function () {
    $ch = cuctMakeChannel(1, 'caixa-unif-013-uuid');
    cuctSetUserAndGrant(1, 10, [$ch->id]);

    // Contact CRM do business 1
    $contactId = (int) DB::table('contacts')->insertGetId([
        'business_id' => 1, 'name' => 'Cliente A', 'created_at' => now(), 'updated_at' => now(),
    ]);

    // Conversa vinculada ao contact (contact_id)
    $conv = cuctMakeConv(1, $ch->id);
    $conv->forceFill(['contact_id' => $contactId])->save();

    // Vendas do contact (business 1):
    //  - final paga: final 1000 (conta no LTV, sem saldo)
    //  - final due:  final 420, pago 100 → saldo 320
    //  - draft due:  final 9999 → IGNORADA (status='draft' fora de count/ltv/saldo)
    DB::table('transactions')->insert([
        'business_id' => 1, 'contact_id' => $contactId, 'type' => 'sell', 'status' => 'final',
        'payment_status' => 'paid', 'final_total' => 1000, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $tDue = (int) DB::table('transactions')->insertGetId([
        'business_id' => 1, 'contact_id' => $contactId, 'type' => 'sell', 'status' => 'final',
        'payment_status' => 'due', 'final_total' => 420, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('transactions')->insert([
        'business_id' => 1, 'contact_id' => $contactId, 'type' => 'sell', 'status' => 'draft',
        'payment_status' => 'due', 'final_total' => 9999, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('transaction_payments')->insert([
        'transaction_id' => $tDue, 'amount' => 100, 'created_at' => now(), 'updated_at' => now(),
    ]);
    // Tier 0 — venda do business 99 pro MESMO contact_id NÃO pode contar
    DB::table('transactions')->insert([
        'business_id' => 99, 'contact_id' => $contactId, 'type' => 'sell', 'status' => 'final',
        'payment_status' => 'due', 'final_total' => 5000, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $props = cuctIndexProps(new CaixaUnificadaController(), cuctBuildRequest(['thread' => $conv->id]));

    expect($props)->toHaveKey('customerContext');
    $cc = $props['customerContext'];
    expect($cc['linked'])->toBeTrue();
    expect((int) $cc['sells_count'])->toBe(2);          // final paga + final due (draft fora)
    expect((float) $cc['ltv'])->toBe(1420.0);           // 1000 + 420 (status != draft)
    expect((float) $cc['saldo_aberto'])->toBe(320.0);   // due 420 − 100 pago; biz 99 fora (Tier 0)

    // Fallback — conversa sem contact_id vinculado → linked false, zeros
    $conv2 = cuctMakeConv(1, $ch->id);
    $props2 = cuctIndexProps(new CaixaUnificadaController(), cuctBuildRequest(['thread' => $conv2->id]));
    expect($props2['customerContext']['linked'])->toBeFalse();
    expect((int) $props2['customerContext']['sells_count'])->toBe(0);
    expect((float) $props2['customerContext']['saldo_aberto'])->toBe(0.0);
});

// ============================================================================
// 14. Filtro media_inbound_24h — schema novo (`messages`), NÃO legacy
// ============================================================================

/**
 * R-WA-CAIXA-UNIF-014 — o filtro `?media_inbound_24h=1` deve casar mensagens da relação
 * Conversation::messages (tabela `messages` do schema novo polimórfico, ADR 0135).
 *
 * BUG (pré-fix): o controller fazia `->from('whatsapp_messages')` (tabela LEGACY) e
 * juntava `whatsapp_messages.conversation_id = conversations.id`. Como o schema novo grava
 * em `messages`, o join cross-schema nunca casava e o filtro voltava SEMPRE vazio.
 *
 * red-first: contra o código antigo este teste FALHA de forma barulhenta — o schema
 * sintético sequer cria `whatsapp_messages`, então a query estoura "no such table". É o
 * discriminador do bug, não tautológico.
 */
function cuctMakeInboundMsg(int $businessId, int $convId, string $type, ?string $createdAt = null): void
{
    // DB::table direto pra controlar `created_at` (Eloquent reseta pra now()).
    $ts = $createdAt ?? now()->toDateTimeString();
    \DB::table('messages')->insert([
        'business_id' => $businessId,
        'conversation_id' => $convId,
        'direction' => 'inbound',
        'provider' => 'whatsapp_whatsmeow',
        'type' => $type,
        'status' => 'received',
        'created_at' => $ts,
        'updated_at' => $ts,
    ]);
}

it('R-WA-CAIXA-UNIF-014 — media_inbound_24h filtra pela relação messages (schema novo), não whatsapp_messages legacy', function () {
    $ch = cuctMakeChannel(1, 'caixa-unif-014-uuid');
    cuctSetUserAndGrant(1, 10, [$ch->id]);

    // Conv A — mídia inbound recente (< 24h) → DEVE aparecer no filtro
    $convMedia = cuctMakeConv(1, $ch->id);
    cuctMakeInboundMsg(1, $convMedia->id, 'image');

    // Conv B — só texto inbound recente → NÃO aparece (filtra por type de mídia)
    $convText = cuctMakeConv(1, $ch->id);
    cuctMakeInboundMsg(1, $convText->id, 'text');

    // Conv C — mídia inbound, mas há 25h (fora da janela 24h) → NÃO aparece
    $convOld = cuctMakeConv(1, $ch->id);
    cuctMakeInboundMsg(1, $convOld->id, 'audio', now()->subHours(25)->toDateTimeString());

    // Sem filtro: as 3 convs aparecem (controle)
    $all = cuctResolveDefer(
        cuctIndexProps(new CaixaUnificadaController(), cuctBuildRequest())['conversations']
    );
    expect($all['data'])->toHaveCount(3);

    // Com filtro: só a conv com mídia inbound nas últimas 24h
    $filtered = cuctResolveDefer(
        cuctIndexProps(new CaixaUnificadaController(), cuctBuildRequest(['media_inbound_24h' => '1']))['conversations']
    );
    expect($filtered['data'])->toHaveCount(1)
        ->and($filtered['data'][0]['id'])->toBe($convMedia->id);
});
