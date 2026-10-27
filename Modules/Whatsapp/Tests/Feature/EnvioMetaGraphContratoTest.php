<?php
// @covers-us US-WA-329

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Entities\Conversation;
use Modules\Whatsapp\Entities\Message;
use Modules\Whatsapp\Events\OmnichannelMessageReceived;
use Modules\Whatsapp\Events\OmnichannelMessageSent;
use Modules\Whatsapp\Services\Canais\MensagemRecebida;
use Modules\Whatsapp\Services\Canais\RecebimentoNoCanal;
use Modules\Whatsapp\Services\Centrifugo\CentrifugoPublisher;
use Modules\Whatsapp\Services\Inbox\EnvioDeTextoNoCanal;
use Tests\Support\WithSeededTenant;

/**
 * Resposta do atendente no Instagram Direct / Messenger — US-WA-329 fatia 3.
 *
 * Âncora: SPEC US-WA-329 (resposta pela Caixa no mesmo caminho do WhatsApp) e o
 * contrato da Send API da Meta (`POST /me/messages`, `recipient.id`,
 * `messaging_type=RESPONSE`, resposta `{recipient_id, message_id}`). Meta falsa
 * (Http::fake) — nada sai de verdade. Tenant 98 (ADR 0358). ⚠️ SKIP em SQLite.
 */
uses(Tests\TestCase::class, DatabaseTransactions::class, WithSeededTenant::class);

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasColumn('messages', 'failed_reason')) {
        $this->markTestSkipped('Requer MySQL com o schema omnichannel.');
    }
    try {
        $this->biz = $this->seededTenant();
    } catch (\Throwable $e) {
        $this->markTestSkipped('Schema UltimatePOS ausente: '.$e->getMessage());
    }
    Event::fake([OmnichannelMessageReceived::class, OmnichannelMessageSent::class]);
    $this->mock(CentrifugoPublisher::class, fn ($m) => $m->shouldReceive('publish')->andReturn(true));
    config(['whatsapp.omnicanal.meta_graph_enabled' => true]);
});

function emgConversa(int $biz, string $tipo, array $config): array
{
    $canal = Channel::query()->withoutGlobalScopes()->forceCreate([
        'business_id' => $biz, 'channel_uuid' => (string) Str::uuid(), 'label' => 'Envio MG',
        'type' => $tipo, 'status' => 'active', 'channel_health' => 'healthy',
        'display_identifier' => $tipo.'-'.Str::random(8), 'config_json' => $config,
    ]);
    $conv = Conversation::query()->withoutGlobalScopes()->forceCreate([
        'business_id' => $biz, 'channel_id' => $canal->id, 'customer_external_id' => 'igsid-55',
        'contact_name' => 'Cliente IG', 'status' => 'open', 'unread_count' => 0,
    ]);
    $msg = Message::query()->withoutGlobalScopes()->forceCreate([
        'business_id' => $biz, 'conversation_id' => $conv->id, 'direction' => 'outbound',
        'provider' => $tipo, 'type' => 'text', 'body' => 'Temos sim!', 'status' => 'queued', 'sender_kind' => 'human',
    ]);

    return [$canal, $conv, $msg];
}

it('UC-CXU-22 · US-WA-329 · resposta no Instagram sai pela Send API para o cliente da conversa, e o eco não duplica', function () {
    Http::fake(['graph.facebook.com/*' => Http::response(['recipient_id' => 'igsid-55', 'message_id' => 'mid-resp-1'])]);
    [$canal, $conv, $msg] = emgConversa($this->biz->id, Channel::TYPE_INSTAGRAM, ['page_access_token' => 'tok-pagina']);

    $r = app(EnvioDeTextoNoCanal::class)->despachar($msg, $conv, $canal, 'Temos sim!');

    expect($r['ok'])->toBeTrue();
    Http::assertSent(fn ($req) => str_ends_with($req->url(), '/me/messages')
        && $req->hasHeader('Authorization', 'Bearer tok-pagina')
        && $req['recipient'] === ['id' => 'igsid-55']
        && $req['messaging_type'] === 'RESPONSE'
        && $req['message'] === ['text' => 'Temos sim!']);
    $msg->refresh();
    expect($msg->status)->toBe('sent')
        ->and($msg->provider_message_id)->toBe("instagram:{$canal->id}:mid-resp-1");

    // A Meta devolve o eco da mesma mensagem pelo webhook: cai como repetida.
    $eco = new MensagemRecebida('igsid-55', 'mid-resp-1', 'Temos sim!', enviadaPorNos: true);
    expect(app(RecebimentoNoCanal::class)->receber($canal, $eco))->toBe('repetida');
    expect(DB::table('messages')->where('conversation_id', $conv->id)->count())->toBe(1);
});

it('UC-CXU-22 · US-WA-329 · sem token, Meta recusando ou flag desligada: a mensagem fica falha com o motivo', function () {
    Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'janela de 24h']], 400)]);

    [$c1, $v1, $m1] = emgConversa($this->biz->id, Channel::TYPE_MESSENGER, []);
    expect(app(EnvioDeTextoNoCanal::class)->despachar($m1, $v1, $c1, 'x')['motivo'])->toBe('sem_token');

    [$c2, $v2, $m2] = emgConversa($this->biz->id, Channel::TYPE_MESSENGER, ['page_access_token' => 't']);
    expect(app(EnvioDeTextoNoCanal::class)->despachar($m2, $v2, $c2, 'x')['motivo'])->toBe('daemon');
    expect($m2->refresh()->status)->toBe('failed')->and($m2->failed_reason)->toContain('Meta 400');

    config(['whatsapp.omnicanal.meta_graph_enabled' => false]);
    [$c3, $v3, $m3] = emgConversa($this->biz->id, Channel::TYPE_MESSENGER, ['page_access_token' => 't']);
    expect(app(EnvioDeTextoNoCanal::class)->despachar($m3, $v3, $c3, 'x')['ok'])->toBeFalse();
    expect($m3->refresh()->status)->toBe('failed');
    Http::assertSentCount(1);
});
