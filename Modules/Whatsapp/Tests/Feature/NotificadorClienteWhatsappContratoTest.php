<?php
// @covers-us US-WA-319

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Jobs\DespacharMensagemDoSistemaJob;
use Modules\Whatsapp\Services\Inbox\EnvioDeTextoNoCanal;
use Modules\Whatsapp\Services\Notify\NotificadorClienteWhatsapp;
use Modules\Whatsapp\Services\Notify\ResultadoNotificacao;
use Tests\Support\WithSeededTenant;

/**
 * Notificador reutilizável de aviso automático por WhatsApp (US-WA-319).
 *
 * Âncora: SPEC US-WA-319 (critérios 1-6) e a regra LGPD do sistema —
 * `Contact::canReceiveWhatsappNotification()`: opt-out explícito bloqueia, null permite.
 * Envia pelo schema omnichannel (`channels`), o vivo em produção. Daemon falso.
 *
 * Tenant 98 contra 99 (ADR 0358). ⚠️ SKIP em SQLite (LC-13).
 */
uses(Tests\TestCase::class, DatabaseTransactions::class, WithSeededTenant::class);

const NOTIF_DAEMON = 'https://daemon-falso-notificador.test';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite'
        || ! Schema::hasColumn('contacts', 'whatsapp_consent')
        || ! Schema::hasColumn('channels', 'handles_billing')) {
        $this->markTestSkipped('Requer MySQL com consentimento em contacts e channels.handles_*.');
    }
    try {
        $this->biz = $this->seededTenant();
        $this->outra = $this->seededSupportClientTenant();
    } catch (\Throwable $e) {
        $this->markTestSkipped('Schema UltimatePOS ausente: '.$e->getMessage());
    }
    Bus::fake([DespacharMensagemDoSistemaJob::class]);
    $this->criador = \App\User::factory()->create(['business_id' => $this->biz->id]);
    $this->notificador = app(NotificadorClienteWhatsapp::class);
});

function notifCanal(int $biz, array $flags = []): int
{
    return (int) Channel::query()->withoutGlobalScopes()->forceCreate(array_merge([
        'business_id' => $biz, 'channel_uuid' => (string) Str::uuid(), 'label' => 'Notif teste',
        'type' => Channel::TYPE_WHATSAPP_WHATSMEOW, 'status' => 'active', 'channel_health' => 'healthy',
        'config_json' => ['whatsmeow_user_token' => 'token-falso'],
        'display_identifier' => '+55 47 3'.random_int(100, 999).'-'.random_int(1000, 9999),
    ], $flags))->id;
}

