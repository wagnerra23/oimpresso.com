<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Entities\Conversation;
use Modules\Whatsapp\Entities\Message;
use Modules\Whatsapp\Http\Controllers\Admin\InboxController;
use Modules\Whatsapp\Observers\MessageObserver;

uses(Tests\TestCase::class);

/**
 * R-WA-072 — GUARD tests pra denormalize `last_message_preview` +
 * `last_message_direction` em conversations (US-WA-072).
 *
 * Contexto: `InboxController::convToListArray()` chamava
 * `$c->messages()->reorder()->first()` 50× por page load (paginate 50)
 * → N+1 query. Solução: denormalize 2 colunas, mantidas pelo
 * `MessageObserver::created()`.
 *
 * Cobre:
 *  001. MessageObserver::created atualiza preview/direction ao criar Message
 *  002. 2ª msg sobrescreve preview (não acumula histórico — sempre o último)
 *  003. body=null não quebra (preview fica null — mídia sem caption)
 *  004. convToListArray retorna preview sem disparar query em messages
 *  005. Tier 0 (ADR 0093) — preview de biz=99 não vaza pra biz=1
 *
 * @see memory/decisions/0135-omnichannel-inbox-arquitetura.md
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('era-sqlite: schema sintético manual incompatível com MySQL persistente — quarentena Onda 2 SDD floor; burn-down converte depois.');
    }

    foreach (['messages', 'conversations', 'channels'] as $t) {
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
        // US-WA-072 — denormalizados
        $table->string('last_message_preview', 120)->nullable();
        $table->string('last_message_direction', 10)->nullable();
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

    // Observer roda mesmo sem ServiceProvider boot completo (testbench
    // não chama boot() de todos os providers). Garante que MessageObserver
    // dispara `created()` ao salvar Message::create().
    Message::observe(MessageObserver::class);
});

it('R-WA-072-001 — MessageObserver atualiza last_message_preview/direction ao create', function () {
    session()->put('user.business_id', 1);

    $channel = Channel::query()->create([
        'business_id' => 1,
        'channel_uuid' => 'aaaaaaaa-0000-0000-0000-000000000001',
        'label' => 'X',
        'type' => Channel::TYPE_WHATSAPP_BAILEYS,
        'status' => 'active',
    ]);
    $conv = Conversation::query()->create([
        'business_id' => 1, 'channel_id' => $channel->id,
        'customer_external_id' => '+554811111111', 'status' => 'open',
    ]);

    // Sanity: preview começa null
    expect($conv->last_message_preview)->toBeNull();
    expect($conv->last_message_direction)->toBeNull();

    Message::query()->create([
        'business_id' => 1,
        'conversation_id' => $conv->id,
        'direction' => Message::DIRECTION_INBOUND,
        'provider' => 'whatsapp_baileys',
        'type' => 'text',
        'body' => 'Olá, gostaria de saber sobre o pedido',
        'status' => Message::STATUS_RECEIVED,
    ]);

    $conv->refresh();
    expect($conv->last_message_preview)->toBe('Olá, gostaria de saber sobre o pedido');
    expect($conv->last_message_direction)->toBe('inbound');
});

it('R-WA-072-002 — 2a msg sobrescreve preview (nao acumula)', function () {
    session()->put('user.business_id', 1);

    $channel = Channel::query()->create([
        'business_id' => 1,
        'channel_uuid' => 'bbbbbbbb-0000-0000-0000-000000000001',
        'label' => 'X',
        'type' => Channel::TYPE_WHATSAPP_BAILEYS,
        'status' => 'active',
    ]);
    $conv = Conversation::query()->create([
        'business_id' => 1, 'channel_id' => $channel->id,
        'customer_external_id' => '+554822222222', 'status' => 'open',
    ]);

    Message::query()->create([
        'business_id' => 1,
        'conversation_id' => $conv->id,
        'direction' => Message::DIRECTION_INBOUND,
        'provider' => 'whatsapp_baileys',
        'type' => 'text',
        'body' => 'Primeira mensagem do cliente',
        'status' => Message::STATUS_RECEIVED,
    ]);

    $conv->refresh();
    expect($conv->last_message_preview)->toBe('Primeira mensagem do cliente');
    expect($conv->last_message_direction)->toBe('inbound');

    // 2ª msg — outbound do atendente — deve sobrescrever
    Message::query()->create([
        'business_id' => 1,
        'conversation_id' => $conv->id,
        'direction' => Message::DIRECTION_OUTBOUND,
        'provider' => 'whatsapp_baileys',
        'type' => 'text',
        'body' => 'Resposta do atendente',
        'status' => Message::STATUS_SENT,
        'sender_kind' => 'human',
    ]);

    $conv->refresh();
    expect($conv->last_message_preview)->toBe('Resposta do atendente');
    expect($conv->last_message_direction)->toBe('outbound');
});

it('R-WA-072-003 — body=null nao quebra (preview fica null)', function () {
    session()->put('user.business_id', 1);

    $channel = Channel::query()->create([
        'business_id' => 1,
        'channel_uuid' => 'cccccccc-0000-0000-0000-000000000001',
        'label' => 'X',
        'type' => Channel::TYPE_WHATSAPP_BAILEYS,
        'status' => 'active',
    ]);
    $conv = Conversation::query()->create([
        'business_id' => 1, 'channel_id' => $channel->id,
        'customer_external_id' => '+554833333333', 'status' => 'open',
    ]);

    // Mídia sem caption — body = null
    Message::query()->create([
        'business_id' => 1,
        'conversation_id' => $conv->id,
        'direction' => Message::DIRECTION_INBOUND,
        'provider' => 'whatsapp_baileys',
        'type' => 'image',
        'body' => null,
        'status' => Message::STATUS_RECEIVED,
    ]);

    $conv->refresh();
    expect($conv->last_message_preview)->toBeNull();
    // Direction segue setada mesmo sem body — UI mostra "📷 Imagem" placeholder
    expect($conv->last_message_direction)->toBe('inbound');
});

it('R-WA-072-004 — convToListArray retorna preview SEM disparar query em messages (zero N+1)', function () {
    session()->put('user.business_id', 1);

    $channel = Channel::query()->create([
        'business_id' => 1,
        'channel_uuid' => 'dddddddd-0000-0000-0000-000000000001',
        'label' => 'X',
        'type' => Channel::TYPE_WHATSAPP_BAILEYS,
        'status' => 'active',
    ]);
    $conv = Conversation::query()->create([
        'business_id' => 1, 'channel_id' => $channel->id,
        'customer_external_id' => '+554844444444', 'status' => 'open',
    ]);

    // 3 msgs — última deve ser o que aparece no preview
    Message::query()->create([
        'business_id' => 1, 'conversation_id' => $conv->id,
        'direction' => Message::DIRECTION_INBOUND, 'provider' => 'whatsapp_baileys',
        'type' => 'text', 'body' => 'Msg 1', 'status' => Message::STATUS_RECEIVED,
    ]);
    Message::query()->create([
        'business_id' => 1, 'conversation_id' => $conv->id,
        'direction' => Message::DIRECTION_OUTBOUND, 'provider' => 'whatsapp_baileys',
        'type' => 'text', 'body' => 'Msg 2', 'status' => Message::STATUS_SENT,
    ]);
    Message::query()->create([
        'business_id' => 1, 'conversation_id' => $conv->id,
        'direction' => Message::DIRECTION_INBOUND, 'provider' => 'whatsapp_baileys',
        'type' => 'text', 'body' => 'Msg 3 — última', 'status' => Message::STATUS_RECEIVED,
    ]);

    // Recarrega conv (simulating o controller que pega via paginate)
    $convReloaded = Conversation::query()->where('id', $conv->id)->with('channel')->first();

    // Liga query log AGORA — só rastreia o que convToListArray dispara
    DB::enableQueryLog();
    DB::flushQueryLog();

    $controller = app(InboxController::class);
    $reflection = new ReflectionClass($controller);
    $method = $reflection->getMethod('convToListArray');
    $method->setAccessible(true);
    $payload = $method->invoke($controller, $convReloaded);

    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    // Zero queries em `messages` — antes desse PR, esse trecho disparava
    // `SELECT * FROM messages WHERE conversation_id = ? ORDER BY ... LIMIT 1`
    $messageQueries = array_filter(
        $queries,
        fn ($q) => stripos($q['query'], 'from "messages"') !== false
            || stripos($q['query'], 'from `messages`') !== false
    );
    expect($messageQueries)->toHaveCount(0);

    // Preview correto vem das colunas denormalizadas
    expect($payload['last_message_preview'])->toBe('Msg 3 — última');
    expect($payload['last_message_direction'])->toBe('inbound');
});

it('R-WA-072-005 — Tier 0: Observer escreve preview EXCLUSIVAMENTE na conv da msg, nao toca conv de outro biz', function () {
    // Setup: 2 channels + 2 conversations, 1 por business (1 e 99).
    // Provar que ao criar msg em conv biz=99, a conv biz=1 NÃO recebe escrita
    // — Observer filtra estritamente por `conversation_id` (não escreve em
    // lote em conversas alheias).

    // biz=99 (alien tenant) — setup direto sem scope (sessão não setada ainda)
    $channelAlien = new Channel([
        'business_id' => 99,
        'channel_uuid' => 'eeeeeeee-0000-0000-0000-000000000099',
        'label' => 'Alien',
        'type' => Channel::TYPE_WHATSAPP_BAILEYS,
        'status' => 'active',
    ]);
    $channelAlien->save();
    $convAlien = new Conversation([
        'business_id' => 99,
        'channel_id' => $channelAlien->id,
        'customer_external_id' => '+5511999999999',
        'status' => 'open',
    ]);
    $convAlien->save();

    // biz=1 (legítimo)
    $channel1 = new Channel([
        'business_id' => 1,
        'channel_uuid' => 'ffffffff-0000-0000-0000-000000000001',
        'label' => 'Meu Chip',
        'type' => Channel::TYPE_WHATSAPP_BAILEYS,
        'status' => 'active',
    ]);
    $channel1->save();
    $conv1 = new Conversation([
        'business_id' => 1,
        'channel_id' => $channel1->id,
        'customer_external_id' => '+554811111111',
        'status' => 'open',
    ]);
    $conv1->save();

    // Cria msg em biz=99 — preview deve gravar SÓ em convAlien
    $msgAlien = new Message([
        'business_id' => 99,
        'conversation_id' => $convAlien->id,
        'direction' => Message::DIRECTION_INBOUND,
        'provider' => 'whatsapp_baileys',
        'type' => 'text',
        'body' => 'SEGREDO COMERCIAL biz=99',
        'status' => Message::STATUS_RECEIVED,
    ]);
    $msgAlien->save();

    // Defesa em profundidade — usa withoutGlobalScope pra inspecionar o DB
    // raw (sem depender do auth state). Confirma:
    // 1. convAlien recebeu o preview correto.
    // 2. conv1 (biz=1) ficou intocada (null) — Observer NÃO escreveu nela.
    $convAlienRefresh = Conversation::query()
        ->withoutGlobalScope(ScopeByBusiness::class)
        ->where('id', $convAlien->id)->first();
    expect($convAlienRefresh->last_message_preview)->toBe('SEGREDO COMERCIAL biz=99');
    expect($convAlienRefresh->last_message_direction)->toBe('inbound');

    $conv1Refresh = Conversation::query()
        ->withoutGlobalScope(ScopeByBusiness::class)
        ->where('id', $conv1->id)->first();
    expect($conv1Refresh->last_message_preview)->toBeNull(); // intocada
    expect($conv1Refresh->last_message_direction)->toBeNull();

    // Cria msg em biz=1 — preview deve gravar SÓ em conv1 (não sobrescreve convAlien)
    Message::query()->create([
        'business_id' => 1,
        'conversation_id' => $conv1->id,
        'direction' => Message::DIRECTION_OUTBOUND,
        'provider' => 'whatsapp_baileys',
        'type' => 'text',
        'body' => 'Resposta normal biz=1',
        'status' => Message::STATUS_SENT,
        'sender_kind' => 'human',
    ]);

    $conv1Refresh2 = Conversation::query()
        ->withoutGlobalScope(ScopeByBusiness::class)
        ->where('id', $conv1->id)->first();
    expect($conv1Refresh2->last_message_preview)->toBe('Resposta normal biz=1');
    expect($conv1Refresh2->last_message_direction)->toBe('outbound');

    // convAlien NÃO foi tocada pela msg do biz=1 — preview alien continua íntegro
    $convAlienFinal = Conversation::query()
        ->withoutGlobalScope(ScopeByBusiness::class)
        ->where('id', $convAlien->id)->first();
    expect($convAlienFinal->last_message_preview)->toBe('SEGREDO COMERCIAL biz=99');
    expect($convAlienFinal->last_message_direction)->toBe('inbound');
});
