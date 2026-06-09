<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\OficinaAuto\Entities\ServiceOrder;
use Modules\OficinaAuto\Entities\Vehicle;
use Modules\OficinaAuto\Services\StageGateEvaluator;

uses(Tests\TestCase::class);

/**
 * F3 OS-V2-5 — StageGate (checklist de etapa) ENFORÇADO no servidor.
 *
 * A regra-mãe do handoff: "gate é servidor, UI é espelho". Estes specs garantem que
 * o ServiceOrderFsmActionController::execute BLOQUEIA (422) a transição quando há
 * requisito bloqueante pendente — independentemente do que a UI mostra — e que o
 * StageGateEvaluator deriva os requisitos data-driven por (process_key, action_key).
 *
 * Multi-tenant Tier 0 (ADR 0093): execute cross-tenant → 404 (route model binding scope).
 * MySQL-only (schema UltimatePOS · ADR 0101). User real biz=1 (HTTP passa middleware).
 */

const BIZ_GATE5 = 1;
const BIZ_GATE5_OUTRO = 99;
const PLATE_GATE5_PREFIX = 'WGAT5';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: requer schema MySQL UltimatePOS (ADR 0101)');
    }
    if (! Schema::hasColumn('service_orders', 'current_stage_id')) {
        $this->markTestSkipped('Wave 7 migration current_stage_id pendente');
    }
});

function sg5_user(): ?\App\User
{
    return \App\User::withoutGlobalScopes()->where('business_id', BIZ_GATE5)->first();
}

function sg5_criaOs(string $suffix, int $biz = BIZ_GATE5, string $orderType = 'mecanica'): ServiceOrder
{
    $vehicle = Vehicle::withoutGlobalScopes()->create([
        'business_id'  => $biz,
        'plate'        => PLATE_GATE5_PREFIX . $suffix,
        'vehicle_type' => 'caminhao',
    ]);

    return ServiceOrder::withoutGlobalScopes()->create([
        'business_id' => $biz,
        'vehicle_id'  => $vehicle->id,
        'order_type'  => $orderType,
        'status'      => 'aberta',
        'entered_at'  => now(),
    ]);
}

function sg5_cleanup(string $suffix): void
{
    $vehicles = Vehicle::withoutGlobalScopes()
        ->where('plate', PLATE_GATE5_PREFIX . $suffix)
        ->pluck('id')->toArray();
    if (! empty($vehicles)) {
        ServiceOrder::withoutGlobalScopes()->whereIn('vehicle_id', $vehicles)->forceDelete();
        Vehicle::withoutGlobalScopes()->whereIn('id', $vehicles)->forceDelete();
    }
}

// ─── Enforcement HTTP ───────────────────────────────────────────────────────

it('bloqueia (422) enviar_orcamento sem DVI/foto/orçamento — gate é servidor', function () {
    session(['user.business_id' => BIZ_GATE5]);
    $user = sg5_user();
    if ($user === null) {
        $this->markTestSkipped('Sem user disponível em biz=1');
    }

    $os = sg5_criaOs('A'); // order_type=mecanica → process oficina_mecanica_os

    $resp = $this->actingAs($user)
        ->postJson("/oficina-auto/service-orders/{$os->id}/fsm/execute", [
            'action_key' => 'enviar_orcamento',
        ]);

    $resp->assertStatus(422);
    $resp->assertJsonPath('gate.satisfied', false);
    expect($resp->json('gate.blocking_unmet'))->toBe(3);
    expect($resp->json('error'))->toContain('Checklist de etapa');
})->afterEach(fn () => sg5_cleanup('A'));

it('execute cross-tenant é 404 (Tier 0) — OS de outra biz não é alcançável', function () {
    session(['user.business_id' => BIZ_GATE5]);
    $user = sg5_user();
    if ($user === null) {
        $this->markTestSkipped('Sem user disponível em biz=1');
    }

    $os = sg5_criaOs('B', BIZ_GATE5_OUTRO); // OS pertence ao biz=99

    $this->actingAs($user)
        ->postJson("/oficina-auto/service-orders/{$os->id}/fsm/execute", [
            'action_key' => 'enviar_orcamento',
        ])
        ->assertNotFound();
})->afterEach(fn () => sg5_cleanup('B'));

// ─── Evaluator data-driven ──────────────────────────────────────────────────

it('StageGateEvaluator deriva os 3 requisitos bloqueantes de enviar_orcamento', function () {
    session(['user.business_id' => BIZ_GATE5]);

    $os = sg5_criaOs('C');
    $gate = app(StageGateEvaluator::class)->evaluate($os, 'oficina_mecanica_os', 'enviar_orcamento');

    expect($gate['satisfied'])->toBeFalse();
    expect($gate['total'])->toBe(3);
    expect($gate['blocking_unmet'])->toBe(3);
    expect(collect($gate['requirements'])->pluck('key')->all())
        ->toBe(['dvi_min', 'dvi_foto', 'orcamento']);
})->afterEach(fn () => sg5_cleanup('C'));

it('StageGateEvaluator não bloqueia transição sem regra cadastrada (ex: cancelar_os)', function () {
    session(['user.business_id' => BIZ_GATE5]);

    $os = sg5_criaOs('D');
    $gate = app(StageGateEvaluator::class)->evaluate($os, 'oficina_mecanica_os', 'cancelar_os');

    expect($gate['satisfied'])->toBeTrue();
    expect($gate['total'])->toBe(0);
    expect($gate['requirements'])->toBe([]);
})->afterEach(fn () => sg5_cleanup('D'));
