<?php
// @covers-us US-WA-323

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Http\Controllers\Admin\BroadcastController;
use Modules\Whatsapp\Jobs\DispararBroadcastJob;
use Spatie\Permission\Models\Permission;
use Tests\Support\WithSeededTenant;

/**
 * "Salvar e disparar" e "Cancelar" da campanha (US-WA-323 · ADR 0268 fase 2).
 *
 * Âncora: SPEC US-WA-323 (critérios 1-5). O disparo em si é o `DispararBroadcastJob` (US-WA-322,
 * testado à parte); aqui só se prova QUANDO ele é agendado e que cancelar tira a campanha do ar.
 *
 * Tenant 98 contra 99 (ADR 0358). ⚠️ SKIP em SQLite (LC-13).
 */
uses(Tests\TestCase::class, DatabaseTransactions::class, WithSeededTenant::class);

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
    Bus::fake([DispararBroadcastJob::class]);
    foreach (['whatsapp.send', 'whatsapp.view-all-phones'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }
    $this->user = \App\User::factory()->create(['business_id' => $this->biz->id]);
    $this->user->givePermissionTo(['whatsapp.send', 'whatsapp.view-all-phones']);
    $this->actingAs($this->user);
    session(['user.business_id' => $this->biz->id, 'user.id' => $this->user->id]);

    $this->canal = Channel::query()->withoutGlobalScopes()->forceCreate([
        'business_id' => $this->biz->id, 'channel_uuid' => (string) Str::uuid(), 'label' => 'Campanha',
        'type' => Channel::TYPE_WHATSAPP_BAILEYS, 'status' => 'active', 'display_identifier' => '+55 47 3'.random_int(100, 999).'-'.random_int(1000, 9999),
    ]);
    $contato = (int) DB::table('contacts')->insertGetId([
        'business_id' => $this->biz->id, 'type' => 'customer', 'name' => 'Cliente opt-in', 'mobile' => '00000000000',
        'whatsapp_opt_in_at' => now()->subMonth(), 'created_by' => $this->user->id, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('conversations')->insert([
        'business_id' => $this->biz->id, 'channel_id' => $this->canal->id, 'contact_id' => $contato,
        'customer_external_id' => '+5547'.random_int(900000000, 999999999), 'contact_name' => 'Fulano',
        'status' => 'open', 'is_blocked' => false, 'last_message_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    $this->req = fn (array $dados) => Request::create('/atendimento/broadcast', 'POST', $dados);
    $this->campanhas = fn () => DB::table('whatsapp_broadcasts')->where('business_id', $this->biz->id)->where('channel_id', $this->canal->id);
});

it('US-WA-323 · flag desligada: "salvar e disparar" recusa e não grava nem agenda', function () {
    config(['whatsapp.broadcast.disparo_enabled' => false]);

    expect(fn () => app(BroadcastController::class)->store(($this->req)(['channel_id' => $this->canal->id, 'kind' => 'freeform', 'body' => 'Oi', 'disparar' => true])))
        ->toThrow(ValidationException::class);
    expect(($this->campanhas)()->count())->toBe(0);
    Bus::assertNotDispatched(DispararBroadcastJob::class);

    // Controle: rascunho continua funcionando.
    app(BroadcastController::class)->store(($this->req)(['channel_id' => $this->canal->id, 'kind' => 'freeform', 'body' => 'Oi']));
    expect(($this->campanhas)()->value('status'))->toBe('draft');
    Bus::assertNotDispatched(DispararBroadcastJob::class);
});

it('UC-CXU-19 · US-WA-323 · flag ligada: grava a campanha e agenda o disparo dela, da própria empresa', function () {
    config(['whatsapp.broadcast.disparo_enabled' => true]);

    app(BroadcastController::class)->store(($this->req)(['channel_id' => $this->canal->id, 'kind' => 'freeform', 'body' => 'Promoção', 'disparar' => true]));

    $id = (int) ($this->campanhas)()->value('id');
    Bus::assertDispatched(DispararBroadcastJob::class, fn ($j) => $j->businessId === $this->biz->id && $j->broadcastId === $id);
});

it('US-WA-323 · template ou canal com campanha em disparo: recusa', function () {
    config(['whatsapp.broadcast.disparo_enabled' => true]);
    expect(fn () => app(BroadcastController::class)->store(($this->req)(['channel_id' => $this->canal->id, 'kind' => 'template', 'template_name' => 'promo', 'disparar' => true])))
        ->toThrow(ValidationException::class);

    app(BroadcastController::class)->store(($this->req)(['channel_id' => $this->canal->id, 'kind' => 'freeform', 'body' => 'A', 'disparar' => true]));
    ($this->campanhas)()->update(['status' => 'dispatching']);
    expect(fn () => app(BroadcastController::class)->store(($this->req)(['channel_id' => $this->canal->id, 'kind' => 'freeform', 'body' => 'B', 'disparar' => true])))
        ->toThrow(ValidationException::class);
    Bus::assertDispatchedTimes(DispararBroadcastJob::class, 1);
});

it('US-WA-323 · cancelar tira a campanha de disparo; campanha de outra empresa dá 404', function () {
    $id = (int) DB::table('whatsapp_broadcasts')->insertGetId([
        'business_id' => $this->biz->id, 'channel_id' => $this->canal->id, 'created_by_user_id' => $this->user->id,
        'kind' => 'freeform', 'body' => 'X', 'status' => 'dispatching', 'audience_snapshot' => '[]',
        'recipient_conversation_ids' => '[]', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $alheia = (int) DB::table('whatsapp_broadcasts')->insertGetId([
        'business_id' => $this->outra->id, 'channel_id' => $this->canal->id, 'created_by_user_id' => $this->user->id,
        'kind' => 'freeform', 'body' => 'Y', 'status' => 'dispatching', 'audience_snapshot' => '[]',
        'recipient_conversation_ids' => '[]', 'created_at' => now(), 'updated_at' => now(),
    ]);

    app(BroadcastController::class)->cancelar(Request::create("/atendimento/broadcast/{$id}/cancelar", 'POST'), $id);
    expect(DB::table('whatsapp_broadcasts')->where('id', $id)->value('status'))->toBe('cancelled');

    expect(fn () => app(BroadcastController::class)->cancelar(Request::create("/atendimento/broadcast/{$alheia}/cancelar", 'POST'), $alheia))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
    expect(DB::table('whatsapp_broadcasts')->where('id', $alheia)->value('status'))->toBe('dispatching');
});
