<?php
// @covers-us US-OFICINA-001

declare(strict_types=1);

// casos (G-2 rastreabilidade · ADR 0264): defende
//   UC-OCR-04 (OficinaAuto/ServiceOrders/Create) — vehicle_id de outro business rejeitado na criação
//   UC-OED-05 (OficinaAuto/ServiceOrders/Edit)   — vehicle_id de outro business rejeitado na edição

use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\OficinaAuto\Entities\ServiceOrder;
use Modules\OficinaAuto\Entities\Vehicle;

uses(Tests\TestCase::class);

/**
 * Contrato Tier 0 (ADR 0093): a OS só aceita veículo da própria empresa.
 *
 * Antes deste teste a regra era `exists:vehicles,id`, sem filtro de empresa: uma OS do
 * business A podia apontar pra um veículo do business B, e a página pública de aprovação
 * mostrava a placa dele.
 *
 * O 302 sozinho não prova nada (sucesso e recusa são ambos redirect). A prova é:
 * erro de validação NO CAMPO vehicle_id + nenhuma linha gravada/alterada. O caso de
 * controle (veículo da própria empresa) garante que a requisição chega ao controller.
 *
 * Tenants: 98 é a empresa do usuário (tenant canônico de teste, ADR 0358). A empresa
 * "outra" é o biz=2 do seed da lane — o 99 não existe no seed e quebraria
 * fk_vehicles_business (mesmo motivo registrado em VehicleMultiTenantTest).
 */

const VTEN_BIZ_PROPRIA = 98;
const VTEN_BIZ_OUTRA = 2;
const VTEN_PLACA = 'VTEN';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: requer schema MySQL UltimatePOS');
    }
    if (! Schema::hasTable('vehicles') || ! Schema::hasTable('service_orders')) {
        $this->markTestSkipped('tabelas do OficinaAuto ausentes — rode o migrate do módulo');
    }
    foreach ([VTEN_BIZ_PROPRIA, VTEN_BIZ_OUTRA] as $biz) {
        if (! DB::table('business')->where('id', $biz)->exists()) {
            $this->markTestSkipped("business {$biz} ausente — seed pest-mysql-setup não rodou");
        }
    }

    session(['user.business_id' => VTEN_BIZ_PROPRIA]);
});

function vten_veiculo(int $biz, string $sufixo): Vehicle
{
    return Vehicle::withoutGlobalScopes()->create([ // SUPERADMIN: fixture de teste em tenant explícito
        'business_id'  => $biz,
        'plate'        => VTEN_PLACA . $sufixo,
        'vehicle_type' => 'automovel',
    ]);
}

function vten_usuario(): User
{
    // user_type/allow_login explícitos: sem eles o CheckUserLogin devolve 403 antes do controller.
    $user = User::factory()->create([
        'business_id' => VTEN_BIZ_PROPRIA,
        'user_type'   => 'user',
        'allow_login' => 1,
    ]);
    $user->givePermissionTo('superadmin');

    return $user;
}

function vten_os(Vehicle $vehicle): ServiceOrder
{
    return ServiceOrder::withoutGlobalScopes()->create([ // SUPERADMIN: fixture de teste em tenant explícito
        'business_id' => VTEN_BIZ_PROPRIA,
        'vehicle_id'  => $vehicle->id,
        'order_type'  => 'mecanica',
        'status'      => 'aberta',
        'entered_at'  => now(),
    ]);
}

function vten_limpar(): void
{
    $ids = Vehicle::withoutGlobalScopes() // SUPERADMIN: limpeza só dos dois tenants do teste
        ->whereIn('business_id', [VTEN_BIZ_PROPRIA, VTEN_BIZ_OUTRA])
        ->where('plate', 'like', VTEN_PLACA . '%')
        ->pluck('id')
        ->all();
    if ($ids !== []) {
        Vehicle::withoutGlobalScopes()->whereIn('id', $ids)->update(['current_rental_id' => null]);
        ServiceOrder::withoutGlobalScopes()->whereIn('vehicle_id', $ids)->forceDelete();
        Vehicle::withoutGlobalScopes()->whereIn('id', $ids)->forceDelete();
    }
}

it('UC-OCR-04 · criar OS com veículo de OUTRA empresa é recusado no campo vehicle_id e não grava', function () {
    $alheio = vten_veiculo(VTEN_BIZ_OUTRA, 'C1');

    $this->actingAs(vten_usuario())
        ->post('/oficina-auto/ordens-servico', [
            'vehicle_id' => $alheio->id,
            'order_type' => 'mecanica',
            'status'     => 'aberta',
        ])
        ->assertSessionHasErrors('vehicle_id');

    expect(ServiceOrder::withoutGlobalScopes()->where('vehicle_id', $alheio->id)->count())->toBe(0);
})->after(fn () => vten_limpar());

it('UC-OCR-04 · controle: criar OS com veículo da PRÓPRIA empresa grava', function () {
    $proprio = vten_veiculo(VTEN_BIZ_PROPRIA, 'C2');

    $this->actingAs(vten_usuario())
        ->post('/oficina-auto/ordens-servico', [
            'vehicle_id' => $proprio->id,
            'order_type' => 'mecanica',
            'status'     => 'aberta',
        ])
        ->assertSessionHasNoErrors();

    $os = ServiceOrder::withoutGlobalScopes()->where('vehicle_id', $proprio->id)->first();
    expect($os)->not->toBeNull();
    expect((int) $os->business_id)->toBe(VTEN_BIZ_PROPRIA);
})->after(fn () => vten_limpar());

it('UC-OED-05 · editar OS trocando pra veículo de OUTRA empresa é recusado e o vínculo não muda', function () {
    $proprio = vten_veiculo(VTEN_BIZ_PROPRIA, 'E1');
    $alheio = vten_veiculo(VTEN_BIZ_OUTRA, 'E2');
    $os = vten_os($proprio);

    $this->actingAs(vten_usuario())
        ->put('/oficina-auto/ordens-servico/' . $os->id, [
            'vehicle_id' => $alheio->id,
            'status'     => 'aberta',
        ])
        ->assertSessionHasErrors('vehicle_id');

    expect((int) ServiceOrder::withoutGlobalScopes()->find($os->id)->vehicle_id)->toBe((int) $proprio->id);
})->after(fn () => vten_limpar());

it('UC-OED-05 · controle: editar OS trocando pra outro veículo da PRÓPRIA empresa grava', function () {
    $proprio = vten_veiculo(VTEN_BIZ_PROPRIA, 'E3');
    $outroProprio = vten_veiculo(VTEN_BIZ_PROPRIA, 'E4');
    $os = vten_os($proprio);

    $this->actingAs(vten_usuario())
        ->put('/oficina-auto/ordens-servico/' . $os->id, [
            'vehicle_id' => $outroProprio->id,
            'status'     => 'aberta',
        ])
        ->assertSessionHasNoErrors();

    expect((int) ServiceOrder::withoutGlobalScopes()->find($os->id)->vehicle_id)->toBe((int) $outroProprio->id);
})->after(fn () => vten_limpar());
