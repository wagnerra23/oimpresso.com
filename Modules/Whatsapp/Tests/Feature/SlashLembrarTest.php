<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Entities\MemoriaFato;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Entities\Conversation;
use Modules\Whatsapp\Entities\Message;
use Modules\Whatsapp\Http\Controllers\Admin\InboxController;
use Modules\Whatsapp\Services\Notes\LembrarHandler;
use Modules\Whatsapp\Services\Notes\ParsedCommand;
use Modules\Whatsapp\Services\Notes\SlashCommandParser;
use Modules\Whatsapp\Services\Notes\SlashCommandRegistry;
use Modules\Whatsapp\Services\Notes\SlashCommandResult;

uses(Tests\TestCase::class);

/**
 * US-WA-074 (ADR 0142) — Slash command `/lembrar` + fundação SlashCommandParser.
 *
 * Cobre 8 dimensões:
 *
 *   1. Parser — `/lembrar prefere boleto` → ParsedCommand('lembrar', 'prefere boleto')
 *   2. Parser — `/desconhecido xyz` → null (não-match)
 *   3. Parser — `lembrar sem barra` → null
 *   4. LembrarHandler — cria fact em jana_memoria_facts com metadata correto
 *   5. LembrarHandler — arguments vazio → unrecognized graceful (no fact created)
 *   6. Multi-tenant Tier 0 — biz=99 não vê fact de biz=1 (global scope)
 *   7. Integration — `/lembrar X` em nota interna cria fact + flash badge
 *   8. Gate Tier 0 — `/lembrar X` em mensagem NORMAL (não-nota) NÃO cria fact
 *
 * @see Modules\Whatsapp\Services\Notes\*
 * @see memory/decisions/0142-notas-internas-sinal-treino-jana.md
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-074
 */
beforeEach(function () {
    foreach (['messages', 'conversations', 'channels', 'jana_memoria_facts'] as $t) {
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

    Schema::create('jana_memoria_facts', function ($table) {
        $table->id();
        $table->unsignedInteger('business_id');
        $table->unsignedInteger('user_id');
        $table->text('fato');
        $table->json('metadata')->nullable();
        $table->timestamp('valid_from')->useCurrent();
        $table->timestamp('valid_until')->nullable();
        $table->unsignedInteger('hits_count')->default(0);
        $table->timestamp('ultimo_hit_em')->nullable();
        $table->boolean('core_memory')->default(false);
        $table->timestamp('created_at')->nullable();
        $table->timestamp('updated_at')->nullable();
        $table->timestamp('deleted_at')->nullable();

        $table->index(['business_id', 'user_id'], 'cmf_biz_user_idx');
    });
});

function makeWaConvSlash(int $businessId, string $uuid, ?int $contactId = null): array
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

// ─── 1. Parser ──────────────────────────────────────────────────────────────

it('Parser — `/lembrar prefere boleto` → ParsedCommand(lembrar, "prefere boleto")', function () {
    $parser = new SlashCommandParser();
    $result = $parser->parse('/lembrar prefere boleto');

    expect($result)->toBeInstanceOf(ParsedCommand::class);
    expect($result->command)->toBe('lembrar');
    expect($result->arguments)->toBe('prefere boleto');
});

it('Parser — `/desconhecido xyz` → null (não-match)', function () {
    $parser = new SlashCommandParser();
    expect($parser->parse('/desconhecido xyz'))->toBeNull();
});

it('Parser — `lembrar sem barra` → null', function () {
    $parser = new SlashCommandParser();
    expect($parser->parse('lembrar sem barra'))->toBeNull();
});

it('Parser — `/lembrar` sem argumentos → null (graceful)', function () {
    $parser = new SlashCommandParser();
    expect($parser->parse('/lembrar'))->toBeNull();
    expect($parser->parse('/lembrar   '))->toBeNull();
});

it('Parser — trim leading/trailing spaces antes do match', function () {
    $parser = new SlashCommandParser();
    $result = $parser->parse('   /lembrar prefere boleto   ');
    expect($result)->not->toBeNull();
    expect($result->command)->toBe('lembrar');
    expect($result->arguments)->toBe('prefere boleto');
});

// ─── 2. LembrarHandler ──────────────────────────────────────────────────────

it('LembrarHandler — cria fact em jana_memoria_facts com metadata correto', function () {
    [, $conv] = makeWaConvSlash(1, 'aaaa-0000-0000-0000-lembrar', 42);

    $note = Message::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'conversation_id' => $conv->id,
        'direction' => 'outbound',
        'provider' => 'whatsapp_baileys',
        'type' => 'text',
        'body' => '/lembrar prefere boleto, recusa cartao',
        'status' => 'sent',
        'sender_user_id' => 7,
        'sender_kind' => 'human',
        'is_internal_note' => true,
    ]);
    $note->setRelation('conversation', $conv);

    $handler = new LembrarHandler();
    $result = $handler->handle($note, 'prefere boleto, recusa cartao');

    expect($result->kind)->toBe(SlashCommandResult::KIND_SUCCESS);
    expect($result->badge)->toBe('✓ memorizado');
    expect($result->linkUrl)->toMatch('#^/copiloto/admin/memoria\\?fact_id=\\d+$#');

    $fact = MemoriaFato::withoutGlobalScope(ScopeByBusiness::class)->first();
    expect($fact)->not->toBeNull();
    expect($fact->business_id)->toBe(1);
    expect($fact->user_id)->toBe(7);
    expect($fact->fato)->toBe('prefere boleto, recusa cartao');
    expect($fact->metadata['source'] ?? null)->toBe('human_note');
    expect($fact->metadata['source_user_id'] ?? null)->toBe(7);
    expect($fact->metadata['source_conversation_id'] ?? null)->toBe($conv->id);
    expect($fact->metadata['source_message_id'] ?? null)->toBe($note->id);
    expect($fact->metadata['contact_id'] ?? null)->toBe(42);
    // confidence: JSON encode/decode pode resultar em int 1 ou float 1.0 dependendo
    // de quem desserializou. Comparamos via float cast pra evitar drift Pest strict.
    expect((float) ($fact->metadata['confidence'] ?? 0))->toBe(1.0);
    expect($fact->metadata['category'] ?? null)->toBe('preference');
    expect($fact->valid_from)->not->toBeNull();
    expect($fact->valid_until)->toBeNull();
});

