<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Modules\OficinaAuto\Entities\ServiceOrder;
use Modules\OficinaAuto\Entities\Vehicle;
use Modules\OficinaAuto\Jobs\EnviarLinkAprovacaoWhatsappJob;

uses(Tests\TestCase::class);

/**
 * F3 OS-V2-3 — Gate de aprovação com ciclo de estados (none → pending → approved | declined).
 *
 * Cobre a TRILHA derivada do backend que o drawer (DviInlineEditor · DviGateFoot) espelha:
 *  - enviarAprovacao carimba approval_requested_at → estado `pending`
 *  - show() JSON expõe o bloco `approval` (state + total + timestamps)
 *  - re-envio ("Cobrar") em OS já em orcamento re-dispara o WhatsApp (limpa idempotência)
 *  - accessor approval_state deriva approved/declined das colunas de decisão
 *
 * Multi-tenant biz=1 (ADR 0101). MySQL-only (schema UltimatePOS).
 */

const BIZ_GATE = 1;
const PLATE_GATE_PREFIX = 'WGATE';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: requer schema MySQL UltimatePOS (ADR 0101)');
    }
});

function gate_user(): ?\App\User
{
    return \App\User::withoutGlobalScopes()->where('business_id', BIZ_GATE)->first();
}

function gate_criaOs(string $suffix, string $status = 'aberta'): ServiceOrder
{
    $vehicle = Vehicle::withoutGlobalScopes()->create([
        'business_id'  => BIZ_GATE,
        'plate'        => PLATE_GATE_PREFIX . $suffix,
        'vehicle_type' => 'automovel',
    ]);

    return ServiceOrder::withoutGlobalScopes()->create([
        'business_id' => BIZ_GATE,
        'vehicle_id'  => $vehicle->id,
        'order_type'  => 'manutencao',
        'status'      => $status,
    ]);
}

function gate_cleanup(string $suffix): void
{
    $vehicles = Vehicle::withoutGlobalScopes()
        ->where('plate', PLATE_GATE_PREFIX . $suffix)
        ->pluck('id')->toArray();
    if (! empty($vehicles)) {
        ServiceOrder::withoutGlobalScopes()->whereIn('vehicle_id', $vehicles)->forceDelete();
        Vehicle::withoutGlobalScopes()->whereIn('id', $vehicles)->forceDelete();
    }
}

it('enviarAprovacao carimba approval_requested_at e o estado vira pending', function () {
    Queue::fake();
    session(['user.business_id' => BIZ_GATE]);
    $user = gate_user();
    if ($user === null) {
        $this->markTestSkipped('Sem user disponível em biz=1');
    }

    $os = gate_criaOs('A', 'aberta');
    expect($os->approval_state)->toBe('none');

    $this->actingAs($user)->post("/oficina-auto/ordens-servico/{$os->id}/enviar-aprovacao")
        ->assertRedirect();

    $fresh = ServiceOrder::withoutGlobalScopes()->find($os->id);
    expect($fresh->status)->toBe('orcamento');
    expect($fresh->approval_requested_at)->not->toBeNull();
    expect($fresh->approval_decision)->toBeNull();
    expect($fresh->approval_state)->toBe('pending');
    Queue::assertPushed(EnviarLinkAprovacaoWhatsappJob::class);
})->afterEach(fn () => gate_cleanup('A'));

it('show() JSON expõe o bloco approval com o estado derivado', function () {
    session(['user.business_id' => BIZ_GATE]);
    $user = gate_user();
    if ($user === null) {
        $this->markTestSkipped('Sem user disponível em biz=1');
    }

    $os = gate_criaOs('B', 'orcamento');
    $os->forceFill(['approval_requested_at' => now()])->save();

    $resp = $this->actingAs($user)
        ->getJson("/oficina-auto/service-orders/{$os->id}");

    $resp->assertOk();
    $resp->assertJsonPath('approval.state', 'pending');
    expect($resp->json('approval'))->toHaveKeys(['state', 'total', 'requested_at', 'decided_at', 'decision']);
})->afterEach(fn () => gate_cleanup('B'));

it('re-envio (Cobrar) em OS já em orcamento re-dispara o WhatsApp', function () {
    Queue::fake();
    session(['user.business_id' => BIZ_GATE]);
    $user = gate_user();
    if ($user === null) {
        $this->markTestSkipped('Sem user disponível em biz=1');
    }

    $os = gate_criaOs('C', 'orcamento');
    $os->forceFill(['approval_requested_at' => now()->subHour()])->save();
    // Simula a chave de idempotência de 7d do Job já gravada (1º envio).
    Cache::put("oficina:approval_dispatched:{$os->id}", true, now()->addDays(7));

    $this->actingAs($user)->post("/oficina-auto/ordens-servico/{$os->id}/enviar-aprovacao")
        ->assertRedirect();

    // Cobrar limpa a idempotência + redispara o Job (Observer não dispara: status não muda).
    expect(Cache::has("oficina:approval_dispatched:{$os->id}"))->toBeFalse();
    Queue::assertPushed(EnviarLinkAprovacaoWhatsappJob::class);
})->afterEach(fn () => gate_cleanup('C'));

it('accessor approval_state deriva approved e declined das colunas de decisão', function () {
    session(['user.business_id' => BIZ_GATE]);

    $aprovada = gate_criaOs('D', 'aprovada');
    $aprovada->forceFill(['approval_decided_at' => now(), 'approval_decision' => 'approved'])->saveQuietly();
    expect($aprovada->fresh()->approval_state)->toBe('approved');

    $recusada = gate_criaOs('E', 'orcamento');
    $recusada->forceFill([
        'approval_requested_at' => now()->subHour(),
        'approval_decided_at'   => now(),
        'approval_decision'     => 'declined',
    ])->saveQuietly();
    expect($recusada->fresh()->approval_state)->toBe('declined');
})->afterEach(function () {
    gate_cleanup('D');
    gate_cleanup('E');
});
