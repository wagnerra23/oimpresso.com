<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Entities\ChannelUserAccess;
use Modules\Whatsapp\Entities\Conversation;
use Modules\Whatsapp\Entities\Message;
use Modules\Whatsapp\Http\Controllers\Admin\InboxController;

uses(Tests\TestCase::class);

/**
 * US-WA-069 — Validar canal=fila (Suporte não vê inbox do Financeiro).
 *
 * GUARD tests Tier 0 IRREVOGÁVEL (ADR 0093 + ADR 0135):
 *
 *   1. 2 canais mesma biz, 2 users com acessos disjuntos — vê só conv do canal permitido
 *   2. User sem nenhum acesso → inbox vazia (não 500)
 *   3. Superadmin bypass (Gate `whatsapp.view-all-phones`) vê tudo
 *   4. Cross-tenant rígido: biz=99 invisível mesmo com ACL bugada
 *   5. Acesso revogado (revoked_at != null) deixa de contar
 *   6. send() em canal sem acesso → 403
 *   7. send() em canal COM acesso → 200 controle positivo
 *   8. Re-grant após revoke restaura acesso
 *
 * Pattern segue ChannelUserAccessTest (PR #644): User stub não-persistido +
 * auth()->setUser + session.business_id + forgetInstance ScopeByBusiness.
 *
 * @see memory/decisions/0135-omnichannel-inbox-arquitetura.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-069
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('era-sqlite: schema sintético manual incompatível com MySQL persistente — quarentena Onda 2 SDD floor; burn-down converte depois.');
    }

    foreach (['messages', 'channel_user_access', 'whatsapp_conversation_tags', 'whatsapp_tags', 'conversations', 'channels'] as $t) {
        Schema::dropIfExists($t);
    }

    // whatsapp_tags + pivot — controller faz ensureDefaultTags() + eager-load tags
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
 * Configura sessão multi-tenant pro User+business correntes.
 * Mesmo pattern de ChannelUserAccessTest — user stub sem can() (Gate
 * `whatsapp.view-all-phones` retorna false por default).
 */
function cfiSetUser(int $businessId, int $userId, bool $canSeeAll = false): void
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

function cfiMakeChannel(int $businessId, string $label, string $uuid): Channel
{
    return Channel::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => $businessId,
        'channel_uuid' => $uuid,
        'label' => $label,
        'type' => Channel::TYPE_WHATSAPP_BAILEYS,
        'status' => 'active',
    ]);
}

function cfiMakeConv(int $businessId, int $channelId, string $phone, string $name): Conversation
{
    return Conversation::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => $businessId,
        'channel_id' => $channelId,
        'customer_external_id' => $phone,
        'contact_name' => $name,
        'status' => 'open',
        'last_message_at' => now(),
    ]);
}

function cfiGrant(int $businessId, int $channelId, int $userId): void
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
 * Invoca index() e retorna IDs das conversas que ele incluiu na resposta Inertia.
 *
 * Lê `props` direto via reflection — Inertia\Response::toResponse() ia tentar
 * renderizar view real e falhar em ambiente Pest (view cache path). Reflection
 * pega o array de props ANTES da serialização HTTP.
 */
function cfiIndexConvIds(InboxController $controller, Request $request): array
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

    return collect($convs)->pluck('id')->map(fn ($id) => (int) $id)->all();
}

function cfiBuildRequest(): Request
{
    $request = Request::create('/atendimento/inbox', 'GET');
    $request->setLaravelSession(app('session.store'));
    return $request;
}

