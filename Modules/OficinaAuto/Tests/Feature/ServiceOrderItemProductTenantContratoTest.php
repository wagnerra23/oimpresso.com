<?php
// @covers-us US-OFICINA-001

declare(strict_types=1);

// casos (G-2 rastreabilidade · ADR 0264): defende
//   UC-OED-09 (OficinaAuto/ServiceOrders/Edit) — editar o item não troca a peça por produto de outro business

use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\OficinaAuto\Entities\ServiceOrder;
use Modules\OficinaAuto\Entities\ServiceOrderItem;
use Modules\OficinaAuto\Entities\Vehicle;

uses(Tests\TestCase::class);

/**
 * Contrato Tier 0 (ADR 0093): o item da OS só referencia produto da própria empresa,
 * também na EDIÇÃO.
 *
 * O lançamento (store) já recusava produto de outra empresa no
 * ServiceOrderItemService::addItem (UC-OED-08). O update fazia `fill($validated)` com
 * `product_id` só `nullable|integer`: o item passava a apontar pro catálogo de outra
 * empresa.
 *
 * A prova é: 422 com erro NO CAMPO product_id + o product_id do item não muda. O caso de
 * controle (produto da própria empresa) garante que a requisição chega ao controller.
 *
 * Tenants: 98 é a empresa do usuário (ADR 0358). A empresa "outra" é o biz=2 do seed da
 * lane — o 99 não existe no seed e quebraria as FKs de business.
 */

const PTEN_BIZ_PROPRIA = 98;
const PTEN_BIZ_OUTRA = 2;
const PTEN_PLACA = 'PTEN';
const PTEN_SKU = 'PTEN-SKU-';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: requer schema MySQL UltimatePOS');
    }
    foreach (['vehicles', 'service_orders', 'oficina_service_order_items', 'products'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("tabela {$t} ausente");
        }
    }
    foreach ([PTEN_BIZ_PROPRIA, PTEN_BIZ_OUTRA] as $biz) {
        if (! DB::table('business')->where('id', $biz)->exists()) {
            $this->markTestSkipped("business {$biz} ausente — seed pest-mysql-setup não rodou");
        }
    }

    session(['user.business_id' => PTEN_BIZ_PROPRIA]);
});

function pten_produto(int $biz): int
{
    $dono = (int) DB::table('business')->where('id', $biz)->value('owner_id');

    return (int) DB::table('products')->insertGetId([
        'business_id'    => $biz,
        'name'           => 'Peça PTEN ' . $biz,
        'type'           => 'single',
        'tax_type'       => 'exclusive',
        'sku'            => PTEN_SKU . $biz . '-' . random_int(1000, 9999),
        'barcode_type'   => 'C128',
        'enable_stock'   => 0,
        'alert_quantity' => 0,
        'created_by'     => $dono,
        'created_at'     => now(),
        'updated_at'     => now(),
    ]);
}

function pten_usuario(): User
{
    // user_type/allow_login explícitos: sem eles o CheckUserLogin devolve 403 antes do controller.
    $user = User::factory()->create([
        'business_id' => PTEN_BIZ_PROPRIA,
        'user_type'   => 'user',
        'allow_login' => 1,
    ]);
    $user->givePermissionTo('superadmin');

    return $user;
}

/**
 * @return array{0: ServiceOrder, 1: ServiceOrderItem}
 */
function pten_os_com_item(int $produtoId): array
{
    $vehicle = Vehicle::withoutGlobalScopes()->create([ // SUPERADMIN: fixture de teste em tenant explícito
        'business_id'  => PTEN_BIZ_PROPRIA,
        'plate'        => PTEN_PLACA . random_int(100, 999),
        'vehicle_type' => 'caminhao',
    ]);
    $os = ServiceOrder::withoutGlobalScopes()->create([ // SUPERADMIN: fixture de teste em tenant explícito
        'business_id' => PTEN_BIZ_PROPRIA,
        'vehicle_id'  => $vehicle->id,
        'order_type'  => 'mecanica',
        'status'      => 'aberta',
        'entered_at'  => now(),
    ]);
    $item = ServiceOrderItem::withoutGlobalScopes()->create([ // SUPERADMIN: fixture de teste em tenant explícito
        'business_id'      => PTEN_BIZ_PROPRIA,
        'service_order_id' => $os->id,
        'tipo'             => ServiceOrderItem::TIPO_PECA,
        'descricao'        => 'Peça PTEN',
        'quantidade'       => 1,
        'valor_unitario'   => 10,
        'valor_total'      => 10,
        'product_id'       => $produtoId,
    ]);

    return [$os, $item];
}

function pten_limpar(): void
{
    $veiculos = Vehicle::withoutGlobalScopes() // SUPERADMIN: limpeza só do tenant do teste
        ->where('business_id', PTEN_BIZ_PROPRIA)
        ->where('plate', 'like', PTEN_PLACA . '%')
        ->pluck('id')
        ->all();
    if ($veiculos !== []) {
        $osIds = ServiceOrder::withoutGlobalScopes()->whereIn('vehicle_id', $veiculos)->pluck('id')->all();
        if ($osIds !== []) {
            ServiceOrderItem::withoutGlobalScopes()->whereIn('service_order_id', $osIds)->forceDelete();
            ServiceOrder::withoutGlobalScopes()->whereIn('id', $osIds)->forceDelete();
        }
        Vehicle::withoutGlobalScopes()->whereIn('id', $veiculos)->forceDelete();
    }
    DB::table('products')
        ->whereIn('business_id', [PTEN_BIZ_PROPRIA, PTEN_BIZ_OUTRA])
        ->where('sku', 'like', PTEN_SKU . '%')
        ->delete();
}

it('UC-OED-09 · editar o item trocando a peça por produto de OUTRA empresa é recusado e o item não muda', function () {
    $proprio = pten_produto(PTEN_BIZ_PROPRIA);
    $alheio = pten_produto(PTEN_BIZ_OUTRA);
    [$os, $item] = pten_os_com_item($proprio);

    $this->actingAs(pten_usuario())
        ->putJson('/oficina-auto/ordens-servico/' . $os->id . '/items/' . $item->id, [
            'product_id' => $alheio,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('product_id');

    expect((int) ServiceOrderItem::withoutGlobalScopes()->find($item->id)->product_id)->toBe($proprio);
})->after(fn () => pten_limpar());

it('UC-OED-09 · controle: editar o item trocando por outro produto da PRÓPRIA empresa grava', function () {
    $proprio = pten_produto(PTEN_BIZ_PROPRIA);
    $outroProprio = pten_produto(PTEN_BIZ_PROPRIA);
    [$os, $item] = pten_os_com_item($proprio);

    $this->actingAs(pten_usuario())
        ->putJson('/oficina-auto/ordens-servico/' . $os->id . '/items/' . $item->id, [
            'product_id' => $outroProprio,
        ])
        ->assertOk()
        ->assertJsonPath('product_id', $outroProprio);

    expect((int) ServiceOrderItem::withoutGlobalScopes()->find($item->id)->product_id)->toBe($outroProprio);
})->after(fn () => pten_limpar());
