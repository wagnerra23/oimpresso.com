<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Modules\Whatsapp\Entities\Message;
use Modules\Whatsapp\Events\OmnichannelMessageReceived;
use Modules\Whatsapp\Jobs\AnalisarMensagemInboundJob;
use Modules\Whatsapp\Listeners\AnalisarMensagemInboundComJana;

uses(Tests\TestCase::class);

/**
 * US-WA-095 — Listener AnalisarMensagemInboundComJana — guards Tier 0.
 *
 * Cobertura:
 *   1. config disabled → não dispatcha
 *   2. outbound → não dispatcha
 *   3. internal_note=true → não dispatcha
 *   4. type=image|audio (não-text) → não dispatcha
 *   5. body vazio → não dispatcha
 *   6. business_id fora da allowlist → não dispatcha
 *   7. happy path (text inbound em biz permitido) → DISPATCHA
 *
 * Não testa a chamada real ao laravel/ai — esse é smoke prod e/ou unit
 * test isolado do Service (US futura quando ProviderFake estiver pronto).
 *
 * @see Modules/Whatsapp/Listeners/AnalisarMensagemInboundComJana.php
 */
beforeEach(function () {
    Schema::dropIfExists('messages');
    Schema::create('messages', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->unsignedBigInteger('conversation_id');
        $table->string('direction', 20);
        $table->string('provider', 20)->nullable();
        $table->string('provider_message_id', 128)->nullable();
        $table->string('type', 20)->default('text');
        $table->text('body')->nullable();
        $table->json('payload')->nullable();
        $table->string('status', 20)->default('received');
        $table->boolean('is_internal_note')->default(false);
        $table->timestamps();
    });

    Config::set('whatsapp.analise.enabled', true);
    Config::set('whatsapp.analise.enabled_business_ids', []);
});

function makeMessage(array $overrides = []): Message
{
    $m = new Message();
    $m->id = $overrides['id'] ?? 1;
    $m->business_id = $overrides['business_id'] ?? 1;
    $m->conversation_id = $overrides['conversation_id'] ?? 100;
    $m->direction = $overrides['direction'] ?? Message::DIRECTION_INBOUND;
    $m->type = $overrides['type'] ?? 'text';
    $m->body = $overrides['body'] ?? 'Olá, gostaria de informação sobre o pedido';
    $m->is_internal_note = $overrides['is_internal_note'] ?? false;
    $m->status = $overrides['status'] ?? Message::STATUS_RECEIVED;
    return $m;
}

it('não dispatcha quando flag global desligada', function () {
    Config::set('whatsapp.analise.enabled', false);
    Bus::fake();

    $listener = new AnalisarMensagemInboundComJana();
    $listener->handle(new OmnichannelMessageReceived(makeMessage()));

    Bus::assertNotDispatched(AnalisarMensagemInboundJob::class);
});

it('não dispatcha pra msg outbound', function () {
    Bus::fake();

    $listener = new AnalisarMensagemInboundComJana();
    $listener->handle(new OmnichannelMessageReceived(makeMessage([
        'direction' => Message::DIRECTION_OUTBOUND,
    ])));

    Bus::assertNotDispatched(AnalisarMensagemInboundJob::class);
});

it('não dispatcha nota interna (is_internal_note=true)', function () {
    Bus::fake();

    $listener = new AnalisarMensagemInboundComJana();
    $listener->handle(new OmnichannelMessageReceived(makeMessage([
        'is_internal_note' => true,
    ])));

    Bus::assertNotDispatched(AnalisarMensagemInboundJob::class);
});

it('não dispatcha tipo não suportado (image/audio/video)', function () {
    Bus::fake();

    foreach (['image', 'audio', 'video', 'document', 'location'] as $t) {
        $listener = new AnalisarMensagemInboundComJana();
        $listener->handle(new OmnichannelMessageReceived(makeMessage([
            'type' => $t,
        ])));
    }

    Bus::assertNotDispatched(AnalisarMensagemInboundJob::class);
});

it('não dispatcha body vazio', function () {
    Bus::fake();

    $listener = new AnalisarMensagemInboundComJana();
    $listener->handle(new OmnichannelMessageReceived(makeMessage(['body' => '   '])));

    Bus::assertNotDispatched(AnalisarMensagemInboundJob::class);
});

it('respeita allowlist per-business (biz=99 NÃO permitido com allowlist=[1])', function () {
    Config::set('whatsapp.analise.enabled_business_ids', [1]);
    Bus::fake();

    $listener = new AnalisarMensagemInboundComJana();
    $listener->handle(new OmnichannelMessageReceived(makeMessage([
        'business_id' => 99,
    ])));

    Bus::assertNotDispatched(AnalisarMensagemInboundJob::class);
});

it('happy path: text inbound biz permitido → DISPATCHA', function () {
    Config::set('whatsapp.analise.enabled_business_ids', [1]);
    Bus::fake();

    $listener = new AnalisarMensagemInboundComJana();
    $listener->handle(new OmnichannelMessageReceived(makeMessage([
        'id' => 42,
        'business_id' => 1,
        'body' => 'Quero reclamar do atraso da minha entrega',
    ])));

    Bus::assertDispatched(AnalisarMensagemInboundJob::class, function ($job) {
        return $job->businessId === 1 && $job->messageId === 42;
    });
});

it('allowlist vazia = permite todos os business', function () {
    Config::set('whatsapp.analise.enabled_business_ids', []);
    Bus::fake();

    $listener = new AnalisarMensagemInboundComJana();
    foreach ([1, 4, 99] as $biz) {
        $listener->handle(new OmnichannelMessageReceived(makeMessage([
            'id' => $biz,
            'business_id' => $biz,
        ])));
    }

    Bus::assertDispatchedTimes(AnalisarMensagemInboundJob::class, 3);
});