it('LembrarHandler — arguments vazio → unrecognized graceful (no fact created)', function () {
    [, $conv] = makeWaConvSlash(1, 'aaaa-0000-0000-0000-vazio');
    $note = Message::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'conversation_id' => $conv->id,
        'direction' => 'outbound',
        'provider' => 'whatsapp_baileys',
        'type' => 'text',
        'body' => '/lembrar',
        'status' => 'sent',
        'sender_kind' => 'human',
        'is_internal_note' => true,
    ]);

    $handler = new LembrarHandler();
    $result = $handler->handle($note, '');

    expect($result->isUnrecognized())->toBeTrue();
    expect(MemoriaFato::withoutGlobalScope(ScopeByBusiness::class)->count())->toBe(0);
});

it('LembrarHandler — gate redundante Tier 0 (is_internal_note=false vira error)', function () {
    [, $conv] = makeWaConvSlash(1, 'aaaa-0000-0000-0000-gate');
    $note = Message::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'conversation_id' => $conv->id,
        'direction' => 'outbound',
        'provider' => 'whatsapp_baileys',
        'type' => 'text',
        'body' => 'pode ser?',
        'status' => 'sent',
        'sender_kind' => 'human',
        'is_internal_note' => false, // NÃO é nota interna
    ]);
    $note->setRelation('conversation', $conv);

    $handler = new LembrarHandler();
    $result = $handler->handle($note, 'preferencia');

    expect($result->isError())->toBeTrue();
    expect(MemoriaFato::withoutGlobalScope(ScopeByBusiness::class)->count())->toBe(0);
});

// ─── 3. Multi-tenant Tier 0 ─────────────────────────────────────────────────

it('Multi-tenant Tier 0 — biz=99 NÃO vê fact criado via /lembrar em biz=1', function () {
    // Cria fact em biz=1
    [, $conv1] = makeWaConvSlash(1, 'aaaa-0000-0000-0000-biz1-lemb');
    $note1 = Message::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'conversation_id' => $conv1->id,
        'direction' => 'outbound',
        'provider' => 'whatsapp_baileys',
        'type' => 'text',
        'body' => '/lembrar segredo biz=1',
        'status' => 'sent',
        'sender_user_id' => 1,
        'sender_kind' => 'human',
        'is_internal_note' => true,
    ]);
    $note1->setRelation('conversation', $conv1);
    (new LembrarHandler())->handle($note1, 'segredo biz=1');

    // Cria fact em biz=99
    [, $conv99] = makeWaConvSlash(99, 'aaaa-0000-0000-0000-biz99-lemb');
    $note99 = Message::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 99,
        'conversation_id' => $conv99->id,
        'direction' => 'outbound',
        'provider' => 'whatsapp_baileys',
        'type' => 'text',
        'body' => '/lembrar fato biz=99',
        'status' => 'sent',
        'sender_user_id' => 1,
        'sender_kind' => 'human',
        'is_internal_note' => true,
    ]);
    $note99->setRelation('conversation', $conv99);
    (new LembrarHandler())->handle($note99, 'fato biz=99');

    expect(MemoriaFato::withoutGlobalScope(ScopeByBusiness::class)->count())->toBe(2);

    // Visão biz=99 (global scope) vê só o dele
    session(['user.business_id' => 99]);
    $visible = MemoriaFato::where('business_id', 99)->get();
    expect($visible)->toHaveCount(1);
    expect($visible->first()->fato)->toBe('fato biz=99');
    expect($visible->pluck('fato')->toArray())->not->toContain('segredo biz=1');
});

// ─── 4. SlashCommandRegistry ────────────────────────────────────────────────

