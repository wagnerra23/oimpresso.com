<?php

declare(strict_types=1);

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Entities\Conversation;
use Modules\Whatsapp\Entities\Message;
use Modules\Whatsapp\Http\Controllers\Admin\InboxController;
use Modules\Whatsapp\Http\Controllers\Api\ChannelBaileysWebhookController;

uses(Tests\TestCase::class);

/**
 * R-WA-076 + R-WA-077 — GUARD tests Inbox cleanup.
 *
 *   076. Filtrar protocol msgs — webhook descarta msgs body=null + type=text
 *        (eventos internos Baileys: senderKeyDistributionMessage,
 *        protocolMessage, app-state-sync) ANTES de criar Conversation/Message.
 *        Evita conv "fantasma" com customer_external_id = phone do próprio
 *        chip + lista de msgs vazias.
 *
 *   077. Identificar atendente — `Message::senderUser` relation +
 *        `InboxController::msgToUiArray` retorna `sender_user_name` resolvido
 *        do first_name/surname/last_name do User que enviou via web UI.
 *        Frontend `MessageBubble` renderiza acima da bubble outbound quando
 *        `sender_kind='human'` E nome set.
 *
 * @see memory/decisions/0135-omnichannel-inbox-arquitetura.md
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('era-sqlite: schema sintético manual incompatível com MySQL persistente — quarentena Onda 2 SDD floor; burn-down converte depois.');
    }

    foreach (['messages', 'conversations', 'channels', 'users'] as $t) {
        Schema::dropIfExists($t);
    }

    Schema::create('users', function ($table) {
        $table->bigIncrements('id');
        $table->string('surname', 10)->nullable();
        $table->string('first_name', 255);
        $table->string('last_name', 255)->nullable();
        $table->string('email', 256)->nullable();
        $table->string('password', 255)->nullable();
        $table->unsignedInteger('business_id')->nullable();
        $table->timestamps();
        $table->softDeletes(); // User model usa SoftDeletes trait
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

it('R-WA-076 — webhook descarta protocol msg (body=null + type=text) com 200 ignored, sem criar Conversation/Message lixo', function () {
    $channel = Channel::query()->create([
        'business_id' => 1,
        'channel_uuid' => 'bbbbbbbb-0000-0000-0000-000000000001',
        'label' => 'Suporte',
        'type' => Channel::TYPE_WHATSAPP_BAILEYS,
        'status' => 'active',
    ]);

    // Payload típico de protocol msg do daemon Baileys: key + remoteJid +
    // message proto vazio (sem `conversation` nem `extendedTextMessage` nem
    // mídia). Falls into `type='text'` default + `body=null`.
    $protocolPayload = [
        'event' => 'message',
        'data' => [
            'key' => [
                'remoteJid' => '+554896486699@s.whatsapp.net',
                'id' => 'PROTOCOL_INTERNAL_EVT_xyz',
                'fromMe' => true,
            ],
            'message' => [
                // Sem `conversation`, sem `extendedTextMessage`, sem mídia.
                // Equivale ao app-state-sync event do Baileys.
            ],
            'push_name' => null,
        ],
    ];

    $controller = app(ChannelBaileysWebhookController::class);
    $req = Request::create('/test', 'POST', $protocolPayload);
    $resp = $controller->handle($req, $channel->channel_uuid);

    expect($resp->getStatusCode())->toBe(200);
    expect($resp->getData(true)['note'])->toBe('protocol_msg_ignored');

    // DB-state — nenhuma row criada (nem Conversation nem Message)
    $convCount = Conversation::query()->withoutGlobalScope(ScopeByBusiness::class)->count();
    $msgCount = Message::query()->withoutGlobalScope(ScopeByBusiness::class)->count();
    expect($convCount)->toBe(0);
    expect($msgCount)->toBe(0);
});

it('R-WA-076 — webhook ACEITA msg de texto real (body present) — filtro nao false-positive em uso normal', function () {
    $channel = Channel::query()->create([
        'business_id' => 1,
        'channel_uuid' => 'bbbbbbbb-0000-0000-0000-000000000002',
        'label' => 'X',
        'type' => Channel::TYPE_WHATSAPP_BAILEYS,
        'status' => 'active',
    ]);

    $realPayload = [
        'event' => 'message',
        'data' => [
            'key' => [
                'remoteJid' => '+554811112222@s.whatsapp.net',
                'id' => 'REAL_MSG_123',
                'fromMe' => false,
            ],
            'message' => ['conversation' => 'Olá, boa tarde'],
            'push_name' => 'Cliente',
        ],
    ];

    $controller = app(ChannelBaileysWebhookController::class);
    $req = Request::create('/test', 'POST', $realPayload);
    $resp = $controller->handle($req, $channel->channel_uuid);

    expect($resp->getStatusCode())->toBe(200);
    expect($resp->getData(true)['note'])->not->toBe('protocol_msg_ignored');
    // Conversation + Message foram criadas (filtro não engoliu msg real)
    $msgCount = Message::query()->withoutGlobalScope(ScopeByBusiness::class)->count();
    expect($msgCount)->toBe(1);
});

it('R-WA-077 — Message->senderUser relation popula User quando sender_user_id set', function () {
    $user = \App\User::query()->create([
        'business_id' => 1,
        'first_name' => 'Wagner',
        'surname' => 'Sr.',
        'last_name' => 'Rocha',
        'email' => 'wagner@oimpresso.com',
        'password' => 'irrelevant_for_test',
    ]);

    $channel = Channel::query()->create([
        'business_id' => 1,
        'channel_uuid' => 'cccccccc-0000-0000-0000-000000000001',
        'label' => 'Suporte',
        'type' => Channel::TYPE_WHATSAPP_BAILEYS,
        'status' => 'active',
    ]);
    $conv = Conversation::query()->create([
        'business_id' => 1, 'channel_id' => $channel->id,
        'customer_external_id' => '+554899999999', 'status' => 'open',
    ]);
    $msgFromAtendente = Message::query()->create([
        'business_id' => 1, 'conversation_id' => $conv->id,
        'direction' => 'outbound', 'provider' => 'whatsapp_baileys',
        'type' => 'text', 'body' => 'Boa tarde, em que posso ajudar?',
        'status' => 'sent',
        'sender_kind' => 'human',
        'sender_user_id' => $user->id,
    ]);

    // Eager-load explícito espelhando InboxController::index — N+1 evitado
    $loaded = Message::query()
        ->withoutGlobalScope(ScopeByBusiness::class)
        ->with('senderUser:id,first_name,surname,last_name')
        ->find($msgFromAtendente->id);

    expect($loaded->senderUser)->not->toBeNull();
    expect($loaded->senderUser->id)->toBe($user->id);
    expect($loaded->senderUser->first_name)->toBe('Wagner');
});

it('R-WA-077 — Message->senderUser e null pra outbound do chip externo (sender_user_id null mesmo com sender_kind=human)', function () {
    $channel = Channel::query()->create([
        'business_id' => 1,
        'channel_uuid' => 'cccccccc-0000-0000-0000-000000000002',
        'label' => 'X',
        'type' => Channel::TYPE_WHATSAPP_BAILEYS,
        'status' => 'active',
    ]);
    $conv = Conversation::query()->create([
        'business_id' => 1, 'channel_id' => $channel->id,
        'customer_external_id' => '+554811112222', 'status' => 'open',
    ]);

    // Mensagem que veio do CHIP (Wagner mandou pelo celular). Tem
    // sender_kind='human' (não bot, não system) MAS sender_user_id=null
    // pq não passou pela web UI. UI deve renderizar bubble outbound sem
    // nome de atendente (msg do chip externo é anônima do ponto de vista
    // do time interno do oimpresso).
    $msgFromChip = Message::query()->create([
        'business_id' => 1, 'conversation_id' => $conv->id,
        'direction' => 'outbound', 'provider' => 'whatsapp_baileys',
        'type' => 'text', 'body' => 'Já te respondi pelo celular',
        'status' => 'sent',
        'sender_kind' => 'human',
        'sender_user_id' => null,
    ]);

    $loaded = Message::query()
        ->withoutGlobalScope(ScopeByBusiness::class)
        ->with('senderUser:id,first_name,surname,last_name')
        ->find($msgFromChip->id);

    expect($loaded->senderUser)->toBeNull();
});

/**
 * R-WA-085 — GUARD tests fix tela branca Atribuir/Ativar bot + optimistic UI send.
 *
 *   001/002. updateStatus toggle `assigned_to_me`/`bot_handling` retorna
 *            RedirectResponse (Inertia 302 back) e atualiza DB. Antes:
 *            ConversationSidebar chamava `whatsapp.conversations.update_status`
 *            legacy do `/atendimento/inbox` → controller legacy redirect pra
 *            URL legacy → tela branca. Fix: prop `updateStatusRouteName` no
 *            componente + `atendimento.inbox.update_status` no novo Inbox.
 *
 *   003/004. send queueia Message com status final em DB ANTES do request
 *            retornar — permite UI mostrar bubble com hourglass/check
 *            imediatamente via polling/Centrifugo. Compose UX otimista.
 */
