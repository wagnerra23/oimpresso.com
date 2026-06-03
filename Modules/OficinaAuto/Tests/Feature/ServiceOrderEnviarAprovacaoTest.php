<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Modules\OficinaAuto\Entities\ServiceOrder;
use Modules\OficinaAuto\Entities\Vehicle;
use Modules\OficinaAuto\Jobs\EnviarLinkAprovacaoWhatsappJob;

uses(Tests\TestCase::class);

/**
 * US-OFICINA-041 — Gate de aprovação: enviar orçamento pro cliente (status → orcamento).
 *
 * Delta do protótipo Cowork "Nova OS" (card "Aprovação do cliente"). O endpoint
 * transiciona status → orcamento, o que faz o ServiceOrderObserver despachar o
 * EnviarLinkAprovacaoWhatsappJob (link público + PIN). Tests biz=1 (ADR 0101).
 */

const BIZ_APR = 1;
const PLATE_APR_PREFIX = 'WAPR';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: requer schema MySQL UltimatePOS (ADR 0101)');
    }
});

function apr_criaOs(string $suffix, string $status = 'aberta', int $biz = BIZ_APR): ServiceOrder
{
    $vehicle = Vehicle::withoutGlobalScopes()->create([
        'business_id'  => $biz,
        'plate'        => PLATE_APR_PREFIX . $suffix,
        'vehicle_type' => 'automovel',
    ]);

    return ServiceOrder::withoutGlobalScopes()->create([
        'business_id' => $biz,
        'vehicle_id'  => $vehicle->id,
        'status'      => $status,
    ]);
}

function apr_cleanup(string $suffix): void
{
    $vehicles = Vehicle::withoutGlobalScopes()
        ->where('plate', PLATE_APR_PREFIX . $suffix)
        ->pluck('id')->toArray();
    if (! empty($vehicles)) {
        ServiceOrder::withoutGlobalScopes()->whereIn('vehicle_id', $vehicles)->forceDelete();
        Vehicle::withoutGlobalScopes()->whereIn('id', $vehicles)->forceDelete();
    }
}

it('envia aprovação: status vira orcamento e dispara job WhatsApp', function () {
    Queue::fake();
    session(['user.business_id' => BIZ_APR]);
    $user = \App\User::withoutGlobalScopes()->where('business_id', BIZ_APR)->first();
    if ($user === null) {
        $this->markTestSkipped('Sem user disponível em biz=1');
    }

    $os = apr_criaOs('A', 'aberta');

    $resp = $this->actingAs($user)->post("/oficina-auto/ordens-servico/{$os->id}/enviar-aprovacao");
    $resp->assertRedirect();

    expect(ServiceOrder::withoutGlobalScopes()->find($os->id)->status)->toBe('orcamento');
    Queue::assertPushed(EnviarLinkAprovacaoWhatsappJob::class);
})->afterEach(fn () => apr_cleanup('A'));

it('não reenvia aprovação em OS já aprovada (status preservado)', function () {
    Queue::fake();
    session(['user.business_id' => BIZ_APR]);
    $user = \App\User::withoutGlobalScopes()->where('business_id', BIZ_APR)->first();
    if ($user === null) {
        $this->markTestSkipped('Sem user disponível em biz=1');
    }

    $os = apr_criaOs('B', 'aprovada');

    $this->actingAs($user)->post("/oficina-auto/ordens-servico/{$os->id}/enviar-aprovacao")
        ->assertRedirect();

    // status NÃO muda + nenhum job de aprovação disparado
    expect(ServiceOrder::withoutGlobalScopes()->find($os->id)->status)->toBe('aprovada');
    Queue::assertNotPushed(EnviarLinkAprovacaoWhatsappJob::class);
})->afterEach(fn () => apr_cleanup('B'));
