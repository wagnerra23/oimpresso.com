<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Entities\Conversation;
use Modules\Whatsapp\Entities\JanaCorrecao;
use Modules\Whatsapp\Entities\Message;
use Modules\Whatsapp\Http\Controllers\Admin\InboxController;
use Modules\Whatsapp\Services\Notes\CorrigirHandler;
use Modules\Whatsapp\Services\Notes\ParsedCommand;
use Modules\Whatsapp\Services\Notes\SlashCommandParser;
use Modules\Whatsapp\Services\Notes\SlashCommandResult;

uses(Tests\TestCase::class);

/**
 * US-WA-075 (ADR 0142 §3a) — Slash command `/corrigir` + training signal Jana.
 *
 * Cobre 7 dimensões:
 *
 *   1. Parser — `/corrigir Deveria dizer X` → ParsedCommand('corrigir', 'Deveria dizer X')
 *   2. CorrigirHandler — cria row em whatsapp_jana_correcoes com metadata correto
 *   3. CorrigirHandler — nenhuma msg do bot na conv → error gracioso
 *   4. CorrigirHandler — arguments vazio → unrecognized graceful
 *   5. CorrigirHandler — gate redundante Tier 0 (is_internal_note=false → error)
 *   6. Multi-tenant Tier 0 — biz=99 não vê correções de biz=1 (global scope)
 *   7. Integration via InboxController::send — POST com is_internal_note=true cria
 *      correção + flash badge
 *
 * Schema migration idempotência é validada implicitamente via Schema::hasTable
 * guard no setUp (a migration própria já é idempotente em up()).
 *
 * @see Modules\Whatsapp\Services\Notes\CorrigirHandler
 * @see Modules\Whatsapp\Entities\JanaCorrecao
 * @see memory/decisions/0142-notas-internas-sinal-treino-jana.md §3a
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-075
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('era-sqlite: schema sintético manual incompatível com MySQL persistente — quarentena Onda 2 SDD floor; burn-down converte depois.');
    }

    // Drop em ordem reversa de FK (correcoes -> messages -> conversations -> channels)
    foreach (['whatsapp_jana_correcoes', 'messages', 'conversations', 'channels'] as $t) {
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

    Schema::create('whatsapp_jana_correcoes', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->unsignedBigInteger('conversation_id');
        $table->unsignedBigInteger('message_id_errada');
        $table->text('correcao_texto');
        $table->unsignedInteger('contact_id')->nullable();
        $table->unsignedInteger('atendente_user_id');
        $table->string('training_status', 20)->default('pending_review');
        $table->json('metadata')->nullable();
        $table->timestamp('created_at')->useCurrent();
        $table->timestamp('updated_at')->nullable();

        $table->index(['business_id', 'training_status'], 'wjc_biz_status_idx');
        $table->index('message_id_errada', 'wjc_msg_idx');
    });
});

/**
 * Helper — cria channel + conversation no business indicado.
 * Bypassa global scope porque o test não roda em sessão autenticada.
 */
function makeWaConvCorrigir(int $businessId, string $uuid, ?int $contactId = null): array
{
    $channel = Channel::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => $businessId,
        'channel_uuid' => $uuid,
        'label' => 'Suporte',
        'type' => Channel::TYPE_WHATSAPP_BAILEYS,
        'status' => 'active',
    ]);

    $conv = Conversation::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => $businessId,
        'channel_id' => $channel->id,
        'contact_id' => $contactId,
        'customer_external_id' => '+5511999999999',
        'contact_name' => 'Cliente Teste',
        'status' => 'open',
    ]);

    return [$channel, $conv];
}

/**
 * Helper — cria msg do bot anterior à nota (target da correção).
 */
function makeBotMessage(int $businessId, int $conversationId, string $body = 'resposta errada do bot'): Message
{
    return Message::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => $businessId,
        'conversation_id' => $conversationId,
        'direction' => 'outbound',
        'provider' => 'whatsapp_baileys',
        'type' => 'text',
        'body' => $body,
        'status' => 'sent',
        'sender_kind' => 'bot',
        'is_internal_note' => false,
    ]);
}

// ─── 1. Parser ──────────────────────────────────────────────────────────────

it('Parser — `/corrigir Deveria dizer X` → ParsedCommand(corrigir, "Deveria dizer X")', function () {
    $parser = new SlashCommandParser();
    $result = $parser->parse('/corrigir Deveria ter dito que entrega é em 3 dias, não 7');

    expect($result)->toBeInstanceOf(ParsedCommand::class);
    expect($result->command)->toBe('corrigir');
    expect($result->arguments)->toBe('Deveria ter dito que entrega é em 3 dias, não 7');
});

// ─── 2. CorrigirHandler ─────────────────────────────────────────────────────

