<?php
// @covers-us US-WA-329

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Entities\Conversation;
use Modules\Whatsapp\Entities\Message;
use Modules\Whatsapp\Events\OmnichannelMessageReceived;
use Modules\Whatsapp\Events\OmnichannelMessageSent;
use Modules\Whatsapp\Mail\RespostaDeAtendimentoMail;
use Modules\Whatsapp\Services\Centrifugo\CentrifugoPublisher;
use Modules\Whatsapp\Services\Inbox\EnvioDeTextoNoCanal;
use Tests\Support\WithSeededTenant;

/**
 * E-mail na Caixa Unificada — US-WA-329 fatia 4.
 *
 * Âncora: SPEC US-WA-329 critério 8 e o formato documentado do Postmark Inbound
 * (`FromFull{Email,Name}`, `Subject`, `TextBody`, `MessageID`, Basic Auth na URL).
 * SMTP falso (Mail::fake). Tenant 98 contra 99 (ADR 0358). ⚠️ SKIP em SQLite.
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
    Mail::fake();
    config(['whatsapp.omnicanal.email_enabled' => true]);
});

function emCanal(int $biz, array $config = []): Channel
{
    return Channel::query()->withoutGlobalScopes()->forceCreate([
        'business_id' => $biz, 'channel_uuid' => (string) Str::uuid(), 'label' => 'Atendimento',
        'type' => Channel::TYPE_EMAIL_IMAP, 'status' => 'active', 'channel_health' => 'healthy',
        'display_identifier' => 'contato-'.Str::lower(Str::random(6)).'@loja.test',
        'config_json' => array_merge(['inbound_token' => 'tok-entrada', 'smtp_host' => 'smtp.loja.test'], $config),
    ]);
}

function emPostmark(string $id, string $assunto = 'Orçamento de banner'): array
{
    return [
        'From' => 'Maria <maria@cliente.test>', 'FromFull' => ['Email' => 'Maria@Cliente.test', 'Name' => 'Maria'],
        'To' => 'contato@loja.test', 'Subject' => $assunto, 'TextBody' => 'Quanto fica 2x1m?', 'MessageID' => $id,
    ];
}

it('UC-CXU-23 · US-WA-329 · e-mail encaminhado com o token do canal vira conversa da empresa; token errado não grava', function () {
    $canal = emCanal($this->biz->id);
    emCanal($this->outra->id);
    $url = "/api/atendimento/channels/email/{$canal->channel_uuid}";

    $this->postJson($url, emPostmark('pm-1'), ['X-Inbound-Token' => 'errado'])->assertStatus(401);
    expect(DB::table('conversations')->where('channel_id', $canal->id)->count())->toBe(0);

    $this->withHeaders(['Authorization' => 'Basic '.base64_encode('postmark:tok-entrada')])
        ->postJson($url, emPostmark('pm-1'))->assertOk()->assertJsonPath('resultado', 'gravada');
    $this->postJson($url, emPostmark('pm-1'), ['X-Inbound-Token' => 'tok-entrada'])->assertJsonPath('resultado', 'repetida');

    $conv = DB::table('conversations')->where('customer_external_id', 'maria@cliente.test')->get();
    expect($conv)->toHaveCount(1)
        ->and((int) $conv[0]->business_id)->toBe($this->biz->id)
        ->and($conv[0]->contact_name)->toBe('Maria');
    $msg = DB::table('messages')->where('conversation_id', $conv[0]->id)->first();
    expect($msg->type)->toBe('email')->and($msg->subject)->toBe('Orçamento de banner')->and($msg->body)->toBe('Quanto fica 2x1m?');
});

it('UC-CXU-23 · US-WA-329 · flag desligada ou canal que não é de e-mail responde 404', function () {
    $canal = emCanal($this->biz->id);
    config(['whatsapp.omnicanal.email_enabled' => false]);
    $this->postJson("/api/atendimento/channels/email/{$canal->channel_uuid}", emPostmark('pm-2'), ['X-Inbound-Token' => 'tok-entrada'])
        ->assertNotFound();
    expect(DB::table('conversations')->where('channel_id', $canal->id)->count())->toBe(0);
});

it('UC-CXU-23 · US-WA-329 · a resposta do atendente sai pelo SMTP da caixa da empresa, com "Re:" do assunto', function () {
    $canal = emCanal($this->biz->id);
    $this->postJson("/api/atendimento/channels/email/{$canal->channel_uuid}", emPostmark('pm-3'), ['X-Inbound-Token' => 'tok-entrada'])->assertOk();
    $conv = Conversation::query()->withoutGlobalScopes()->where('channel_id', $canal->id)->firstOrFail();
    $msg = Message::query()->withoutGlobalScopes()->forceCreate([
        'business_id' => $this->biz->id, 'conversation_id' => $conv->id, 'direction' => 'outbound',
        'provider' => $canal->type, 'type' => 'text', 'body' => 'Fica pronto amanhã.', 'status' => 'queued', 'sender_kind' => 'human',
    ]);

    $r = app(EnvioDeTextoNoCanal::class)->despachar($msg, $conv, $canal, 'Fica pronto amanhã.');

    expect($r['ok'])->toBeTrue();
    Mail::assertSent(RespostaDeAtendimentoMail::class, fn ($m) => $m->hasTo('maria@cliente.test')
        && $m->hasFrom($canal->display_identifier)
        && $m->assunto === 'Re: Orçamento de banner'
        && $m->businessId === $this->biz->id
        && $m->mailer === "omnicanal_{$canal->id}");
    expect(config("mail.mailers.omnicanal_{$canal->id}.host"))->toBe('smtp.loja.test');
    $msg->refresh();
    expect($msg->status)->toBe('sent')->and($msg->type)->toBe('email')
        ->and($msg->provider_message_id)->toStartWith("email_imap:{$canal->id}:<");
});

it('UC-CXU-23 · US-WA-329 · sem SMTP configurado a resposta fica falha, e nada é enviado', function () {
    $canal = emCanal($this->biz->id, ['smtp_host' => null]);
    $conv = Conversation::query()->withoutGlobalScopes()->forceCreate([
        'business_id' => $this->biz->id, 'channel_id' => $canal->id, 'customer_external_id' => 'x@cliente.test',
        'status' => 'open', 'unread_count' => 0,
    ]);
    $msg = Message::query()->withoutGlobalScopes()->forceCreate([
        'business_id' => $this->biz->id, 'conversation_id' => $conv->id, 'direction' => 'outbound',
        'provider' => $canal->type, 'type' => 'text', 'body' => 'oi', 'status' => 'queued', 'sender_kind' => 'human',
    ]);

    expect(app(EnvioDeTextoNoCanal::class)->despachar($msg, $conv, $canal, 'oi')['motivo'])->toBe('sem_token');
    expect($msg->refresh()->status)->toBe('failed');
    Mail::assertNothingSent();
});