it('R-WA-069-001 — 2 users com acessos disjuntos veem APENAS conversas do canal permitido', function () {
    $chSuporte = cfiMakeChannel(1, 'Suporte', 'wa069-suporte-1');
    $chFinanc  = cfiMakeChannel(1, 'Financeiro', 'wa069-financ-1');

    $c1 = cfiMakeConv(1, $chSuporte->id, '+5511900000001', 'Cliente A');
    $c2 = cfiMakeConv(1, $chFinanc->id,  '+5511900000002', 'Cliente B');

    cfiGrant(1, $chSuporte->id, 10);
    cfiGrant(1, $chFinanc->id,  11);

    // user_suporte → só vê C1
    cfiSetUser(1, 10);
    $idsSuporte = cfiIndexConvIds(new InboxController(), cfiBuildRequest());
    expect($idsSuporte)->toEqual([$c1->id]);

    // user_financeiro → só vê C2
    cfiSetUser(1, 11);
    $idsFinanc = cfiIndexConvIds(new InboxController(), cfiBuildRequest());
    expect($idsFinanc)->toEqual([$c2->id]);
});

it('R-WA-069-002 — User sem ACL em nenhum canal recebe inbox vazia (não 500)', function () {
    $ch = cfiMakeChannel(1, 'Vendas', 'wa069-vendas-empty');
    cfiMakeConv(1, $ch->id, '+5511900000099', 'Visível só pra quem tem ACL');

    cfiSetUser(1, 42); // user sem nenhum grant

    $ids = cfiIndexConvIds(new InboxController(), cfiBuildRequest());
    expect($ids)->toBe([]);
});

it('R-WA-069-003 — Superadmin Gate whatsapp.view-all-phones bypassa filtro per-canal', function () {
    $chA = cfiMakeChannel(1, 'Canal A', 'wa069-bypass-a');
    $chB = cfiMakeChannel(1, 'Canal B', 'wa069-bypass-b');
    $ca = cfiMakeConv(1, $chA->id, '+5511800000001', 'Conv A');
    $cb = cfiMakeConv(1, $chB->id, '+5511800000002', 'Conv B');

    // User admin sem nenhum grant explícito mas com Gate true
    cfiSetUser(1, 99, canSeeAll: true);

    $ids = cfiIndexConvIds(new InboxController(), cfiBuildRequest());
    expect(count($ids))->toBe(2);
    expect($ids)->toContain($ca->id);
    expect($ids)->toContain($cb->id);
});

it('R-WA-069-004 — Cross-tenant rígido: biz=1 nunca vê conv de biz=99 mesmo com ACL bugada', function () {
    $ch1  = cfiMakeChannel(1,  'Canal biz=1',  'wa069-cross-1');
    $ch99 = cfiMakeChannel(99, 'Canal biz=99', 'wa069-cross-99');
    cfiMakeConv(1,  $ch1->id,  '+5511700000001', 'Conv biz=1');
    $c99 = cfiMakeConv(99, $ch99->id, '+5511700000099', 'Conv biz=99 — JAMAIS vazar');

    // ACL bugada: row pra user de biz=1 apontando pra channel_id de biz=99
    // (cenário paranoico — não deveria existir, mas se existir global scope
    // protege).
    ChannelUserAccess::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1, // ACL row marcada biz=1 mesmo apontando ch99
        'channel_id' => $ch99->id,
        'user_id' => 50,
        'granted_by_user_id' => 1,
        'granted_at' => now(),
    ]);

    cfiSetUser(1, 50);
    $ids = cfiIndexConvIds(new InboxController(), cfiBuildRequest());

    // Global scope business_id IRREVOGÁVEL bloqueia c99 mesmo com ACL bugada
    expect($ids)->not->toContain($c99->id);
});

it('R-WA-069-005 — Acesso revogado (revoked_at != null) NÃO conta como acesso ativo', function () {
    $ch = cfiMakeChannel(1, 'Suporte revogado', 'wa069-revoked');
    $conv = cfiMakeConv(1, $ch->id, '+5511600000001', 'Cliente');

    // Grant + revoke imediato
    ChannelUserAccess::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'channel_id' => $ch->id,
        'user_id' => 20,
        'granted_by_user_id' => 1,
        'granted_at' => now()->subDay(),
        'revoked_at' => now()->subHour(),
        'revoked_by_user_id' => 1,
    ]);

    cfiSetUser(1, 20);
    $ids = cfiIndexConvIds(new InboxController(), cfiBuildRequest());

    expect($ids)->toBe([]); // revoked → não vê
});

