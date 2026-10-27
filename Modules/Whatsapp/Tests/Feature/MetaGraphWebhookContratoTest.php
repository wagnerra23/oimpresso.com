<?php
// @covers-us US-WA-329

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Events\OmnichannelMessageReceived;
use Modules\Whatsapp\Events\OmnichannelMessageSent;
use Modules\Whatsapp\Services\Centrifugo\CentrifugoPublisher;
use Tests\Support\WithSeededTenant;

/**
 * Webhook Meta Graph (Instagram Direct e Messenger) por canal — US-WA-329 fatia 2.
 *
 * Âncora: SPEC US-WA-329 critério 5 e o contrato de webhook da Meta (GET de
 * inscrição com hub.verify_token; POST assinado em X-Hub-Signature-256 com o
 * app secret). Tenant 98 contra 99 (ADR 0358). ⚠️ SKIP em SQLite (LC-13).
 */
uses(Tests\TestCase::class, DatabaseTransactions::class, WithSeededTenant::class);

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasColumn('messages', 'subject')) {
        $this->markTestSkipped('Requer MySQL com o schema omnichannel.');
    }
    try {
        $this->biz = $this->seededTenant();
        $this->outra = $this->seededSupportClientTenant();
    } catch (\Throwable $e) {
        $this->markTestSkipped('Schema UltimatePOS ausente: '.$e->getMessage());
    }
    Event::fake([OmnichannelMessageReceived::class, OmnichannelMessageSent::class]);
    $this->mock(CentrifugoPublisher::class, fn ($m) => $m->shouldReceive('publish')->andReturn(true));
    config(['whatsapp.omnicanal.meta_graph_enabled' => true]);
});

function mgCanal(int $biz, string $tipo, array $config = []): Channel
{
    return Channel::query()->withoutGlobalScopes()->forceCreate([
        'business_id' => $biz, 'channel_uuid' => (string) Str::uuid(), 'label' => "MG {$tipo}",
        'type' => $tipo, 'status' => 'active', 'channel_health' => 'healthy',
        'display_identifier' => $tipo.'-'.Str::random(8),
        'config_json' => array_merge(['verify_token' => 'tok-certo', 'app_secret' => 'segredo-do-app'], $config),
    ]);
}

function mgPost($test, Channel $canal, array $payload, string $segredo = 'segredo-do-app')
{
    $corpo = json_encode($payload);

    return $test->call('POST', "/api/atendimento/channels/meta-graph/{$canal->channel_uuid}", [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $corpo, $segredo),
    ], $corpo);
}

function mgDm(string $objeto, string $conta, string $cliente, string $mid, string $texto): array
{
    return ['object' => $objeto, 'entry' => [['id' => $conta, 'time' => 1762902671000, 'messaging' => [[
        'sender' => ['id' => $cliente], 'recipient' => ['id' => $conta], 'timestamp' => 1762902671000,
        'message' => ['mid' => $mid, 'text' => $texto],
    ]]]]];
}

it('UC-CXU-21 · US-WA-329 · a inscrição da Meta só confirma com o verify token do canal', function () {
    $canal = mgCanal($this->biz->id, Channel::TYPE_INSTAGRAM);
    $url = "/api/atendimento/channels/meta-graph/{$canal->channel_uuid}";

    $ok = $this->get($url.'?hub.mode=subscribe&hub.verify_token=tok-certo&hub.challenge=4242');
    $ok->assertOk();
    expect($ok->getContent())->toBe('4242');
    $this->get($url.'?hub.mode=subscribe&hub.verify_token=tok-errado&hub.challenge=4242')->assertForbidden();
});

it('UC-CXU-21 · US-WA-329 · DM assinada vira conversa da empresa dona do canal; assinatura errada não grava nada', function () {
    $canal = mgCanal($this->biz->id, Channel::TYPE_INSTAGRAM, ['account_id' => '1784']);
    mgCanal($this->outra->id, Channel::TYPE_INSTAGRAM, ['account_id' => '1784']);

    mgPost($this, $canal, mgDm('instagram', '1784', '9001', 'mid-a', 'Oi'), 'outro-segredo')->assertStatus(401);
    expect(DB::table('conversations')->where('channel_id', $canal->id)->count())->toBe(0);

    mgPost($this, $canal, mgDm('instagram', '1784', '9001', 'mid-a', 'Oi'))->assertOk();
    $conv = DB::table('conversations')->where('customer_external_id', '9001')->get();
    expect($conv)->toHaveCount(1)
        ->and((int) $conv[0]->business_id)->toBe($this->biz->id)
        ->and((int) $conv[0]->channel_id)->toBe($canal->id);

    // Reentrega da Meta: 200 de novo, sem duplicar.
    mgPost($this, $canal, mgDm('instagram', '1784', '9001', 'mid-a', 'Oi'))->assertOk();
    expect(DB::table('messages')->where('conversation_id', $conv[0]->id)->count())->toBe(1);
});

it('UC-CXU-21 · US-WA-329 · entrada de outra conta ou de Messenger num canal Instagram é ignorada', function () {
    $canal = mgCanal($this->biz->id, Channel::TYPE_INSTAGRAM, ['account_id' => '1784']);

    mgPost($this, $canal, mgDm('instagram', '9999', 'c1', 'mid-1', 'outra conta'))->assertOk();
    mgPost($this, $canal, mgDm('page', '1784', 'c2', 'mid-2', 'é Messenger'))->assertOk();
    expect(DB::table('conversations')->where('channel_id', $canal->id)->count())->toBe(0);
});

it('UC-CXU-21 · US-WA-329 · com a flag desligada, canal inativo ou canal WhatsApp, a rota responde 404', function () {
    $canal = mgCanal($this->biz->id, Channel::TYPE_MESSENGER);
    $whats = mgCanal($this->biz->id, Channel::TYPE_WHATSAPP_META);
    $inativo = mgCanal($this->biz->id, Channel::TYPE_MESSENGER);
    DB::table('channels')->where('id', $inativo->id)->update(['status' => 'inactive']);

    mgPost($this, $whats, mgDm('page', '1', 'c', 'm1', 'x'))->assertNotFound();
    mgPost($this, $inativo, mgDm('page', '1', 'c', 'm2', 'x'))->assertNotFound();

    config(['whatsapp.omnicanal.meta_graph_enabled' => false]);
    mgPost($this, $canal, mgDm('page', '1', 'c', 'm3', 'x'))->assertNotFound();
    expect(DB::table('messages')->where('provider_message_id', 'like', 'messenger:%')->where('business_id', $this->biz->id)->count())->toBe(0);
});