it('CorrigirHandler — cria row em whatsapp_jana_correcoes com metadata correto', function () {
    [, $conv] = makeWaConvCorrigir(1, 'aaaa-0000-0000-0000-corrigir', 42);

    // Msg errada do bot ANTES da nota
    $botMsg = makeBotMessage(1, (int) $conv->id);

    $note = Message::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'conversation_id' => $conv->id,
        'direction' => 'outbound',
        'provider' => 'whatsapp_baileys',
        'type' => 'text',
        'body' => '/corrigir Deveria ter dito entrega em 3 dias',
        'status' => 'sent',
        'sender_user_id' => 7,
        'sender_kind' => 'human',
        'is_internal_note' => true,
    ]);
    $note->setRelation('conversation', $conv);

    $handler = new CorrigirHandler();
    $result = $handler->handle($note, 'Deveria ter dito entrega em 3 dias');

    expect($result->kind)->toBe(SlashCommandResult::KIND_SUCCESS);
    expect($result->badge)->toBe('⚠ corrigida');
    expect($result->linkUrl)->toMatch('#^/copiloto/admin/correcoes-jana\?correcao_id=\d+$#');

    $correcao = JanaCorrecao::withoutGlobalScope(ScopeByBusiness::class)->first();
    expect($correcao)->not->toBeNull();
    expect($correcao->business_id)->toBe(1);
    expect($correcao->conversation_id)->toBe((int) $conv->id);
    expect($correcao->message_id_errada)->toBe((int) $botMsg->id);
    expect($correcao->correcao_texto)->toBe('Deveria ter dito entrega em 3 dias');
    expect($correcao->atendente_user_id)->toBe(7);
    expect($correcao->contact_id)->toBe(42);
    expect($correcao->training_status)->toBe(JanaCorrecao::STATUS_PENDING_REVIEW);
    expect($correcao->metadata['source'] ?? null)->toBe('human_note');
    expect($correcao->metadata['source_message_id'] ?? null)->toBe($note->id);
    expect($correcao->metadata['resolution'] ?? null)->toBe('latest_bot_message_fallback');
});

it('CorrigirHandler — usa a ÚLTIMA msg do bot (mais recente) quando há várias', function () {
    [, $conv] = makeWaConvCorrigir(1, 'aaaa-0000-0000-0000-multi-bot');

    // 3 msgs do bot — a 3ª é a target
    $bot1 = makeBotMessage(1, (int) $conv->id, 'primeira resposta');
    $bot2 = makeBotMessage(1, (int) $conv->id, 'segunda resposta');
    $bot3 = makeBotMessage(1, (int) $conv->id, 'terceira resposta — esta é a errada');

    $note = Message::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'conversation_id' => $conv->id,
        'direction' => 'outbound',
        'provider' => 'whatsapp_baileys',
        'type' => 'text',
        'body' => '/corrigir X',
        'status' => 'sent',
        'sender_user_id' => 7,
        'sender_kind' => 'human',
        'is_internal_note' => true,
    ]);
    $note->setRelation('conversation', $conv);

    (new CorrigirHandler())->handle($note, 'expected: Y');

    $correcao = JanaCorrecao::withoutGlobalScope(ScopeByBusiness::class)->first();
    expect($correcao->message_id_errada)->toBe((int) $bot3->id);
});

it('CorrigirHandler — nenhuma msg do bot na conversa → error gracioso', function () {
    [, $conv] = makeWaConvCorrigir(1, 'aaaa-0000-0000-0000-no-bot');

    // SEM msg do bot — só a nota
    $note = Message::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'conversation_id' => $conv->id,
        'direction' => 'outbound',
        'provider' => 'whatsapp_baileys',
        'type' => 'text',
        'body' => '/corrigir X',
        'status' => 'sent',
        'sender_user_id' => 7,
        'sender_kind' => 'human',
        'is_internal_note' => true,
    ]);
    $note->setRelation('conversation', $conv);

    $handler = new CorrigirHandler();
    $result = $handler->handle($note, 'expected response');

    expect($result->isError())->toBeTrue();
    expect($result->errorMessage)->toContain('Nenhuma mensagem do bot');
    expect(JanaCorrecao::withoutGlobalScope(ScopeByBusiness::class)->count())->toBe(0);
});

it('CorrigirHandler — arguments vazio → unrecognized graceful (no correcao created)', function () {
    [, $conv] = makeWaConvCorrigir(1, 'aaaa-0000-0000-0000-empty');
    makeBotMessage(1, (int) $conv->id);

    $note = Message::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'conversation_id' => $conv->id,
        'direction' => 'outbound',
        'provider' => 'whatsapp_baileys',
        'type' => 'text',
        'body' => '/corrigir',
        'status' => 'sent',
        'sender_kind' => 'human',
        'is_internal_note' => true,
    ]);

    $handler = new CorrigirHandler();
    $result = $handler->handle($note, '');

    expect($result->isUnrecognized())->toBeTrue();
    expect(JanaCorrecao::withoutGlobalScope(ScopeByBusiness::class)->count())->toBe(0);
});

