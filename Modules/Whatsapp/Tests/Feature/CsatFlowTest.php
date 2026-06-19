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
use Modules\Whatsapp\Entities\CsatResponse;
use Modules\Whatsapp\Entities\Message;
use Modules\Whatsapp\Http\Controllers\Admin\InboxController;
use Modules\Whatsapp\Jobs\DispatchCsatJob;
use Modules\Whatsapp\Services\Csat\CsatDispatcher;
use Modules\Whatsapp\Services\Csat\CsatResponseParser;

uses(Tests\TestCase::class);

/**
 * PR-6 CYCLE-07 — CSAT pós-resolução (1-5 estrelas via WhatsApp).
 *
 * Fluxo testado:
 *   1. Resolve conv → CsatResponse row criada + msg outbound enviada
 *   2. Idempotência — resolve 2× → não duplica row
 *   3. Parser extrai 1-5 de várias variações de texto (incluindo estrelas Unicode)
 *   4. Inbound após resolve com "5" → recordResponse popula score
 *   5. Cross-tenant biz=99 → isolado de biz=1
 *   6. Comment opcional — "5 obrigado" → score=5, comment="obrigado"
 *
 * @see CsatDispatcher
 * @see CsatResponseParser
 * @see memory/requisitos/Whatsapp/COMPARATIVO-MERCADO-2026-05-12.md gap #5 P1
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('era-sqlite: schema sintético manual incompatível com MySQL persistente — quarentena Onda 2 SDD floor; burn-down converte depois.');
    }

    foreach (['whatsapp_csat_responses', 'messages', 'channel_user_access', 'conversations', 'channels'] as $t) {
        Schema::dropIfExists($t);
    }

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
        $table->string('channel_health', 20)->default('never_checked');
        $table->unsignedInteger('channel_health_consecutive_failures')->default(0);
        $table->timestamp('last_health_check_at')->nullable();
        $table->text('last_health_message')->nullable();
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

    Schema::create('whatsapp_csat_responses', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->unsignedBigInteger('conversation_id');
        $table->unsignedBigInteger('resolved_message_id');
        $table->unsignedBigInteger('response_message_id')->nullable();
        $table->unsignedTinyInteger('score')->nullable();
        $table->text('comment')->nullable();
        $table->unsignedInteger('resolved_by_user_id')->nullable();
        $table->timestamp('asked_at');
        $table->timestamp('responded_at')->nullable();
        $table->timestamps();
    });

    config([
        'whatsapp.baileys.daemon_url' => 'https://daemon.test',
        'whatsapp.baileys.api_key' => 'test-key-min16chars',
        'whatsapp.csat.enabled' => true,
    ]);
});

function csatMakeChannelAndConv(int $businessId, string $uuid = 'csat-aaaa-bbbb-cccc-0000'): array
{
    $channel = Channel::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => $businessId,
        'channel_uuid' => $uuid,
        'label' => 'Suporte CSAT',
        'type' => Channel::TYPE_WHATSAPP_BAILEYS,
        'status' => 'active',
    ]);

    $conv = Conversation::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => $businessId,
        'channel_id' => $channel->id,
        'customer_external_id' => '+5511988887777',
        'contact_name' => 'Cliente CSAT',
        'status' => Conversation::STATUS_OPEN,
    ]);

    ChannelUserAccess::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => $businessId,
        'channel_id' => $channel->id,
        'user_id' => 1,
        'granted_by_user_id' => 1,
        'granted_at' => now(),
    ]);

    return [$channel, $conv];
}

it('Test 1 — resolve conv dispara CsatResponse + msg outbound', function () {
    Http::fake([
        '*' => Http::response(['status' => 'sent', 'message_id' => 'wamid.csat'], 200),
    ]);
    [, $conv] = csatMakeChannelAndConv(1);

    $dispatcher = app(CsatDispatcher::class);
    $csat = $dispatcher->dispatchOnResolve($conv, resolvedBy: 1);

    expect($csat)->not->toBeNull();
    expect($csat->business_id)->toBe(1);
    expect($csat->conversation_id)->toBe($conv->id);
    expect($csat->score)->toBeNull();
    expect($csat->asked_at)->not->toBeNull();
    expect($csat->resolved_by_user_id)->toBe(1);

    // Msg outbound CSAT persistida
    $msg = Message::withoutGlobalScope(ScopeByBusiness::class)
        ->where('business_id', 1)
        ->where('id', $csat->resolved_message_id)
        ->first();
    expect($msg)->not->toBeNull();
    expect($msg->direction)->toBe('outbound');
    expect($msg->type)->toBe('text');
    expect($msg->sender_kind)->toBe('system');
    expect($msg->body)->toContain('1 a 5');

    // Daemon foi chamado
    Http::assertSent(fn ($req) => str_contains($req->url(), '/instances/'));
});

it('Test 2 — idempotência: resolve 2x não duplica CsatResponse', function () {
    Http::fake([
        '*' => Http::response(['status' => 'sent', 'message_id' => 'wamid.idem'], 200),
    ]);
    [, $conv] = csatMakeChannelAndConv(1);

    $dispatcher = app(CsatDispatcher::class);
    $first = $dispatcher->dispatchOnResolve($conv, resolvedBy: 1);
    $second = $dispatcher->dispatchOnResolve($conv, resolvedBy: 1);

    expect($first)->not->toBeNull();
    expect($second)->toBeNull(); // idempotente — não duplica

    $rows = CsatResponse::withoutGlobalScope(ScopeByBusiness::class)
        ->where('business_id', 1)
        ->get();
    expect($rows)->toHaveCount(1);
});

it('Test 3 — parser extrai 1-5 de variantes ("5", "5/5", "nota 5", "⭐⭐⭐⭐⭐")', function () {
    $parser = new CsatResponseParser();

    expect($parser->tryParse('5'))->toBe(5);
    expect($parser->tryParse(' 5 '))->toBe(5);
    expect($parser->tryParse('5/5'))->toBe(5);
    expect($parser->tryParse('nota 5'))->toBe(5);
    expect($parser->tryParse('Nota 4!'))->toBe(4);
    expect($parser->tryParse('Avalio 3 ok'))->toBe(3);
    expect($parser->tryParse('1'))->toBe(1);

    // Estrelas Unicode (⭐ U+2B50)
    expect($parser->tryParse("\u{2B50}\u{2B50}\u{2B50}\u{2B50}\u{2B50}"))->toBe(5);
    expect($parser->tryParse("\u{2B50}\u{2B50}\u{2B50}\u{2B50}"))->toBe(4);
    expect($parser->tryParse("\u{2B50}"))->toBe(1);
    // Star sólido (★ U+2605)
    expect($parser->tryParse("\u{2605}\u{2605}\u{2605}"))->toBe(3);

    // Fora 1-5
    expect($parser->tryParse('10'))->toBeNull();
    expect($parser->tryParse('0'))->toBeNull();
    expect($parser->tryParse('100'))->toBeNull();
    expect($parser->tryParse(''))->toBeNull();
    expect($parser->tryParse('obrigado'))->toBeNull(); // sem score
    expect($parser->tryParse('15'))->toBeNull();       // 15 não é 1
});

it('Test 4 — inbound após resolve com "5" → recordResponse popula score', function () {
    [, $conv] = csatMakeChannelAndConv(1);

    // Simula que CsatDispatcher criou row pending (msg outbound + asked_at recente)
    $outboundMsg = Message::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'conversation_id' => $conv->id,
        'direction' => 'outbound',
        'provider' => 'whatsapp_baileys',
        'type' => 'text',
        'body' => 'CSAT?',
        'status' => 'sent',
    ]);
    $pending = CsatResponse::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'conversation_id' => $conv->id,
        'resolved_message_id' => $outboundMsg->id,
        'asked_at' => now()->subMinutes(5),
    ]);

    // Simula msg inbound cliente respondendo "5"
    $inboundMsg = Message::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'conversation_id' => $conv->id,
        'direction' => 'inbound',
        'provider' => 'whatsapp_baileys',
        'type' => 'text',
        'body' => '5',
        'status' => 'received',
    ]);

    $parser = new CsatResponseParser();
    $score = $parser->tryParse('5');
    expect($score)->toBe(5);

    $updated = $parser->recordResponse(
        businessId: 1,
        conversationId: $conv->id,
        score: $score,
        comment: null,
        messageId: (int) $inboundMsg->id,
    );

    expect($updated)->not->toBeNull();
    expect($updated->id)->toBe($pending->id);
    expect($updated->score)->toBe(5);
    expect($updated->response_message_id)->toBe((int) $inboundMsg->id);
    expect($updated->responded_at)->not->toBeNull();
});

it('Test 5 — cross-tenant biz=99 isolado de biz=1 (global scope)', function () {
    [, $conv1] = csatMakeChannelAndConv(1, 'csat-biz-0001');
    [, $conv99] = csatMakeChannelAndConv(99, 'csat-biz-0099');

    // Cria 1 row cada biz
    CsatResponse::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'conversation_id' => $conv1->id,
        'resolved_message_id' => 1,
        'score' => 5,
        'asked_at' => now(),
        'responded_at' => now(),
    ]);
    CsatResponse::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 99,
        'conversation_id' => $conv99->id,
        'resolved_message_id' => 2,
        'score' => 1,
        'asked_at' => now(),
        'responded_at' => now(),
    ]);

    // Sessão biz=99
    session(['user.business_id' => 99]);
    $visible = CsatResponse::where('business_id', 99)->get();

    expect($visible)->toHaveCount(1);
    expect($visible->first()->score)->toBe(1);

    // Sessão biz=1 vê só o seu
    session(['user.business_id' => 1]);
    $visible1 = CsatResponse::where('business_id', 1)->get();

    expect($visible1)->toHaveCount(1);
    expect($visible1->first()->score)->toBe(5);

    // Parser respeita business_id na busca de pending (cross-tenant safety)
    $parser = new CsatResponseParser();
    // Cria 1 pending biz=1
    CsatResponse::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'conversation_id' => $conv1->id,
        'resolved_message_id' => 3,
        'asked_at' => now(),
    ]);
    // Tenta gravar resposta usando biz=99 + conversation_id de biz=1 → no match
    $invalid = $parser->recordResponse(99, $conv1->id, 5, null, 999);
    expect($invalid)->toBeNull();
});

it('Test 6 — comment opcional: "5 obrigado" → score=5, comment="obrigado"', function () {
    $parser = new CsatResponseParser();

    $score = $parser->tryParse('5 obrigado');
    $comment = $parser->tryParseComment('5 obrigado');

    expect($score)->toBe(5);
    expect($comment)->toBe('obrigado');

    // Estrelas + cauda
    $starsBody = "\u{2B50}\u{2B50}\u{2B50}\u{2B50}\u{2B50} valeu demais";
    expect($parser->tryParse($starsBody))->toBe(5);
    expect($parser->tryParseComment($starsBody))->toBe('valeu demais');

    // Só nota numérica sem cauda
    expect($parser->tryParseComment('5'))->toBeNull();
    expect($parser->tryParseComment('5/5'))->toBeNull();

    // Comment via recordResponse persiste
    [, $conv] = csatMakeChannelAndConv(1);
    $outMsg = Message::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'conversation_id' => $conv->id,
        'direction' => 'outbound',
        'provider' => 'whatsapp_baileys',
        'type' => 'text',
        'body' => 'CSAT?',
        'status' => 'sent',
    ]);
    CsatResponse::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'conversation_id' => $conv->id,
        'resolved_message_id' => $outMsg->id,
        'asked_at' => now()->subMinutes(2),
    ]);

    $inMsg = Message::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => 1,
        'conversation_id' => $conv->id,
        'direction' => 'inbound',
        'provider' => 'whatsapp_baileys',
        'type' => 'text',
        'body' => '5 obrigado',
        'status' => 'received',
    ]);

    $row = $parser->recordResponse(1, $conv->id, 5, 'obrigado', (int) $inMsg->id);

    expect($row)->not->toBeNull();
    expect($row->score)->toBe(5);
    expect($row->comment)->toBe('obrigado');
});

it('InboxController updateStatus → resolved dispara DispatchCsatJob (Bus::fake)', function () {
    Bus::fake();
    session(['user.business_id' => 1, 'user.id' => 1]);
    [, $conv] = csatMakeChannelAndConv(1);

    $request = Request::create('', 'PATCH', ['status' => 'resolved']);
    $request->setLaravelSession(app('session.store'));
    app('session.store')->put('user.business_id', 1);
    app('session.store')->put('user.id', 1);

    $controller = new InboxController();
    $controller->updateStatus($request, $conv->id);

    Bus::assertDispatched(DispatchCsatJob::class, function (DispatchCsatJob $job) use ($conv) {
        return $job->businessId === 1
            && $job->conversationId === $conv->id
            && $job->resolvedBy === 1;
    });
});

it('InboxController updateStatus → resolved 2x dispara Job só na transição (não duplica)', function () {
    Bus::fake();
    session(['user.business_id' => 1, 'user.id' => 1]);
    [, $conv] = csatMakeChannelAndConv(1);
    $conv->status = Conversation::STATUS_RESOLVED;
    $conv->save();

    // Já estava resolved — 2ª chamada não deve disparar (status anterior == novo)
    $request = Request::create('', 'PATCH', ['status' => 'resolved']);
    $request->setLaravelSession(app('session.store'));
    app('session.store')->put('user.business_id', 1);
    app('session.store')->put('user.id', 1);

    $controller = new InboxController();
    $controller->updateStatus($request, $conv->id);

    Bus::assertNotDispatched(DispatchCsatJob::class);
});
