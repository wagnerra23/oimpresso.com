<?php
// @covers-us US-WA-322

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Events\OmnichannelMessageReceived;
use Modules\Whatsapp\Events\OmnichannelMessageSent;
use Modules\Whatsapp\Jobs\DispararBroadcastJob;
use Modules\Whatsapp\Jobs\EnviarItemBroadcastJob;
use Modules\Whatsapp\Services\Broadcast\PlanoDeDisparo;
use Modules\Whatsapp\Services\Inbox\EnvioDeTextoNoCanal;
use Tests\Support\WithSeededTenant;

/**
 * Disparo real de campanha por WhatsApp com salvaguardas anti-ban (US-WA-322 · ADR 0268 fase 2).
 *
 * Âncora: SPEC US-WA-322 (critérios 1-6) e a regra LGPD da ADR 0268 (sem `whatsapp_opt_in_at`,
 * fora de campanha) + `Contact::canReceiveWhatsappNotification()`. NENHUMA mensagem real sai:
 * daemon falso (`Http::fake` + `preventStrayRequests`).
 *
 * Tenant 98 contra 99 (ADR 0358). ⚠️ SKIP em SQLite (LC-13).
 */
uses(Tests\TestCase::class, DatabaseTransactions::class, WithSeededTenant::class);

const BCD_DAEMON = 'https://daemon-falso-broadcast.test';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite'
        || ! Schema::hasColumn('whatsapp_broadcasts', 'total')
        || ! Schema::hasColumn('contacts', 'whatsapp_opt_in_at')) {
        $this->markTestSkipped('Requer MySQL com whatsapp_broadcasts (contadores) e contacts.whatsapp_opt_in_at.');
    }
    try {
        $this->biz = $this->seededTenant();
        $this->outra = $this->seededSupportClientTenant();
    } catch (\Throwable $e) {
        $this->markTestSkipped('Schema UltimatePOS ausente: '.$e->getMessage());
    }
    config(['whatsapp.baileys.daemon_url' => BCD_DAEMON, 'whatsapp.baileys.api_key' => 'chave-falsa',
        'whatsapp.broadcast.disparo_enabled' => true]);
    Http::preventStrayRequests();
    Http::fake([BCD_DAEMON.'/*' => fn () => Http::response(['message_id' => 'BCD-'.Str::uuid(), 'status' => 'sent'])]);
    Event::fake([OmnichannelMessageSent::class, OmnichannelMessageReceived::class]);
    $this->criador = \App\User::factory()->create(['business_id' => $this->biz->id]);
    $this->canal = bcdCanal($this->biz->id);
});

function bcdCanal(int $biz): Channel
{
    return Channel::query()->withoutGlobalScopes()->forceCreate([
        'business_id' => $biz, 'channel_uuid' => (string) Str::uuid(), 'label' => 'Campanha teste',
        'type' => Channel::TYPE_WHATSAPP_BAILEYS, 'status' => 'active', 'display_identifier' => '+55 47 3'.random_int(100, 999).'-'.random_int(1000, 9999),
    ]);
}

