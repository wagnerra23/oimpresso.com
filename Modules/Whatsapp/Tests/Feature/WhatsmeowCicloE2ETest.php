<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Http\Controllers\Api\WhatsmeowWebhookController;
use Modules\Whatsapp\Jobs\ProcessIncomingWebhookJob;
use Modules\Whatsapp\Services\Centrifugo\CentrifugoPublisher;

uses(Tests\TestCase::class);

/**
 * WhatsmeowCicloE2ETest — gate de regressão do CICLO de vida do canal ("gates que mordem").
 *
 * Dirige o WhatsmeowWebhookController REAL (controller + reconciler + DB + publish)
 * pela sequência parear → mensagem → logout → recuperar, com Centrifugo e Queue
 * fakeados. Um E2E *real* (WhatsApp de verdade) precisa de humano + número; este
 * cobre todo o app-side que NÓS controlamos — qualquer regressão num elo do ciclo
 * quebra aqui. Cobre os bugs catalogados nesta sessão (Phase B + ADR 0287):
 *  - Connected/PairSuccess → channel_health=healthy + publish realtime
 *  - Message de conversa → enfileira ProcessIncomingWebhookJob (não dropa)
 *  - LoggedOut (reason real com espaços) → disconnected, NÃO banned (ADR 0287)
 *  - reconexão → healthy (recupera)
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('schema sintético manual incompatível com MySQL persistente.');
    }

    Schema::dropIfExists('channels');
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
        $table->timestamp('lgpd_acknowledged_at')->nullable();
        $table->unsignedInteger('lgpd_acknowledged_by_user_id')->nullable();
        $table->timestamps();
    });
});

function cicloChannel(int $bizId = 1): Channel
{
    return Channel::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => $bizId,
        'channel_uuid' => sprintf('cic%05d-0000-0000-0000-%012d', $bizId, random_int(1, 999999)),
        'label' => 'Ciclo',
        'type' => Channel::TYPE_WHATSAPP_WHATSMEOW,
        'status' => 'setup',
        'channel_health' => 'never_checked',
    ]);
}

function whatsmeowEvent(int $bizId, Channel $channel, array $body): Request
{
    $req = Request::create('/api/whatsapp/webhook/whatsmeow/uuid', 'POST', $body);
    $req->attributes->set('whatsapp.business_id', $bizId);
    $req->attributes->set('whatsapp.channel', $channel);

    return $req;
}

it('ciclo completo: parear → mensagem → logout(≠ban) → recuperar', function () {
    Queue::fake();
    $spy = Mockery::spy(CentrifugoPublisher::class);
    app()->instance(CentrifugoPublisher::class, $spy);

    $ch = cicloChannel(1);
    $ctrl = app(WhatsmeowWebhookController::class);

    // 1) PAREAR — Connected → healthy + active
    $ctrl->handle(whatsmeowEvent(1, $ch, ['type' => 'Connected', 'Data' => ['Jid' => '554899999@s.whatsapp.net']]));
    $ch->refresh();
    expect($ch->channel_health)->toBe('healthy');
    expect($ch->status)->toBe('active');

    // 2) MENSAGEM — Message de conversa → enfileira processamento (não dropa)
    $ctrl->handle(whatsmeowEvent(1, $ch, ['type' => 'Message', 'Chat' => '554811111@s.whatsapp.net']));
    Queue::assertPushed(ProcessIncomingWebhookJob::class);

    // 3) LOGOUT — reason REAL (com espaços) → disconnected, NÃO banned (ADR 0287)
    $ctrl->handle(whatsmeowEvent(1, $ch, ['type' => 'LoggedOut', 'Data' => ['Reason' => '401: logged out from another device']]));
    $ch->refresh();
    expect($ch->channel_health)->toBe('disconnected');

    // 4) RECUPERAR — Connected de novo → healthy
    $ctrl->handle(whatsmeowEvent(1, $ch, ['type' => 'Connected', 'Data' => ['Jid' => '554899999@s.whatsapp.net']]));
    $ch->refresh();
    expect($ch->channel_health)->toBe('healthy');

    // realtime publicado nos eventos de estado (paired + disconnected + paired)
    $spy->shouldHaveReceived('publish');
});

it('logout NÃO é ban (reason com espaços ≠ banKeyword com underscore)', function () {
    $spy = Mockery::spy(CentrifugoPublisher::class);
    app()->instance(CentrifugoPublisher::class, $spy);

    $ch = cicloChannel(1);
    $ch->forceFill(['status' => 'active', 'channel_health' => 'healthy'])->save();

    app(WhatsmeowWebhookController::class)->handle(
        whatsmeowEvent(1, $ch, ['type' => 'LoggedOut', 'Data' => ['Reason' => 'logged out from another device']])
    );
    $ch->refresh();

    expect($ch->channel_health)->toBe('disconnected')
        ->and($ch->channel_health)->not->toBe('banned');
});
