<?php

declare(strict_types=1);

use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\OficinaAuto\Entities\ServiceOrder;
use Modules\OficinaAuto\Entities\ServiceOrderItem;
use Modules\OficinaAuto\Entities\Vehicle;

uses(Tests\TestCase::class);

/**
 * Wave 2.5 US-OFICINA-027 — Pest payload JSON endpoint drawer ServiceOrderRichSheet.
 *
 * Garante que `GET /oficina-auto/service-orders/{id}` (Accept: application/json) retorna:
 *   - items[]   → list de ServiceOrderItem populando seção "PEÇAS & MÃO DE OBRA"
 *   - items_total → soma valor_total (espelha accessor total_items + alimenta Observer)
 *   - assigned_user.name → concat surname+first_name+last_name (UltimatePOS canon)
 *   - box_label, mileage_at_service → campos modo manutenção sub-vertical 4 ADR 0194
 *   - vehicle.model_year / manufacture_year / color → drawer header polimórfico
 *
 * Smoke browser MCP biz=164 Martinho fica pra fase pós-merge (Wagner rolará manual
 * via Chrome MCP screenshot — gap conhecido catalogado RUNBOOK smoke-prod-evidence).
 *
 * Multi-tenant Tier 0 [ADR 0093]: global scope filtra; este test atua biz=1 explícito.
 *
 * @see resources/js/Pages/OficinaAuto/ProducaoOficina/_components/ServiceOrderRichSheet.tsx
 * @see Modules/OficinaAuto/Http/Controllers/ServiceOrderController.php::show
 */

const BIZ_RICH = 1;
const PLATE_RICH_PREFIX = 'RICH';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: requer schema MySQL UltimatePOS (ADR 0101)');
    }
    if (! Schema::hasTable('oficina_service_order_items')) {
        $this->markTestSkipped('Rode migration 2026_05_17_000010 primeiro');
    }
    if (! Schema::hasColumn('service_orders', 'box_label')) {
        $this->markTestSkipped('Rode migration 2026_05_26_120001 primeiro (box_label + assigned_user_id)');
    }
});

function rich_criaOs(string $suffix, int $biz = BIZ_RICH): ServiceOrder
{
    $vehicle = Vehicle::withoutGlobalScopes()->create([
        'business_id'    => $biz,
        'plate'          => PLATE_RICH_PREFIX . $suffix,
        'vehicle_type'   => 'caminhao',
        'model_year'     => 2022,
        'color'          => 'branco',
    ]);

    return ServiceOrder::withoutGlobalScopes()->create([
        'business_id'        => $biz,
        'vehicle_id'         => $vehicle->id,
        'order_type'         => 'manutencao',
        'status'             => 'aberta',
        'contact_id'         => 1,
        'mileage_at_service' => 42500,
        'box_label'          => 'Elevador 1',
    ]);
}

function rich_cleanup(string $suffix): void
{
    $vehicles = Vehicle::withoutGlobalScopes()
        ->where('plate', 'like', PLATE_RICH_PREFIX . $suffix . '%')
        ->pluck('id')
        ->toArray();

    if (empty($vehicles)) {
        return;
    }

    $osIds = ServiceOrder::withoutGlobalScopes()
        ->whereIn('vehicle_id', $vehicles)
        ->pluck('id')
        ->toArray();

    if (! empty($osIds)) {
        ServiceOrderItem::withoutGlobalScopes()->whereIn('service_order_id', $osIds)->forceDelete();
        ServiceOrder::withoutGlobalScopes()->whereIn('id', $osIds)->forceDelete();
    }

    Vehicle::withoutGlobalScopes()->whereIn('id', $vehicles)->forceDelete();
}

// ---------------------------------------------------------------------------
// Wave 2.3 + 2.1 — JSON payload com items + assigned_user + box_label
// ---------------------------------------------------------------------------