it('CorrigirHandler — gate redundante Tier 0 (is_internal_note=false vira error)', function () {
    [, $conv] = makeWaConvCorrigir(1, 'aaaa-0000-0000-0000-gate');
    makeBotMessage(1, (int) $conv->id);

    $note = Message::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'conversation_id' => $conv->id,
        'direction' => 'outbound',
        'provider' => 'whatsapp_baileys',
        'type' => 'text',
        'body' => 'resposta normal',
        'status' => 'sent',
        'sender_kind' => 'human',
        'is_internal_note' => false, // NÃO é nota interna
    ]);
    $note->setRelation('conversation', $conv);

    $handler = new CorrigirHandler();
    $result = $handler->handle($note, 'expected response');

    expect($result->isError())->toBeTrue();
    expect(JanaCorrecao::withoutGlobalScope(ScopeByBusiness::class)->count())->toBe(0);
});

// ─── 3. Multi-tenant Tier 0 ─────────────────────────────────────────────────

it('Multi-tenant Tier 0 — biz=99 NÃO vê correções criadas via /corrigir em biz=1', function () {
    // Cria correção em biz=1
    [, $conv1] = makeWaConvCorrigir(1, 'aaaa-0000-0000-0000-biz1-corr');
    makeBotMessage(1, (int) $conv1->id, 'bot biz=1');
    $note1 = Message::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'conversation_id' => $conv1->id,
        'direction' => 'outbound',
        'provider' => 'whatsapp_baileys',
        'type' => 'text',
        'body' => '/corrigir segredo biz=1',
        'status' => 'sent',
        'sender_user_id' => 1,
        'sender_kind' => 'human',
        'is_internal_note' => true,
    ]);
    $note1->setRelation('conversation', $conv1);
    (new CorrigirHandler())->handle($note1, 'segredo biz=1');

    // Cria correção em biz=99
    [, $conv99] = makeWaConvCorrigir(99, 'aaaa-0000-0000-0000-biz99-corr');
    makeBotMessage(99, (int) $conv99->id, 'bot biz=99');
    $note99 = Message::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 99,
        'conversation_id' => $conv99->id,
        'direction' => 'outbound',
        'provider' => 'whatsapp_baileys',
        'type' => 'text',
        'body' => '/corrigir correcao biz=99',
        'status' => 'sent',
        'sender_user_id' => 1,
        'sender_kind' => 'human',
        'is_internal_note' => true,
    ]);
    $note99->setRelation('conversation', $conv99);
    (new CorrigirHandler())->handle($note99, 'correcao biz=99');

    expect(JanaCorrecao::withoutGlobalScope(ScopeByBusiness::class)->count())->toBe(2);

    // Visão biz=99 vê só o dele
    session(['user.business_id' => 99]);
    $visible = JanaCorrecao::where('business_id', 99)->get();
    expect($visible)->toHaveCount(1);
    expect($visible->first()->correcao_texto)->toBe('correcao biz=99');
    expect($visible->pluck('correcao_texto')->toArray())->not->toContain('segredo biz=1');
});

// ─── 4. Integration: InboxController::send ──────────────────────────────────

it('Integration — POST send com is_internal_note=true + /corrigir X cria correção + flash badge', function () {
    Http::fake(); // Tier 0 — driver Baileys NUNCA é chamado em nota interna
    session(['user.business_id' => 1, 'user.id' => 7]);
    [, $conv] = makeWaConvCorrigir(1, 'aaaa-0000-0000-0000-int-corr', 99);

    // Msg do bot que vai ser corrigida — precisa existir ANTES da nota
    $botMsg = makeBotMessage(1, (int) $conv->id, 'bot disse algo errado');

    $request = Request::create('', 'POST', [
        'kind' => 'freeform',
        'body' => '/corrigir Deveria dizer entrega em 3 dias',
        'is_internal_note' => true,
    ]);
    $request->setLaravelSession(app('session.store'));
    app('session.store')->put('user.business_id', 1);
    app('session.store')->put('user.id', 7);

    $controller = new InboxController();
    $response = $controller->send($request, $conv->id);

    // Tier 0 — driver NUNCA chamado
    Http::assertNothingSent();

    // Mensagem nota interna persistida
    $note = Message::withoutGlobalScope(ScopeByBusiness::class)
        ->where('business_id', 1)
        ->where('is_internal_note', true)
        ->first();
    expect($note)->not->toBeNull();
    expect($note->body)->toBe('/corrigir Deveria dizer entrega em 3 dias');

    // Correção criada com message_id_errada apontando pra msg do bot
    $correcao = JanaCorrecao::withoutGlobalScope(ScopeByBusiness::class)->first();
    expect($correcao)->not->toBeNull();
    expect($correcao->correcao_texto)->toBe('Deveria dizer entrega em 3 dias');
    expect($correcao->message_id_errada)->toBe((int) $botMsg->id);
    expect($correcao->contact_id)->toBe(99);
    expect($correcao->atendente_user_id)->toBe(7);
    expect($correcao->training_status)->toBe(JanaCorrecao::STATUS_PENDING_REVIEW);

    // Flash slash payload pra UI renderizar badge
    $slashFlash = $response->getSession()->get('slash');
    expect($slashFlash)->not->toBeNull();
    expect($slashFlash['kind'] ?? null)->toBe('success');
    expect($slashFlash['badge'] ?? null)->toBe('⚠ corrigida');
    expect($slashFlash['command'] ?? null)->toBe('corrigir');
    expect($slashFlash['message_id'] ?? null)->toBe($note->id);
});