it('R-WA-069-006 — send() em canal SEM acesso → 403 Forbidden (defense-in-depth)', function () {
    Http::fake(); // se chegar a chamar daemon → vai falhar este teste indireto
    config([
        'whatsapp.baileys.daemon_url' => 'https://daemon.test',
        'whatsapp.baileys.api_key' => 'test-key-min16chars',
    ]);

    $ch = cfiMakeChannel(1, 'Financeiro', 'wa069-send-no-access');
    $conv = cfiMakeConv(1, $ch->id, '+5511500000001', 'Cliente Financeiro');

    // User SEM grant pra este canal
    cfiSetUser(1, 30);

    $request = Request::create('', 'POST', [
        'kind' => 'freeform',
        'body' => 'tentativa de envio sem ACL',
    ]);
    $request->setLaravelSession(app('session.store'));

    $controller = new InboxController();

    expect(fn () => $controller->send($request, $conv->id))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);

    // Nenhuma Message persistida (abort ANTES do create)
    expect(Message::withoutGlobalScope(ScopeByBusiness::class)->count())->toBe(0);

    // Daemon nunca chamado (defense-in-depth)
    Http::assertNothingSent();
});

it('R-WA-069-007 — send() em canal COM acesso → 200 sucesso (controle positivo)', function () {
    Http::fake([
        '*' => Http::response(['status' => 'sent', 'message_id' => 'wamid.xyz'], 200),
    ]);
    config([
        'whatsapp.baileys.daemon_url' => 'https://daemon.test',
        'whatsapp.baileys.api_key' => 'test-key-min16chars',
    ]);

    $ch = cfiMakeChannel(1, 'Suporte', 'wa069-send-with-access');
    $conv = cfiMakeConv(1, $ch->id, '+5511500000002', 'Cliente Suporte');

    cfiGrant(1, $ch->id, 31);
    cfiSetUser(1, 31);

    $request = Request::create('', 'POST', [
        'kind' => 'freeform',
        'body' => 'mensagem legítima',
    ]);
    $request->setLaravelSession(app('session.store'));

    $controller = new InboxController();
    $response = $controller->send($request, $conv->id);

    // Message persistida com sucesso (não failed)
    $msg = Message::withoutGlobalScope(ScopeByBusiness::class)
        ->where('conversation_id', $conv->id)->first();
    expect($msg)->not->toBeNull();
    expect($msg->status)->toBe('sent');

    // Daemon foi chamado (controle positivo)
    Http::assertSent(fn ($req) => str_contains($req->url(), '/instances/'));
});

it('R-WA-069-008 — Re-grant após revoke restaura acesso (UNIQUE permite N rows)', function () {
    $ch = cfiMakeChannel(1, 'Suporte', 'wa069-regrant');
    $conv = cfiMakeConv(1, $ch->id, '+5511400000001', 'Cliente Regrant');

    // 1. Grant ativo
    cfiGrant(1, $ch->id, 40);
    cfiSetUser(1, 40);
    expect(cfiIndexConvIds(new InboxController(), cfiBuildRequest()))->toEqual([$conv->id]);

    // 2. Revogar
    $access = ChannelUserAccess::withoutGlobalScope(ScopeByBusiness::class)
        ->where('user_id', 40)->where('channel_id', $ch->id)->first();
    $access->revoked_at = now();
    $access->revoked_by_user_id = 1;
    $access->save();

    cfiSetUser(1, 40); // re-init session/auth
    expect(cfiIndexConvIds(new InboxController(), cfiBuildRequest()))->toBe([]);

    // 3. Re-grant — nova row (revoked_at=NULL coexiste com a histórica)
    cfiGrant(1, $ch->id, 40);

    cfiSetUser(1, 40);
    expect(cfiIndexConvIds(new InboxController(), cfiBuildRequest()))->toEqual([$conv->id]);

    // Histórico preservado — 2 rows (1 revoked + 1 ativa)
    $rows = ChannelUserAccess::withoutGlobalScope(ScopeByBusiness::class)
        ->where('user_id', 40)->where('channel_id', $ch->id)->count();
    expect($rows)->toBe(2);
});