/** "Sem celular" é string vazia: `contacts.mobile` é NOT NULL no schema. */
function notifContato(int $biz, int $criadoPor, string $mobile, ?bool $consente): int
{
    return (int) DB::table('contacts')->insertGetId([
        'business_id' => $biz, 'type' => 'customer', 'name' => 'Cliente Notif', 'mobile' => $mobile,
        'whatsapp_consent' => $consente, 'contact_status' => 'active', 'created_by' => $criadoPor,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function notifMensagens(int $biz): \Illuminate\Support\Collection
{
    return DB::table('messages')->where('business_id', $biz)->where('direction', 'outbound')->where('sender_kind', 'system')->orderBy('id')->get();
}

it('US-WA-319 · enfileira a mensagem na conversa do cliente, com os anexos como link, pelo canal de saída', function () {
    $canal = notifCanal($this->biz->id, ['handles_outbound_default' => true]);
    notifCanal($this->outra->id, ['handles_outbound_default' => true]);
    $cliente = notifContato($this->biz->id, $this->criador->id, '(48) 98888-7777', null);

    $r = $this->notificador->notificar($this->biz->id, $cliente, 'outbound_default', 'Sua compra foi registrada.', [
        ['url' => 'https://exemplo.test/fatura.pdf', 'type' => 'document', 'caption' => 'Fatura'],
    ]);

    expect($r)->toBe(ResultadoNotificacao::Enviado);
    $msgs = notifMensagens($this->biz->id);
    expect($msgs)->toHaveCount(1)
        ->and($msgs[0]->body)->toBe("Sua compra foi registrada.\n\nFatura: https://exemplo.test/fatura.pdf")
        ->and($msgs[0]->status)->toBe('queued');
    $conversa = DB::table('conversations')->where('id', $msgs[0]->conversation_id)->first();
    expect((int) $conversa->business_id)->toBe($this->biz->id)
        ->and((int) $conversa->channel_id)->toBe($canal)
        ->and((int) $conversa->contact_id)->toBe($cliente)
        ->and($conversa->customer_external_id)->toBe('+5548988887777');
    Bus::assertDispatched(DespacharMensagemDoSistemaJob::class, fn ($j) => $j->businessId === $this->biz->id && $j->messageId === (int) $msgs[0]->id);

    // 2º aviso reaproveita a mesma conversa.
    $this->notificador->notificar($this->biz->id, $cliente, 'outbound_default', 'De novo.');
    expect(notifMensagens($this->biz->id)->pluck('conversation_id')->unique()->count())->toBe(1);
});

it('US-WA-319 · cobrança prefere o canal marcado para cobrança', function () {
    notifCanal($this->biz->id, ['handles_outbound_default' => true]);
    $financeiro = notifCanal($this->biz->id, ['handles_billing' => true]);
    $cliente = notifContato($this->biz->id, $this->criador->id, '48988887777', true);

    expect($this->notificador->notificar($this->biz->id, $cliente, 'billing', 'Seu boleto vence amanhã.'))->toBe(ResultadoNotificacao::Enviado);
    $conv = notifMensagens($this->biz->id)->first()->conversation_id;
    expect((int) DB::table('conversations')->where('id', $conv)->value('channel_id'))->toBe($financeiro);
});

it('US-WA-319 · contato de outra empresa, recusa LGPD, sem celular, canal caído ou de outra empresa: não envia e diz o motivo', function () {
    notifCanal($this->outra->id, ['handles_outbound_default' => true, 'handles_billing' => true]);
    $ok = notifContato($this->biz->id, $this->criador->id, '48933334444', null);
    expect($this->notificador->notificar($this->biz->id, $ok, 'billing', 'x'))->toBe(ResultadoNotificacao::SemNumeroConfigurado);

    $canal = notifCanal($this->biz->id, ['handles_outbound_default' => true]);
    $daOutra = notifContato($this->outra->id, $this->criador->id, '48911110000', true);
    $recusou = notifContato($this->biz->id, $this->criador->id, '48911112222', false);
    $semCelular = notifContato($this->biz->id, $this->criador->id, '', true);

    expect($this->notificador->notificar($this->biz->id, $daOutra, 'outbound_default', 'x'))->toBe(ResultadoNotificacao::ContatoNaoEncontrado)
        ->and($this->notificador->notificar($this->biz->id, $recusou, 'outbound_default', 'x'))->toBe(ResultadoNotificacao::RecusouWhatsapp)
        ->and($this->notificador->notificar($this->biz->id, $semCelular, 'outbound_default', 'x'))->toBe(ResultadoNotificacao::SemCelular);

    DB::table('channels')->where('id', $canal)->update(['channel_health' => 'disconnected']);
    expect($this->notificador->notificar($this->biz->id, $ok, 'outbound_default', 'x'))->toBe(ResultadoNotificacao::SemNumeroConfigurado);
    expect(notifMensagens($this->biz->id))->toHaveCount(0);

    // Controle: o mesmo cliente, com o canal de volta, recebe.
    DB::table('channels')->where('id', $canal)->update(['channel_health' => 'healthy']);
    expect($this->notificador->notificar($this->biz->id, $ok, 'outbound_default', 'x'))->toBe(ResultadoNotificacao::Enviado);
    expect(notifMensagens($this->biz->id))->toHaveCount(1);
});

it('US-WA-319 · o job envia ao daemon uma vez só', function () {
    config(['whatsapp.whatsmeow.daemon_url' => NOTIF_DAEMON]);
    Http::fake([NOTIF_DAEMON.'/*' => Http::response(['Details' => 'Sent', 'Id' => 'WAM'.Str::upper(Str::random(8))])]);
    notifCanal($this->biz->id, ['handles_outbound_default' => true]);
    $cliente = notifContato($this->biz->id, $this->criador->id, '48988887777', true);
    $this->notificador->notificar($this->biz->id, $cliente, 'outbound_default', 'Pedido pronto.');
    $id = (int) notifMensagens($this->biz->id)->first()->id;

    (new DespacharMensagemDoSistemaJob($this->biz->id, $id))->handle(app(EnvioDeTextoNoCanal::class));
    (new DespacharMensagemDoSistemaJob($this->biz->id, $id))->handle(app(EnvioDeTextoNoCanal::class));
    (new DespacharMensagemDoSistemaJob($this->outra->id, $id))->handle(app(EnvioDeTextoNoCanal::class));

    expect(count(Http::recorded()))->toBe(1)
        ->and(DB::table('messages')->where('id', $id)->value('status'))->toBe('sent');
    expect(Http::recorded()[0][0]->data()['Phone'])->toBe('5548988887777');
});

it('US-WA-319 · anexo sem URL absoluta, de tipo desconhecido ou evento inventado é erro de quem chamou', function () {
    notifCanal($this->biz->id, ['handles_outbound_default' => true]);
    $cliente = notifContato($this->biz->id, $this->criador->id, '48988887777', true);

    expect(fn () => $this->notificador->notificar($this->biz->id, $cliente, 'outbound_default', 'x', [['url' => '/storage/a.pdf']]))
        ->toThrow(\InvalidArgumentException::class);
    expect(fn () => $this->notificador->notificar($this->biz->id, $cliente, 'outbound_default', 'x', [['url' => 'https://a.test/a.mp3', 'type' => 'audio']]))
        ->toThrow(\InvalidArgumentException::class);
    expect(fn () => $this->notificador->notificar($this->biz->id, $cliente, 'evento_inventado', 'x'))
        ->toThrow(\InvalidArgumentException::class);
    expect(notifMensagens($this->biz->id))->toHaveCount(0);
});

// ── O contrato do núcleo AvisaClienteWhatsapp está ligado ao notificador (consumidores: oficina, prova da arte) ──

it('US-WA-319 · o contrato AvisaClienteWhatsapp está ligado e enfileira pelo canal de saída', function () {
    expect(app()->bound(\App\Contracts\Atendimento\AvisaClienteWhatsapp::class))->toBeTrue();
    notifCanal($this->biz->id, ['handles_outbound_default' => true]);
    $cliente = notifContato($this->biz->id, $this->criador->id, '(48) 98888-7777', null);

    $r = app(\App\Contracts\Atendimento\AvisaClienteWhatsapp::class)->avisar($this->biz->id, $cliente, 'A prova da arte está pronta.');

    expect($r)->toBe(['enviado' => true, 'motivo' => null]);
    expect(notifMensagens($this->biz->id)->pluck('body')->all())->toContain('A prova da arte está pronta.');
});

it('US-WA-319 · pelo contrato, cliente de outra empresa não recebe e o motivo é o documentado', function () {
    notifCanal($this->biz->id, ['handles_outbound_default' => true]);
    $alheio = notifContato($this->outra->id, $this->criador->id, '(48) 98888-7777', null);
    $avisa = app(\App\Contracts\Atendimento\AvisaClienteWhatsapp::class);

    expect($avisa->avisar($this->biz->id, $alheio, 'x'))->toBe(['enviado' => false, 'motivo' => 'contato_de_outra_empresa'])
        ->and(notifMensagens($this->biz->id))->toHaveCount(0);
    $semConsentimento = notifContato($this->biz->id, $this->criador->id, '(48) 98888-6666', false);
    expect($avisa->avisar($this->biz->id, $semConsentimento, 'x'))->toBe(['enviado' => false, 'motivo' => 'sem_consentimento']);
});
