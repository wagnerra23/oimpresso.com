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
use Modules\Whatsapp\Services\Canais\MensagemRecebida;
use Modules\Whatsapp\Services\Canais\NormalizadorMetaGraph;
use Modules\Whatsapp\Services\Canais\RecebimentoNoCanal;
use Modules\Whatsapp\Services\Centrifugo\CentrifugoPublisher;
use Tests\Support\WithSeededTenant;

/**
 * Instagram, Messenger e e-mail na mesma Caixa Unificada (US-WA-329 · ADR 0135).
 *
 * Âncora: SPEC US-WA-329 (critérios 1-4) e charter da Caixa Unificada
 * (Multi-tenant Tier 0 + "uma caixa só"). O payload Meta segue o formato
 * documentado do webhook Messenger/Instagram (`entry[].messaging[]`).
 *
 * Tenant 98 contra 99 (ADR 0358). ⚠️ SKIP em SQLite (LC-13).
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
    $this->avisos = [];
    $this->mock(CentrifugoPublisher::class, function ($m) {
        $m->shouldReceive('publish')->andReturnUsing(function ($canal, $dados) {
            $this->avisos[] = [$canal, $dados['type']];

            return true;
        });
    });
    $this->recebimento = app(RecebimentoNoCanal::class);
});

function omniCanal(int $biz, string $tipo): Channel
{
    return Channel::query()->withoutGlobalScopes()->forceCreate([
        'business_id' => $biz, 'channel_uuid' => (string) Str::uuid(), 'label' => "Teste {$tipo}",
        'type' => $tipo, 'status' => 'active', 'channel_health' => 'healthy',
        'display_identifier' => $tipo.'-'.Str::random(8),
    ]);
}

function omniDm(string $cliente, string $mid, string $texto, bool $eco = false): array
{
    return ['object' => 'instagram', 'entry' => [[
        'id' => '17841400000000000', 'time' => 1762902671000,
        'messaging' => [[
            'sender' => ['id' => $eco ? '17841400000000000' : $cliente],
            'recipient' => ['id' => $eco ? $cliente : '17841400000000000'],
            'timestamp' => 1762902671000,
            'message' => array_filter(['mid' => $mid, 'text' => $texto, 'is_echo' => $eco ?: null]),
        ]],
    ]]];
}

it('UC-CXU-20 · US-WA-329 · DM do Instagram vira conversa na mesma tabela do WhatsApp, e o 2º recado cai na mesma conversa', function () {
    $canal = omniCanal($this->biz->id, Channel::TYPE_INSTAGRAM);

    foreach (NormalizadorMetaGraph::normalizar(omniDm('9001', 'mid-1', 'Oi, tem a camiseta P?')) as $e) {
        expect($this->recebimento->receber($canal, $e['mensagem']))->toBe('gravada');
    }
    $conversas = DB::table('conversations')->where('channel_id', $canal->id)->get();
    expect($conversas)->toHaveCount(1)
        ->and((int) $conversas[0]->business_id)->toBe($this->biz->id)
        ->and($conversas[0]->customer_external_id)->toBe('9001')
        ->and($conversas[0]->status)->toBe('open')
        ->and((int) $conversas[0]->unread_count)->toBe(1);

    // Atendente assume; o 2º recado não cria outra conversa nem tira o atendente.
    $atendente = \App\User::factory()->create(['business_id' => $this->biz->id]);
    DB::table('conversations')->where('id', $conversas[0]->id)->update(['assigned_user_id' => $atendente->id]);
    $e = NormalizadorMetaGraph::normalizar(omniDm('9001', 'mid-2', 'E a M?'))[0];
    expect($this->recebimento->receber($canal, $e['mensagem']))->toBe('gravada');

    $conv = DB::table('conversations')->where('channel_id', $canal->id)->get();
    expect($conv)->toHaveCount(1)
        ->and((int) $conv[0]->assigned_user_id)->toBe($atendente->id)
        ->and((int) $conv[0]->unread_count)->toBe(2)
        ->and($conv[0]->last_message_preview)->toBe('E a M?');
    $msgs = DB::table('messages')->where('conversation_id', $conv[0]->id)->orderBy('id')->get();
    expect($msgs->pluck('body')->all())->toBe(['Oi, tem a camiseta P?', 'E a M?'])
        ->and($msgs->pluck('provider')->unique()->all())->toBe(['instagram'])
        ->and($msgs->pluck('direction')->unique()->all())->toBe(['inbound']);
    expect($this->avisos)->toContain(["omnichannel:business:{$this->biz->id}", 'message.received']);
});

it('UC-CXU-20 · US-WA-329 · reentrega do provedor não duplica, e eco da conta da empresa entra como saída sem somar não-lida', function () {
    $canal = omniCanal($this->biz->id, Channel::TYPE_MESSENGER);
    $m = NormalizadorMetaGraph::normalizar(omniDm('psid-7', 'mid-x', 'Olá'))[0]['mensagem'];

    expect($this->recebimento->receber($canal, $m))->toBe('gravada')
        ->and($this->recebimento->receber($canal, $m))->toBe('repetida');

    $eco = NormalizadorMetaGraph::normalizar(omniDm('psid-7', 'mid-y', 'Temos sim!', true))[0]['mensagem'];
    expect($eco->remetenteExterno)->toBe('psid-7')
        ->and($this->recebimento->receber($canal, $eco))->toBe('gravada');

    $conv = DB::table('conversations')->where('channel_id', $canal->id)->get();
    expect($conv)->toHaveCount(1)->and((int) $conv[0]->unread_count)->toBe(1);
    expect(DB::table('messages')->where('conversation_id', $conv[0]->id)->pluck('direction')->all())
        ->toBe(['inbound', 'outbound']);
});

it('UC-CXU-20 · US-WA-329 · o mesmo e-mail em duas empresas gera duas conversas isoladas, cada uma no seu tenant', function () {
    $nosso = omniCanal($this->biz->id, Channel::TYPE_EMAIL_IMAP);
    $deles = omniCanal($this->outra->id, Channel::TYPE_EMAIL_IMAP);
    $email = new MensagemRecebida(
        remetenteExterno: 'cliente@exemplo.test', idNoProvedor: '<abc123@exemplo.test>',
        texto: 'Segue o orçamento.', tipo: 'email', nomeDoContato: 'Cliente', assunto: 'Orçamento',
    );

    expect($this->recebimento->receber($nosso, $email))->toBe('gravada')
        ->and($this->recebimento->receber($deles, $email))->toBe('gravada');

    $nossa = DB::table('conversations')->where('channel_id', $nosso->id)->first();
    $dela = DB::table('conversations')->where('channel_id', $deles->id)->first();
    expect((int) $nossa->business_id)->toBe($this->biz->id)
        ->and((int) $dela->business_id)->toBe($this->outra->id)
        ->and($nossa->contact_name)->toBe('Cliente')
        ->and($nossa->last_message_preview)->toBe('Segue o orçamento.');
    $msg = DB::table('messages')->where('conversation_id', $nossa->id)->first();
    expect($msg->type)->toBe('email')->and($msg->subject)->toBe('Orçamento')
        ->and((int) $msg->business_id)->toBe($this->biz->id);
});

it('UC-CXU-20 · US-WA-329 · conversa bloqueada descarta o recado, recado vazio e canal WhatsApp não entram por aqui', function () {
    $canal = omniCanal($this->biz->id, Channel::TYPE_INSTAGRAM);
    $m = NormalizadorMetaGraph::normalizar(omniDm('9002', 'mid-b1', 'oi'))[0]['mensagem'];
    $this->recebimento->receber($canal, $m);
    DB::table('conversations')->where('channel_id', $canal->id)->update(['is_blocked' => true]);

    $outro = NormalizadorMetaGraph::normalizar(omniDm('9002', 'mid-b2', 'oi de novo'))[0]['mensagem'];
    expect($this->recebimento->receber($canal, $outro))->toBe('bloqueada')
        ->and($this->recebimento->receber($canal, new MensagemRecebida('9002', 'mid-b3', '  ')))->toBe('vazia')
        ->and($this->recebimento->receber(omniCanal($this->biz->id, Channel::TYPE_WHATSAPP_META), $m))->toBe('canal_nao_suportado');
    expect(DB::table('messages')->where('provider_message_id', 'like', 'instagram:'.$canal->id.':mid-b%')->count())->toBe(1);
});

it('UC-CXU-20 · US-WA-329 · evento sem mensagem (leitura, reação) e objeto que não é Instagram/Messenger são ignorados', function () {
    expect(NormalizadorMetaGraph::normalizar(['object' => 'whatsapp_business_account', 'entry' => []]))->toBe([])
        ->and(NormalizadorMetaGraph::normalizar(['object' => 'page', 'entry' => [[
            'id' => '123', 'messaging' => [['sender' => ['id' => 'p'], 'read' => ['watermark' => 1]]],
        ]]]))->toBe([]);
    $img = NormalizadorMetaGraph::normalizar(['object' => 'page', 'entry' => [['id' => '123', 'messaging' => [[
        'sender' => ['id' => 'p'], 'recipient' => ['id' => '123'], 'timestamp' => 1762902671000,
        'message' => ['mid' => 'm1', 'attachments' => [['type' => 'image', 'payload' => ['url' => 'https://cdn.test/a.jpg']]]],
    ]]]]])[0]['mensagem'];
    expect($img->tipo)->toBe('image')->and($img->urlDaMidia)->toBe('https://cdn.test/a.jpg')
        ->and($img->enviadaEm)->toBe(1762902671);
});
