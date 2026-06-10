<?php

declare(strict_types=1);

use App\User;
use Illuminate\Support\Facades\DB;
use Modules\OficinaAuto\Entities\ServiceOrder;
use Modules\OficinaAuto\Entities\Vehicle;

uses(Tests\TestCase::class);

/**
 * store() vincula a OS recém-criada ao veículo (current_rental_id) — PACOTE-Q9 PR-1.
 *
 * Bug pego pelo E2E UC-11 (run 27273605033): OS criada pelo form Nova OS não aparecia
 * como OS no kanban Produção/Oficina — o card ficava "sem OS" porque o bucket
 * `disponivel` não tem fallback V3 (ProducaoOficinaController::loadRentalFallbacks só
 * cobre locada|manutencao) e store() não setava vehicle.current_rental_id. UC-07 do
 * casos.md promete: "salvar adiciona o card em Recepção" (como OS, não como pátio).
 *
 * Regra: vincula SÓ se o veículo está livre (current_rental_id NULL) — não clobbera
 * OS ativa de outro fluxo. Tests biz=1 (ADR 0101). Espelha harness ServiceOrderLaudoPhotoTest.
 */

const BIZ_LINKOS = 1;
const PLATE_LINKOS_PREFIX = 'WLNK';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: requer schema MySQL UltimatePOS (ADR 0101)');
    }
});

function linkos_criaVeiculo(string $suffix, ?int $currentRentalId = null): Vehicle
{
    return Vehicle::withoutGlobalScopes()->create([
        'business_id'       => BIZ_LINKOS,
        'plate'             => PLATE_LINKOS_PREFIX . $suffix,
        'vehicle_type'      => 'automovel',
        'current_rental_id' => $currentRentalId,
    ]);
}

function linkos_user(): User
{
    $user = User::factory()->create(['business_id' => BIZ_LINKOS]);
    $user->givePermissionTo('superadmin');

    return $user;
}

function linkos_cleanup(string $suffix): void
{
    $vehicles = Vehicle::withoutGlobalScopes()
        ->where('plate', PLATE_LINKOS_PREFIX . $suffix)
        ->pluck('id')
        ->toArray();

    if (! empty($vehicles)) {
        ServiceOrder::withoutGlobalScopes()->whereIn('vehicle_id', $vehicles)->forceDelete();
        Vehicle::withoutGlobalScopes()->whereIn('id', $vehicles)->forceDelete();
    }
}

it('store vincula OS nova ao veículo LIVRE (current_rental_id NULL → id da OS)', function () {
    session(['user.business_id' => BIZ_LINKOS]);
    $vehicle = linkos_criaVeiculo('A');
    expect($vehicle->current_rental_id)->toBeNull();

    $response = $this->actingAs(linkos_user())->post('/oficina-auto/ordens-servico', [
        'vehicle_id' => $vehicle->id,
        'order_type' => 'mecanica',
        'status'     => 'aberta',
        'notes'      => 'LINKOS-A — vínculo no store',
    ]);

    $os = ServiceOrder::withoutGlobalScopes()->where('vehicle_id', $vehicle->id)->first();
    expect($os)->not->toBeNull();
    $response->assertRedirect('/oficina-auto/ordens-servico/' . $os->id);

    // O veículo agora aponta pra OS — é isso que faz o card do kanban mostrar a OS
    // (e não "sem OS") e o drawer rico abrir no clique.
    expect((int) $vehicle->fresh()->current_rental_id)->toBe((int) $os->id);
})->afterEach(fn () => linkos_cleanup('A'));

it('store NÃO clobbera vínculo de veículo que JÁ tem OS ativa', function () {
    session(['user.business_id' => BIZ_LINKOS]);
    $vehicle = linkos_criaVeiculo('B');

    $osAtiva = ServiceOrder::withoutGlobalScopes()->create([
        'business_id' => BIZ_LINKOS,
        'vehicle_id'  => $vehicle->id,
        'order_type'  => 'mecanica',
        'status'      => 'aberta',
        'entered_at'  => now()->subDay(),
        'notes'       => 'LINKOS-B — OS ativa pré-existente',
    ]);
    $vehicle->update(['current_rental_id' => $osAtiva->id]);

    $this->actingAs(linkos_user())->post('/oficina-auto/ordens-servico', [
        'vehicle_id' => $vehicle->id,
        'order_type' => 'mecanica',
        'status'     => 'aberta',
        'notes'      => 'LINKOS-B — segunda OS não rouba o vínculo',
    ]);

    // Vínculo original preservado (a 2ª OS existe, mas não vira o "documento vivo" do card).
    expect((int) $vehicle->fresh()->current_rental_id)->toBe((int) $osAtiva->id);
    expect(ServiceOrder::withoutGlobalScopes()->where('vehicle_id', $vehicle->id)->count())->toBe(2);
})->afterEach(fn () => linkos_cleanup('B'));