it('R-WA-085-001 — updateStatus assigned_to_me=true seta assigned_user_id ao current user + retorna RedirectResponse', function () {
    session()->put('user.business_id', 1);
    session()->put('user.id', 42);

    $channel = Channel::query()->create([
        'business_id' => 1,
        'channel_uuid' => 'dddddddd-0000-0000-0000-000000000001',
        'label' => 'X',
        'type' => Channel::TYPE_WHATSAPP_BAILEYS,
        'status' => 'active',
    ]);
    $conv = Conversation::query()->create([
        'business_id' => 1, 'channel_id' => $channel->id,
        'customer_external_id' => '+554899999999', 'status' => 'open',
        'assigned_user_id' => null,
    ]);

    $controller = app(InboxController::class);
    $req = Request::create('/test', 'PATCH', ['assigned_to_me' => true]);
    $resp = $controller->updateStatus($req, $conv->id);

    expect($resp)->toBeInstanceOf(RedirectResponse::class);
    $conv->refresh();
    expect($conv->assigned_user_id)->toBe(42);

    // Toggle off — null
    $req2 = Request::create('/test', 'PATCH', ['assigned_to_me' => false]);
    $controller->updateStatus($req2, $conv->id);
    $conv->refresh();
    expect($conv->assigned_user_id)->toBeNull();
});