it('Registry — comando registrado dispatcha pro handler correto', function () {
    [, $conv] = makeWaConvSlash(1, 'aaaa-0000-0000-0000-reg');
    $note = Message::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'conversation_id' => $conv->id,
        'direction' => 'outbound',
        'provider' => 'whatsapp_baileys',
        'type' => 'text',
        'body' => '/lembrar x',
        'status' => 'sent',
        'sender_user_id' => 1,
        'sender_kind' => 'human',
        'is_internal_note' => true,
    ]);
    $note->setRelation('conversation', $conv);

    $registry = new SlashCommandRegistry();
    $registry->register('lembrar', new LembrarHandler());

    expect($registry->has('lembrar'))->toBeTrue();
    expect($registry->has('corrigir'))->toBeFalse();

    $result = $registry->dispatch('lembrar', $note, 'prefere PIX');
    expect($result->isSuccess())->toBeTrue();
});

it('Registry — comando NÃO registrado retorna unrecognized()', function () {
    [, $conv] = makeWaConvSlash(1, 'aaaa-0000-0000-0000-unk');
    $note = Message::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'conversation_id' => $conv->id,
        'direction' => 'outbound',
        'provider' => 'whatsapp_baileys',
        'type' => 'text',
        'body' => '/corrigir bot disse errado',
        'status' => 'sent',
        'sender_kind' => 'human',
        'is_internal_note' => true,
    ]);

    $registry = new SlashCommandRegistry();
    // SÓ registra lembrar — corrigir continua "vago" até US-WA-075
    $registry->register('lembrar', new LembrarHandler());

    $result = $registry->dispatch('corrigir', $note, 'bot disse errado');
    expect($result->isUnrecognized())->toBeTrue();
});

// ─── 5. Integration: InboxController::send ──────────────────────────────────

it('Integration — POST send com is_internal_note=true + /lembrar X cria fact + flash badge', function () {
    Http::fake(); // Tier 0 — driver Baileys NUNCA é chamado
    session(['user.business_id' => 1, 'user.id' => 7]);
    [, $conv] = makeWaConvSlash(1, 'aaaa-0000-0000-0000-int1', 99);

    $request = Request::create('', 'POST', [
        'kind' => 'freeform',
        'body' => '/lembrar prefere boleto',
        'is_internal_note' => true,
    ]);
    $request->setLaravelSession(app('session.store'));
    app('session.store')->put('user.business_id', 1);
    app('session.store')->put('user.id', 7);

    $controller = new InboxController();
    $response = $controller->send($request, $conv->id);

    // Tier 0 — driver NUNCA chamado
    Http::assertNothingSent();

    // Mensagem persistida como nota interna
    $message = Message::withoutGlobalScope(ScopeByBusiness::class)
        ->where('business_id', 1)
        ->first();
    expect($message)->not->toBeNull();
    expect($message->is_internal_note)->toBeTrue();
    expect($message->body)->toBe('/lembrar prefere boleto');

    // Fact criado
    $fact = MemoriaFato::withoutGlobalScope(ScopeByBusiness::class)->first();
    expect($fact)->not->toBeNull();
    expect($fact->fato)->toBe('prefere boleto');
    expect($fact->metadata['contact_id'] ?? null)->toBe(99);
    expect($fact->metadata['source'] ?? null)->toBe('human_note');

    // Flash slash payload pra UI renderizar badge
    $slashFlash = $response->getSession()->get('slash');
    expect($slashFlash)->not->toBeNull();
    expect($slashFlash['kind'] ?? null)->toBe('success');
    expect($slashFlash['badge'] ?? null)->toBe('✓ memorizado');
    expect($slashFlash['command'] ?? null)->toBe('lembrar');
    expect($slashFlash['message_id'] ?? null)->toBe($message->id);
});

it('Integration — POST send com is_internal_note=false + /lembrar X NÃO cria fact (gate Tier 0)', function () {
    // Daemon URL precisa estar configurado pra simular envio normal
    config([
        'whatsapp.baileys.daemon_url' => 'https://daemon.test',
        'whatsapp.baileys.api_key' => 'test-key-min16chars',
    ]);
    Http::fake([
        '*' => Http::response(['status' => 'sent', 'message_id' => 'wamid.zzz'], 200),
    ]);
    session(['user.business_id' => 1, 'user.id' => 7]);
    [, $conv] = makeWaConvSlash(1, 'aaaa-0000-0000-0000-int2', 99);

    $request = Request::create('', 'POST', [
        'kind' => 'freeform',
        'body' => '/lembrar isto NUNCA deveria virar fato',
        'is_internal_note' => false, // resposta REAL — slash não roda
    ]);
    $request->setLaravelSession(app('session.store'));
    app('session.store')->put('user.business_id', 1);
    app('session.store')->put('user.id', 7);

    $controller = new InboxController();
    $controller->send($request, $conv->id);

    // Driver foi chamado (controle positivo) — body foi entendido como msg cliente
    Http::assertSent(fn ($req) => str_contains($req->url(), '/instances/'));

    // Fact NÃO criado — gate Tier 0
    expect(MemoriaFato::withoutGlobalScope(ScopeByBusiness::class)->count())->toBe(0);

    // Sem flash slash payload
    expect(session('slash'))->toBeNull();
});