function bcdConversa(int $biz, int $canal, int $criador, bool $optIn = true, ?bool $consente = null, bool $bloqueada = false): int
{
    $contato = (int) DB::table('contacts')->insertGetId([
        'business_id' => $biz, 'type' => 'customer', 'name' => 'Cliente BCD '.uniqid(), 'mobile' => '00000000000',
        'whatsapp_consent' => $consente, 'whatsapp_opt_in_at' => $optIn ? now()->subMonth() : null,
        'created_by' => $criador, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return (int) DB::table('conversations')->insertGetId([
        'business_id' => $biz, 'channel_id' => $canal, 'contact_id' => $contato,
        'customer_external_id' => '+5547'.random_int(900000000, 999999999), 'contact_name' => 'Fulano',
        'status' => 'open', 'is_blocked' => $bloqueada, 'last_message_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** @param list<int> $conversas */
function bcdCampanha(int $biz, int $canal, int $criador, array $conversas, string $kind = 'freeform', string $status = 'draft'): int
{
    return (int) DB::table('whatsapp_broadcasts')->insertGetId([
        'business_id' => $biz, 'channel_id' => $canal, 'created_by_user_id' => $criador, 'kind' => $kind,
        'template_name' => $kind === 'template' ? 'promo' : null, 'body' => 'Promoção de outubro!', 'status' => $status,
        'audience_snapshot' => json_encode([]), 'recipient_conversation_ids' => json_encode($conversas),
        'total' => $status === 'dispatching' ? count($conversas) : 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function bcdCampanhaLida(int $id): object
{
    return DB::table('whatsapp_broadcasts')->where('id', $id)->first();
}

it('US-WA-322 · plano anti-ban: intervalo sorteado, só na janela comercial e limite por dia', function () {
    $sorteio = fn (int $a, int $z): int => $z; // sempre o máximo
    $h = PlanoDeDisparo::horarios(6, CarbonImmutable::parse('2026-10-26 19:59:00'), 3, 20, 45, 8, 20, $sorteio);

    expect(array_map(fn ($t) => $t->format('d H:i:s'), $h))->toBe([
        '26 19:59:00',
        '26 19:59:45', // +45s, ainda antes das 20h
        '27 08:00:00', // 20:00:30 passou da janela → início da janela seguinte (conta do dia zera)
        '27 08:00:45',
        '27 08:01:30',
        '28 08:00:00', // 3 no dia 27 = limite → dia seguinte
    ]);
    // Antes da janela começa no início dela.
    expect(PlanoDeDisparo::horarios(1, CarbonImmutable::parse('2026-10-26 05:00:00'), 10, 20, 45, 8, 20, $sorteio)[0]->format('H:i'))->toBe('08:00');
});

it('US-WA-322 · disparo agenda um envio por conversa e só sai do rascunho uma vez', function () {
    Bus::fake([EnviarItemBroadcastJob::class]);
    $convs = [bcdConversa($this->biz->id, $this->canal->id, $this->criador->id), bcdConversa($this->biz->id, $this->canal->id, $this->criador->id)];
    $id = bcdCampanha($this->biz->id, $this->canal->id, $this->criador->id, $convs);

    (new DispararBroadcastJob($this->biz->id, $id))->handle();
    (new DispararBroadcastJob($this->biz->id, $id))->handle(); // 2ª execução: já não é rascunho

    $c = bcdCampanhaLida($id);
    expect($c->status)->toBe('dispatching')->and((int) $c->total)->toBe(2);
    $jobs = Bus::dispatched(EnviarItemBroadcastJob::class)->values()->all();
    expect($jobs)->toHaveCount(2)
        ->and(array_map(fn ($j) => $j->conversationId, $jobs))->toBe($convs)
        ->and($jobs[0]->businessId)->toBe($this->biz->id)
        ->and($jobs[0]->delay)->not->toBeNull();
});

it('US-WA-322 · flag desligada, campanha de template ou de outra empresa: não dispara', function () {
    Bus::fake([EnviarItemBroadcastJob::class]);
    $conv = bcdConversa($this->biz->id, $this->canal->id, $this->criador->id);
    $id = bcdCampanha($this->biz->id, $this->canal->id, $this->criador->id, [$conv]);
    $tpl = bcdCampanha($this->biz->id, $this->canal->id, $this->criador->id, [$conv], 'template');

    config(['whatsapp.broadcast.disparo_enabled' => false]);
    (new DispararBroadcastJob($this->biz->id, $id))->handle();
    expect(bcdCampanhaLida($id)->status)->toBe('draft');

    config(['whatsapp.broadcast.disparo_enabled' => true]);
    (new DispararBroadcastJob($this->biz->id, $tpl))->handle();
    (new DispararBroadcastJob($this->outra->id, $id))->handle(); // id certo, empresa errada
    expect(bcdCampanhaLida($tpl)->status)->toBe('draft')
        ->and(bcdCampanhaLida($id)->status)->toBe('draft');
    expect(Bus::dispatched(EnviarItemBroadcastJob::class)->count())->toBe(0);
});

it('US-WA-322 · envio item a item: só quem tem opt-in e não recusou recebe; campanha fecha ao fim', function () {
    $ok = bcdConversa($this->biz->id, $this->canal->id, $this->criador->id);
    $semOptIn = bcdConversa($this->biz->id, $this->canal->id, $this->criador->id, false);
    $recusou = bcdConversa($this->biz->id, $this->canal->id, $this->criador->id, true, false);
    $bloqueada = bcdConversa($this->biz->id, $this->canal->id, $this->criador->id, true, null, true);
    $daOutra = bcdConversa($this->outra->id, bcdCanal($this->outra->id)->id, $this->criador->id);
    $todas = [$ok, $semOptIn, $recusou, $bloqueada, $daOutra];
    $id = bcdCampanha($this->biz->id, $this->canal->id, $this->criador->id, $todas, 'freeform', 'dispatching');

    foreach ($todas as $conv) {
        (new EnviarItemBroadcastJob($this->biz->id, $id, $conv))->handle(app(EnvioDeTextoNoCanal::class));
    }

    $c = bcdCampanhaLida($id);
    expect((int) $c->enviados)->toBe(1)->and((int) $c->pulados)->toBe(4)->and((int) $c->falhas)->toBe(0)
        ->and($c->status)->toBe('done');
    expect(count(Http::recorded()))->toBe(1);
    $msg = DB::table('messages')->where('business_id', $this->biz->id)->where('conversation_id', $ok)->first();
    expect($msg->body)->toBe('Promoção de outubro!')->and($msg->status)->toBe('sent')->and($msg->sender_kind)->toBe('system');
    expect(DB::table('messages')->whereIn('conversation_id', [$semOptIn, $recusou, $bloqueada, $daOutra])->count())->toBe(0);
});

it('US-WA-322 · campanha cancelada (fora de dispatching) não envia o que ainda estava agendado', function () {
    $conv = bcdConversa($this->biz->id, $this->canal->id, $this->criador->id);
    $id = bcdCampanha($this->biz->id, $this->canal->id, $this->criador->id, [$conv], 'freeform', 'failed');

    (new EnviarItemBroadcastJob($this->biz->id, $id, $conv))->handle(app(EnvioDeTextoNoCanal::class));

    expect(count(Http::recorded()))->toBe(0)->and((int) bcdCampanhaLida($id)->enviados)->toBe(0);
});
