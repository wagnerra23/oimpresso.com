<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Entities\ChannelUserAccess;
use Modules\Whatsapp\Entities\Conversation;
use Modules\Whatsapp\Entities\Message;
use Modules\Whatsapp\Entities\WhatsappBusinessPhone;
use Modules\Whatsapp\Http\Controllers\Admin\InboxController;
use Modules\Whatsapp\Jobs\SendInteractiveJob;

uses(Tests\TestCase::class);

/**
 * R-WA-INTERACTIVE — endpoint POST /atendimento/inbox/conversations/{id}/send-interactive (US-WA-045b).
 *
 * Cobre:
 *  1. payload válido `type=buttons` em canal Baileys → daemon chamado, Message=interactive queued
 *  2. validação 4 botões (excedeu max=3) → erro de validation (não passa pelo Controller)
 *  3. cross-tenant biz=99 conversation_id → 404 (global scope mata)
 *  4. user sem ACL canal → 403 abort
 *  5. type=cta_url em canal Baileys → erro validation/back errors (Meta only)
 *
 * Tier 0 IRREVOGÁVEL (ADR 0093): todas as mutações DEPOIS do business_id scope.
 * Pattern espelha InboxFiltersTest (User stub + reflection — sem render Inertia).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('era-sqlite: schema sintético manual incompatível com MySQL persistente — quarentena Onda 2 SDD floor; burn-down converte depois.');
    }

    foreach (['messages', 'channel_user_access', 'conversations', 'channels', 'whatsapp_business_phones'] as $t) {
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
        $table->string('media_url', 500)->nullable();
        $table->string('media_mime', 80)->nullable();
        $table->unsignedBigInteger('media_size_bytes')->nullable();
        $table->unsignedInteger('media_duration_s')->nullable();
        $table->string('media_thumbnail_url', 500)->nullable();
        $table->text('media_transcription')->nullable();
        $table->string('media_filename', 255)->nullable();
        $table->timestamp('created_at')->useCurrent();
        $table->timestamp('updated_at')->nullable();
    });

    Schema::create('whatsapp_business_phones', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->uuid('phone_uuid')->unique();
        $table->string('label', 80);
        $table->string('driver', 20)->default('zapi');
        $table->string('fallback_driver', 20)->default('meta_cloud');
        $table->timestamps();
    });
});

function iiSetUser(int $businessId, int $userId, array $channelIds = [], bool $admin = false): void
{
    $stub = new class extends \Illuminate\Foundation\Auth\User {
        protected $table = 'users';
        protected $guarded = [];
        public bool $_admin = false;
        public function can($abilities, $arguments = []): bool
        {
            // Admin bypassa whatsapp.view-all-phones (US-WA-069).
            if ($abilities === 'whatsapp.view-all-phones') {
                return $this->_admin;
            }
            return true; // Demais permissões liberadas em test
        }
    };
    $stub->id = $userId;
    $stub->business_id = $businessId;
    $stub->_admin = $admin;
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

function iiMakeChannel(int $businessId, string $uuid, string $type = Channel::TYPE_WHATSAPP_BAILEYS): Channel
{
    return Channel::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => $businessId,
        'channel_uuid' => $uuid,
        'label' => 'Suporte',
        'type' => $type,
        'status' => 'active',
    ]);
}

function iiMakeConv(int $businessId, int $channelId): Conversation
{
    return Conversation::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => $businessId,
        'channel_id' => $channelId,
        'customer_external_id' => '+5511987654321',
        'contact_name' => 'Cliente Teste',
        'status' => 'open',
        'last_message_at' => now(),
    ]);
}

function iiRequest(array $body): Request
{
    $request = Request::create('/atendimento/inbox/conversations/1/send-interactive', 'POST', $body);
    $request->setLaravelSession(app('session.store'));

    return $request;
}

// ============================================================================
// 1 — payload válido buttons, canal Baileys → daemon chamado + Message queued
// ============================================================================

it('R-WA-INTERACTIVE-001 — payload buttons válido em canal Baileys persiste Message + chama daemon', function () {
    Http::fake([
        '*/instances/*/interactive' => Http::response(['status' => 'sent', 'message_id' => 'prov_abc'], 200),
    ]);

    $ch = iiMakeChannel(1, 'int-001-uuid');
    $conv = iiMakeConv(1, $ch->id);

    iiSetUser(1, 10, [$ch->id]);

    $controller = new InboxController();
    $response = $controller->sendInteractive(
        iiRequest([
            'body' => 'Confirma o agendamento?',
            'type' => 'buttons',
            'buttons' => [
                ['id' => 'sim', 'label' => 'Sim'],
                ['id' => 'nao', 'label' => 'Não'],
            ],
        ]),
        $conv->id,
    );

    expect($response)->toBeInstanceOf(\Illuminate\Http\RedirectResponse::class);

    $msg = Message::withoutGlobalScope(ScopeByBusiness::class)
        ->where('conversation_id', $conv->id)
        ->first();

    expect($msg)->not->toBeNull()
        ->and($msg->type)->toBe('interactive')
        ->and($msg->body)->toBe('Confirma o agendamento?')
        ->and($msg->status)->toBe('sent') // resposta mock do daemon
        ->and($msg->provider_message_id)->toBe('prov_abc');

    $payload = is_array($msg->payload) ? $msg->payload : json_decode($msg->payload, true);
    expect($payload['type'])->toBe('buttons')
        ->and($payload['buttons'])->toHaveCount(2);

    Http::assertSent(function (\Illuminate\Http\Client\Request $req) {
        return str_contains($req->url(), '/interactive')
            && $req['interactive']['type'] === 'buttons';
    });
});

// ============================================================================
// 2 — 4 botões viola max:3 → ValidationException (não persiste, não chama daemon)
// ============================================================================

it('R-WA-INTERACTIVE-002 — 4 botões viola max:3 → ValidationException', function () {
    Http::fake(); // se chamar daemon, falha

    $ch = iiMakeChannel(1, 'int-002-uuid');
    $conv = iiMakeConv(1, $ch->id);

    iiSetUser(1, 10, [$ch->id]);

    $controller = new InboxController();

    expect(fn () => $controller->sendInteractive(
        iiRequest([
            'body' => 'Escolha:',
            'type' => 'buttons',
            'buttons' => [
                ['id' => 'a', 'label' => 'A'],
                ['id' => 'b', 'label' => 'B'],
                ['id' => 'c', 'label' => 'C'],
                ['id' => 'd', 'label' => 'D'], // 4º — viola max:3
            ],
        ]),
        $conv->id,
    ))->toThrow(\Illuminate\Validation\ValidationException::class);

    // Defense-in-depth: nenhuma message criada, daemon nunca chamado.
    expect(Message::withoutGlobalScope(ScopeByBusiness::class)->count())->toBe(0);
    Http::assertNothingSent();
});

// ============================================================================
// 3 — cross-tenant biz=99 → 404 (global scope mata findOrFail)
// ============================================================================

it('R-WA-INTERACTIVE-003 — conversation de outro business retorna 404', function () {
    Http::fake();

    // Conv pertence biz=99
    $ch99 = iiMakeChannel(99, 'int-003-uuid');
    $conv99 = iiMakeConv(99, $ch99->id);

    // User logado em biz=1
    iiSetUser(1, 10, []);

    $controller = new InboxController();

    expect(fn () => $controller->sendInteractive(
        iiRequest([
            'body' => 'oi',
            'type' => 'buttons',
            'buttons' => [['id' => 'sim', 'label' => 'Sim']],
        ]),
        $conv99->id,
    ))->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

    expect(Message::withoutGlobalScope(ScopeByBusiness::class)->count())->toBe(0);
});

// ============================================================================
// 4 — user sem ACL canal → 403 abort
// ============================================================================

it('R-WA-INTERACTIVE-004 — user sem ACL canal recebe 403 abort', function () {
    Http::fake();

    $ch = iiMakeChannel(1, 'int-004-uuid');
    $conv = iiMakeConv(1, $ch->id);

    // User sem grant em $ch->id e SEM admin bypass
    iiSetUser(1, 10, [], admin: false);

    $controller = new InboxController();

    expect(fn () => $controller->sendInteractive(
        iiRequest([
            'body' => 'oi',
            'type' => 'buttons',
            'buttons' => [['id' => 'sim', 'label' => 'Sim']],
        ]),
        $conv->id,
    ))->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);

    expect(Message::withoutGlobalScope(ScopeByBusiness::class)->count())->toBe(0);
});

// ============================================================================
// 5 — type=cta_url em canal Baileys → rejeitado (Meta Cloud only)
// ============================================================================

it('R-WA-INTERACTIVE-005 — type=cta_url em canal Baileys retorna erro (Meta only)', function () {
    Http::fake();

    $ch = iiMakeChannel(1, 'int-005-uuid', Channel::TYPE_WHATSAPP_BAILEYS);
    $conv = iiMakeConv(1, $ch->id);

    iiSetUser(1, 10, [$ch->id]);

    $controller = new InboxController();
    $response = $controller->sendInteractive(
        iiRequest([
            'body' => 'Acesse o link:',
            'type' => 'cta_url',
            'cta_label' => 'Acessar',
            'cta_url' => 'https://exemplo.com/promo',
        ]),
        $conv->id,
    );

    // Controller retorna RedirectResponse com errors (não throw — UX flash error)
    expect($response)->toBeInstanceOf(\Illuminate\Http\RedirectResponse::class);

    $errors = optional($response->getSession())->get('errors');
    expect($errors)->not->toBeNull()
        ->and($errors->has('send_interactive'))->toBeTrue();

    // Daemon não pode ter sido chamado — guard ANTES do dispatch
    Http::assertNothingSent();
    expect(Message::withoutGlobalScope(ScopeByBusiness::class)->count())->toBe(0);
});

// ============================================================================
// 6 — payload list com >10 itens total → erro back (regra agregada)
// ============================================================================

it('R-WA-INTERACTIVE-006 — list com 11 itens total viola max=10 → erro back', function () {
    Http::fake();

    $ch = iiMakeChannel(1, 'int-006-uuid');
    $conv = iiMakeConv(1, $ch->id);

    iiSetUser(1, 10, [$ch->id]);

    // 2 sections × 6 items = 12 itens (viola 10 max)
    $sections = [
        ['title' => 'A', 'items' => array_map(fn ($i) => ['id' => "a{$i}", 'title' => "A{$i}"], range(1, 6))],
        ['title' => 'B', 'items' => array_map(fn ($i) => ['id' => "b{$i}", 'title' => "B{$i}"], range(1, 6))],
    ];

    $controller = new InboxController();
    $response = $controller->sendInteractive(
        iiRequest([
            'body' => 'Escolha o tamanho:',
            'type' => 'list',
            'button_label' => 'Ver',
            'sections' => $sections,
        ]),
        $conv->id,
    );

    expect($response)->toBeInstanceOf(\Illuminate\Http\RedirectResponse::class);
    $errors = optional($response->getSession())->get('errors');
    expect($errors)->not->toBeNull()
        ->and($errors->has('send_interactive'))->toBeTrue();
    Http::assertNothingSent();
});

// ============================================================================
// 7 — payload list válido em canal Meta dispatcha SendInteractiveJob (fila)
// ============================================================================

it('R-WA-INTERACTIVE-007 — list válido em canal Meta dispatcha SendInteractiveJob', function () {
    Bus::fake();

    $ch = iiMakeChannel(1, 'int-007-uuid', Channel::TYPE_WHATSAPP_META);
    $conv = iiMakeConv(1, $ch->id);

    // Phone legacy meta_cloud associado ao business — controller usa pro Job
    WhatsappBusinessPhone::withoutGlobalScope(ScopeByBusiness::class)->forceCreate([
        'id' => 77,
        'business_id' => 1,
        'phone_uuid' => \Illuminate\Support\Str::uuid()->toString(),
        'label' => 'Meta Comercial',
        'driver' => 'meta_cloud',
        'fallback_driver' => 'meta_cloud',
    ]);

    iiSetUser(1, 10, [$ch->id]);

    $controller = new InboxController();
    $response = $controller->sendInteractive(
        iiRequest([
            'body' => 'Qual tamanho?',
            'type' => 'list',
            'button_label' => 'Ver tamanhos',
            'sections' => [
                ['title' => 'Tamanhos', 'items' => [
                    ['id' => 'p', 'title' => 'P'],
                    ['id' => 'm', 'title' => 'M'],
                    ['id' => 'g', 'title' => 'G'],
                ]],
            ],
        ]),
        $conv->id,
    );

    expect($response)->toBeInstanceOf(\Illuminate\Http\RedirectResponse::class);

    Bus::assertDispatched(SendInteractiveJob::class, function (SendInteractiveJob $job) {
        return $job->businessId === 1
            && $job->whatsappBusinessPhoneId === 77
            && $job->interactive['type'] === 'list'
            && count($job->interactive['sections']) === 1;
    });

    $msg = Message::withoutGlobalScope(ScopeByBusiness::class)
        ->where('conversation_id', $conv->id)
        ->first();
    expect($msg)->not->toBeNull()
        ->and($msg->type)->toBe('interactive')
        ->and($msg->status)->toBe('queued'); // ainda não confirmado (Job)
});