it('R-WA-085-002 — updateStatus bot_handling=true atualiza coluna por conversa (not global) + retorna RedirectResponse', function () {
    session()->put('user.business_id', 1);
    session()->put('user.id', 42);

    $channel = Channel::query()->create([
        'business_id' => 1,
        'channel_uuid' => 'dddddddd-0000-0000-0000-000000000002',
        'label' => 'X',
        'type' => Channel::TYPE_WHATSAPP_BAILEYS,
        'status' => 'active',
    ]);
    // 2 conversas no mesmo channel — toggle bot em 1 NÃO afeta a outra
    $convA = Conversation::query()->create([
        'business_id' => 1, 'channel_id' => $channel->id,
        'customer_external_id' => '+554811111111', 'status' => 'open',
        'bot_handling' => false,
    ]);
    $convB = Conversation::query()->create([
        'business_id' => 1, 'channel_id' => $channel->id,
        'customer_external_id' => '+554822222222', 'status' => 'open',
        'bot_handling' => false,
    ]);

    $controller = app(InboxController::class);
    $req = Request::create('/test', 'PATCH', ['bot_handling' => true]);
    $resp = $controller->updateStatus($req, $convA->id);

    expect($resp)->toBeInstanceOf(RedirectResponse::class);
    $convA->refresh();
    $convB->refresh();
    expect((bool) $convA->bot_handling)->toBeTrue();  // ligou só em A
    expect((bool) $convB->bot_handling)->toBeFalse(); // B segue desligado — bot é PER-CONVERSATION
});

it('R-WA-085-003 — send queueia Message com status="sent" + provider_message_id quando daemon retorna 200', function () {
    session()->put('user.business_id', 1);
    session()->put('user.id', 42);

    Http::fake([
        '*/instances/*/text' => Http::response(['status' => 'sent', 'message_id' => 'DAEMON_MSG_XYZ'], 200),
    ]);

    $channel = Channel::query()->create([
        'business_id' => 1,
        'channel_uuid' => 'dddddddd-0000-0000-0000-000000000003',
        'label' => 'X',
        'type' => Channel::TYPE_WHATSAPP_BAILEYS,
        'status' => 'active',
    ]);
    $conv = Conversation::query()->create([
        'business_id' => 1, 'channel_id' => $channel->id,
        'customer_external_id' => '+554899872822', 'status' => 'open',
    ]);

    $controller = app(InboxController::class);
    $req = Request::create('/test', 'POST', [
        'kind' => 'freeform',
        'body' => 'Olá, vou checar seu pedido',
    ]);
    $resp = $controller->send($req, $conv->id);

    expect($resp)->toBeInstanceOf(RedirectResponse::class);

    $msg = Message::query()
        ->withoutGlobalScope(ScopeByBusiness::class)
        ->where('conversation_id', $conv->id)
        ->first();
    expect($msg)->not->toBeNull();
    expect($msg->body)->toBe('Olá, vou checar seu pedido');
    expect($msg->direction)->toBe('outbound');
    expect($msg->sender_kind)->toBe('human');
    expect($msg->sender_user_id)->toBe(42);
    expect($msg->status)->toBe('sent');
    expect($msg->provider_message_id)->toBe('DAEMON_MSG_XYZ');
});

it('R-WA-085-004 — send com daemon falha mantem Message em DB com status="failed" + reason (nao bloqueia retry)', function () {
    session()->put('user.business_id', 1);
    session()->put('user.id', 42);

    Http::fake([
        '*/instances/*/text' => Http::response(['error' => 'instance_disconnected'], 503),
    ]);

    $channel = Channel::query()->create([
        'business_id' => 1,
        'channel_uuid' => 'dddddddd-0000-0000-0000-000000000004',
        'label' => 'X',
        'type' => Channel::TYPE_WHATSAPP_BAILEYS,
        'status' => 'active',
    ]);
    $conv = Conversation::query()->create([
        'business_id' => 1, 'channel_id' => $channel->id,
        'customer_external_id' => '+554899872822', 'status' => 'open',
    ]);

    $controller = app(InboxController::class);
    $req = Request::create('/test', 'POST', [
        'kind' => 'freeform',
        'body' => 'msg que vai falhar',
    ]);
    $resp = $controller->send($req, $conv->id);

    expect($resp)->toBeInstanceOf(RedirectResponse::class);

    // Row em DB com status='failed' — frontend bubble mostra ícone vermelho
    // + atendente pode retentar (não trava UI). Optimistic UI premissa
    // mantida mesmo no caminho de erro.
    $msg = Message::query()
        ->withoutGlobalScope(ScopeByBusiness::class)
        ->where('conversation_id', $conv->id)
        ->first();
    expect($msg)->not->toBeNull();
    expect($msg->status)->toBe('failed');
    expect($msg->failed_reason)->toContain('503');
});