it('GET /service-orders/{id} JSON retorna items[] + items_total + campos manutenção', function () {
    session(['user.business_id' => BIZ_RICH]);
    $os = rich_criaOs('A');

    // Lança 3 items: peça R$ [redacted Tier 0] + 3h mão-obra × R$ [redacted Tier 0] + serviço terceiro R$ [redacted Tier 0]
    ServiceOrderItem::withoutGlobalScopes()->create([
        'business_id'      => BIZ_RICH,
        'service_order_id' => $os->id,
        'tipo'             => 'peca',
        'descricao'        => 'Cilindro hidráulico 6.5T',
        'quantidade'       => 1,
        'valor_unitario'   => 4800,
    ]);
    ServiceOrderItem::withoutGlobalScopes()->create([
        'business_id'      => BIZ_RICH,
        'service_order_id' => $os->id,
        'tipo'             => 'mao_obra',
        'descricao'        => 'Troca cilindro + sangria',
        'quantidade'       => 3,
        'valor_unitario'   => 120,
    ]);
    ServiceOrderItem::withoutGlobalScopes()->create([
        'business_id'      => BIZ_RICH,
        'service_order_id' => $os->id,
        'tipo'             => 'servico_terceiro',
        'descricao'        => 'Recondicionamento bomba',
        'quantidade'       => 1,
        'valor_unitario'   => 850,
    ]);

    $user = User::factory()->create(['business_id' => BIZ_RICH]);
    $user->givePermissionTo('superadmin');

    $response = $this->actingAs($user)
        ->getJson("/oficina-auto/service-orders/{$os->id}");

    $response->assertOk();

    // items[] populated
    $items = $response->json('items');
    expect($items)->toBeArray()->toHaveCount(3);

    $items_total = (float) $response->json('items_total');
    expect($items_total)->toBe(6010.0);

    // Campos modo manutenção
    expect($response->json('order_type'))->toBe('manutencao');
    expect($response->json('box_label'))->toBe('Elevador 1');
    expect($response->json('mileage_at_service'))->toBe(42500);

    // Vehicle expandido com campos novos
    expect($response->json('vehicle.model_year'))->toBe(2022);
    expect($response->json('vehicle.color'))->toBe('branco');
    expect($response->json('vehicle.vehicle_type'))->toBe('caminhao');
})->afterEach(fn () => rich_cleanup('A'));

it('GET /service-orders/{id} OS sem items → items=[] + items_total=0 (backward compat)', function () {
    session(['user.business_id' => BIZ_RICH]);
    $os = rich_criaOs('B');

    $user = User::factory()->create(['business_id' => BIZ_RICH]);
    $user->givePermissionTo('superadmin');

    $response = $this->actingAs($user)
        ->getJson("/oficina-auto/service-orders/{$os->id}");

    $response->assertOk();
    expect($response->json('items'))->toBeArray()->toHaveCount(0);
    expect((float) $response->json('items_total'))->toBe(0.0);
})->afterEach(fn () => rich_cleanup('B'));

it('GET /service-orders/{id} com assigned_user populado → name concatenado UltimatePOS canon', function () {
    session(['user.business_id' => BIZ_RICH]);

    $mecanico = User::factory()->create([
        'business_id' => BIZ_RICH,
        'first_name'  => 'Pedro',
        'surname'     => '',
        'last_name'   => 'Souza',
    ]);

    $os = rich_criaOs('C');
    $os->assigned_user_id = $mecanico->id;
    $os->save();

    $user = User::factory()->create(['business_id' => BIZ_RICH]);
    $user->givePermissionTo('superadmin');

    $response = $this->actingAs($user)
        ->getJson("/oficina-auto/service-orders/{$os->id}");

    $response->assertOk();
    expect($response->json('assigned_user.id'))->toBe($mecanico->id);
    // Concat trimmed: "Pedro Souza" (sem surname)
    expect($response->json('assigned_user.name'))->toBe('Pedro Souza');
})->afterEach(fn () => rich_cleanup('C'));
