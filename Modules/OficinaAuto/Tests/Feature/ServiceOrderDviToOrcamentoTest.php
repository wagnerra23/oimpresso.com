<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\OficinaAuto\Entities\OaInspectionItem;
use Modules\OficinaAuto\Entities\ServiceOrder;
use Modules\OficinaAuto\Entities\ServiceOrderItem;
use Modules\OficinaAuto\Entities\Vehicle;

uses(Tests\TestCase::class);

/**
 * US-OFICINA-040 — Converter item DVI reprovado/atenção em linha de orçamento.
 *
 * Delta do protótipo Cowork "Nova OS" (botão "+ orçamento"). Backend:
 * DviInspectionController::toOrcamento. Tests biz=1 (ADR 0101). Espelha
 * harness DviInspectionItemTest.
 */

const BIZ_DTO = 1;
const BIZ_DTO_OUTRO = 99;
const PLATE_DTO_PREFIX = 'WDTO';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: requer schema MySQL UltimatePOS (ADR 0101)');
    }
    if (! Schema::hasTable('oa_inspection_items') || ! Schema::hasTable('oficina_service_order_items')) {
        $this->markTestSkipped('Rode migrations DVI + service_order_items primeiro');
    }
});

function dto_criaOs(string $suffix, int $biz = BIZ_DTO): ServiceOrder
{
    $vehicle = Vehicle::withoutGlobalScopes()->create([
        'business_id'  => $biz,
        'plate'        => PLATE_DTO_PREFIX . $suffix,
        'vehicle_type' => 'automovel',
    ]);

    return ServiceOrder::withoutGlobalScopes()->create([
        'business_id' => $biz,
        'vehicle_id'  => $vehicle->id,
        'status'      => 'aberta',
    ]);
}

function dto_criaDvi(ServiceOrder $os, int $biz = BIZ_DTO): OaInspectionItem
{
    return OaInspectionItem::withoutGlobalScopes()->create([
        'business_id'       => $biz,
        'service_order_id'  => $os->id,
        'categoria'         => 'freios',
        'descricao'         => 'Pastilhas dianteiras 2mm',
        'severity'          => OaInspectionItem::SEVERITY_CRITICO,
        'recomendacao'      => 'trocar imediatamente',
        'valor_recomendado' => 480.00,
    ]);
}

function dto_cleanup(string $suffix): void
{
    $vehicles = Vehicle::withoutGlobalScopes()
        ->where('plate', PLATE_DTO_PREFIX . $suffix)
        ->pluck('id')->toArray();

    if (! empty($vehicles)) {
        $osIds = ServiceOrder::withoutGlobalScopes()
            ->whereIn('vehicle_id', $vehicles)->pluck('id')->toArray();
        if (! empty($osIds)) {
            ServiceOrderItem::withoutGlobalScopes()->whereIn('service_order_id', $osIds)->forceDelete();
            OaInspectionItem::withoutGlobalScopes()->whereIn('service_order_id', $osIds)->forceDelete();
            ServiceOrder::withoutGlobalScopes()->whereIn('id', $osIds)->forceDelete();
        }
        Vehicle::withoutGlobalScopes()->whereIn('id', $vehicles)->forceDelete();
    }
}

it('converte item DVI em ServiceOrderItem (valor sugerido vira valor unitário)', function () {
    session(['user.business_id' => BIZ_DTO]);
    $user = \App\User::withoutGlobalScopes()->where('business_id', BIZ_DTO)->first();
    if ($user === null) {
        $this->markTestSkipped('Sem user disponível em biz=1 pra testar HTTP');
    }

    $os = dto_criaOs('A');
    $dvi = dto_criaDvi($os);

    $resp = $this->actingAs($user)->postJson(
        "/oficina-auto/ordens-servico/{$os->id}/dvi/{$dvi->id}/to-orcamento"
    );

    $resp->assertStatus(201);
    $resp->assertJsonPath('item.tipo', 'mao_obra');
    $resp->assertJsonPath('item.valor_unitario', 480.0);
    $resp->assertJsonPath('inspection_item_id', $dvi->id);

    // ServiceOrderItem criado de fato
    $item = ServiceOrderItem::withoutGlobalScopes()->where('service_order_id', $os->id)->first();
    expect($item)->not->toBeNull();
    expect((float) $item->valor_total)->toBe(480.0);

    // DVI marcado como orçado (metadata.budget_item_id)
    $dvi->refresh();
    expect($dvi->metadata['budget_item_id'] ?? null)->toBe($item->id);
})->afterEach(fn () => dto_cleanup('A'));

it('é idempotente — reconverter o mesmo item DVI retorna 409', function () {
    session(['user.business_id' => BIZ_DTO]);
    $user = \App\User::withoutGlobalScopes()->where('business_id', BIZ_DTO)->first();
    if ($user === null) {
        $this->markTestSkipped('Sem user disponível em biz=1');
    }

    $os = dto_criaOs('B');
    $dvi = dto_criaDvi($os);

    $this->actingAs($user)->postJson("/oficina-auto/ordens-servico/{$os->id}/dvi/{$dvi->id}/to-orcamento")
        ->assertStatus(201);

    $resp = $this->actingAs($user)->postJson("/oficina-auto/ordens-servico/{$os->id}/dvi/{$dvi->id}/to-orcamento");
    $resp->assertStatus(409);

    // Não duplicou o ServiceOrderItem
    expect(ServiceOrderItem::withoutGlobalScopes()->where('service_order_id', $os->id)->count())->toBe(1);
})->afterEach(fn () => dto_cleanup('B'));

it('cross-OS guard — item DVI de outra OS retorna 404', function () {
    session(['user.business_id' => BIZ_DTO]);
    $user = \App\User::withoutGlobalScopes()->where('business_id', BIZ_DTO)->first();
    if ($user === null) {
        $this->markTestSkipped('Sem user disponível em biz=1');
    }

    $os1 = dto_criaOs('C1');
    $os2 = dto_criaOs('C2');
    $dvi = dto_criaDvi($os1);

    // Tenta converter via rota da OS-2 um item da OS-1
    $resp = $this->actingAs($user)->postJson(
        "/oficina-auto/ordens-servico/{$os2->id}/dvi/{$dvi->id}/to-orcamento"
    );
    $resp->assertStatus(404);
})->afterEach(function () {
    dto_cleanup('C1');
    dto_cleanup('C2');
});
