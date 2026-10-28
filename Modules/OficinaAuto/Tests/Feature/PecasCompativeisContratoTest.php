<?php
// @covers-us US-AUTO-008

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\OficinaAuto\Entities\Vehicle;
use Modules\OficinaAuto\Services\CatalogoDePecas;
use Tests\Support\WithSeededTenant;

/**
 * US-AUTO-008 — catálogo de peças por veículo (UC-OSPECA-01..03 do ServiceOrders/Show.casos.md).
 *
 * Âncora: decisão [W] 2026-10-26 — "catálogo de peças com marca e modelo em texto; a peça compatível
 * aparece na OS daquele veículo". Tenant 98 × 99. Nunca biz=4 nem biz=164 (piloto da oficina).
 */
uses(Tests\TestCase::class, DatabaseTransactions::class, WithSeededTenant::class);

beforeEach(function () {
    foreach (['vehicles', 'products', 'units', 'oficina_pecas_compativeis'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema ausente ({$t}).");
        }
    }
    try {
        $this->biz = (int) $this->seededTenant()->id;
        $this->outra = (int) $this->seededSupportClientTenant()->id;
    } catch (\Throwable $e) {
        $this->markTestSkipped('Tenant de teste ausente: ' . $e->getMessage());
    }
    $this->peca = function (int $biz, string $nome): int {
        $dono = (int) DB::table('business')->where('id', $biz)->value('owner_id');
        $unit = (int) DB::table('units')->insertGetId([
            'business_id' => $biz, 'actual_name' => 'Un', 'short_name' => 'UN', 'allow_decimal' => 0,
            'created_by' => $dono, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return (int) DB::table('products')->insertGetId([
            'business_id' => $biz, 'name' => $nome, 'type' => 'single', 'unit_id' => $unit, 'tax_type' => 'exclusive',
            'sku' => 'PC-' . random_int(10000, 99999), 'barcode_type' => 'C128', 'enable_stock' => 0,
            'alert_quantity' => 0, 'created_by' => $dono, 'created_at' => now(), 'updated_at' => now(),
        ]);
    };
    $this->veiculo = fn (int $biz, ?string $marca, ?string $modelo, ?int $ano) => Vehicle::withoutGlobalScopes()->findOrFail(
        // SUPERADMIN: teste monta o veículo direto, fora de sessão HTTP.
        DB::table('vehicles')->insertGetId([
            'business_id' => $biz, 'plate' => 'CAT' . random_int(1000, 9999), 'vehicle_type' => 'auto',
            'brand' => $marca, 'model' => $modelo, 'model_year' => $ano, 'created_at' => now(), 'updated_at' => now(),
        ])
    );
    $this->svc = app(CatalogoDePecas::class);
});

it('UC-OSPECA-01 · na OS do veículo aparecem só as peças daquela marca/modelo e ano', function () {
    $pastilha = ($this->peca)($this->biz, 'Pastilha dianteira Gol');
    $filtroMarca = ($this->peca)($this->biz, 'Filtro de óleo VW');
    $deOutroModelo = ($this->peca)($this->biz, 'Pastilha Saveiro');
    $foraDoAno = ($this->peca)($this->biz, 'Correia Gol antigo');

    $this->svc->marcarCompativel($this->biz, $pastilha, 'Volkswagen', 'Gol', 2010, 2020);
    $this->svc->marcarCompativel($this->biz, $filtroMarca, 'VOLKSWAGEN ');           // modelo vazio = toda a marca
    $this->svc->marcarCompativel($this->biz, $deOutroModelo, 'Volkswagen', 'Saveiro');
    $this->svc->marcarCompativel($this->biz, $foraDoAno, 'Volkswagen', 'Gol', 1995, 2005);

    $gol2016 = ($this->veiculo)($this->biz, 'volkswagen', ' gol ', 2016);
    $ids = array_column($this->svc->compativeisCom($this->biz, $gol2016), 'product_id');

    expect($ids)->toContain($pastilha)
        ->and($ids)->toContain($filtroMarca)
        ->and($ids)->not->toContain($deOutroModelo)
        ->and($ids)->not->toContain($foraDoAno)
        ->and(count($ids))->toBe(2);
});

it('UC-OSPECA-02 · veículo sem marca não recebe sugestão, e marcar de novo não duplica', function () {
    $pastilha = ($this->peca)($this->biz, 'Pastilha');
    $a = $this->svc->marcarCompativel($this->biz, $pastilha, 'Fiat', 'Strada');
    $b = $this->svc->marcarCompativel($this->biz, $pastilha, 'Fiat', 'Strada', 2018);

    expect($b)->toBe($a)
        ->and(DB::table('oficina_pecas_compativeis')->where('product_id', $pastilha)->count())->toBe(1)
        ->and($this->svc->compativeisCom($this->biz, ($this->veiculo)($this->biz, null, 'Strada', 2020)))->toBe([]);
});

it('UC-OSPECA-03 · peça e veículo de outra empresa não entram (Tier 0)', function () {
    $alheia = ($this->peca)($this->outra, 'Pastilha da outra empresa');

    expect(fn () => $this->svc->marcarCompativel($this->biz, $alheia, 'Fiat', 'Uno'))
        ->toThrow(InvalidArgumentException::class);

    DB::table('oficina_pecas_compativeis')->insert([
        'business_id' => $this->outra, 'product_id' => $alheia, 'brand' => 'Fiat', 'model' => 'Uno',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $uno = ($this->veiculo)($this->biz, 'Fiat', 'Uno', 2012);
    expect($this->svc->compativeisCom($this->biz, $uno))->toBe([]);

    $unoAlheio = ($this->veiculo)($this->outra, 'Fiat', 'Uno', 2012);
    expect(fn () => $this->svc->compativeisCom($this->biz, $unoAlheio))->toThrow(InvalidArgumentException::class);
});

it('UC-OSPECA-04 · o catálogo nasce desligado', function () {
    expect($this->svc->ligado())->toBeFalse();
});
