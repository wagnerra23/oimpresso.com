<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

/**
 * Multi-tenant security do catálogo público (Modules/ProductCatalogue).
 *
 * Endpoint público `/catalogue/{business_id}/{location_id}` é SEM AUTH —
 * cliente final acessa pelo QR code. Atacante pode tentar enumerar
 * `business_id` ou cruzar `business_id` de A com `location_id` de B.
 *
 * Cobertura:
 *   1. Endpoint exige business_id correto — não vaza catálogo cross-tenant
 *   2. location_id de outro business NÃO retorna produtos
 *   3. show-catalogue exige business_id+product_id consistentes (anti-enumeration)
 *   4. show-catalogue de product_id que NÃO pertence ao business → 404
 *
 * ProductCatalogue NÃO tem Entities próprios — herda cobertura multi-tenant
 * de `App\Product` (global scope do core UltimatePOS). Estes testes validam
 * que o **endpoint de rota pública** respeita o escopo herdado.
 *
 * ADR 0093: multi-tenant isolation Tier 0 IRREVOGÁVEL.
 * ADR 0101: tests com biz=1 (Wagner WR2) + biz=99 fictício — nunca biz=4 (ROTA LIVRE).
 *
 * @see Modules\ProductCatalogue\Http\Controllers\ProductCatalogueController
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: Product/Business core UltimatePOS requer schema MySQL — ADR 0101');
    }
    foreach (['business', 'business_locations', 'products', 'product_locations'] as $tbl) {
        if (! Schema::hasTable($tbl)) {
            $this->markTestSkipped("Tabela {$tbl} ausente — rode migrations core UltimatePOS primeiro");
        }
    }
});

const BIZ_WAGNER_PC = 1;
const BIZ_FICTICIO_PC = 99;

it('catalogo publico: rota /catalogue/{business}/{location} esta registrada (sanity)', function () {
    $routes = \Route::getRoutes();
    $uris = collect($routes)->map(fn ($r) => $r->uri())->all();

    expect($uris)->toContain('catalogue/{business_id}/{location_id}');
});

it('catalogo publico: business_id=99 fictício retorna 404 (nao vaza estrutura)', function () {
    // Atacante tenta acessar business inexistente — deve dar 404, não 500/dump de erro
    $resposta = $this->get('/catalogue/' . BIZ_FICTICIO_PC . '/999999');

    expect($resposta->status())->toBeIn([404, 302])
        ->and($resposta->status())->not->toBe(500);
});

it('catalogo publico: location_id de outro business retorna 404 (anti-cross-tenant)', function () {
    // Garante que se atacante pega business_id=1 mas tenta passar location_id
    // de business=99, o endpoint barra (BusinessLocation::where('business_id', $bid)->findOrFail)

    // Buscar location de business=99 se existir, senão skip
    $locBiz99 = DB::table('business_locations')->where('business_id', BIZ_FICTICIO_PC)->first();

    if (! $locBiz99) {
        // Inserir location fictícia direto via DB (sem trigger Eloquent)
        DB::table('business_locations')->insert([
            'business_id' => BIZ_FICTICIO_PC,
            'name'        => 'Location Fictícia biz=99',
            'location_id' => 'LOC-X99-TEST',
            'is_active'   => 1,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
        $locBiz99 = DB::table('business_locations')
            ->where('business_id', BIZ_FICTICIO_PC)
            ->where('location_id', 'LOC-X99-TEST')
            ->first();
    }

    // Atacante tenta cruzar: business_id=1 + location_id pertencente a biz=99
    $resposta = $this->get('/catalogue/' . BIZ_WAGNER_PC . '/' . $locBiz99->id);

    // Deve dar 404 (findOrFail) — location não pertence ao business=1
    expect($resposta->status())->toBeIn([404, 302])
        ->and($resposta->status())->not->toBe(200);
})->afterEach(function () {
    DB::table('business_locations')
        ->where('business_id', BIZ_FICTICIO_PC)
        ->where('location_id', 'LOC-X99-TEST')
        ->delete();
});

it('show-catalogue: product_id de outro business retorna 404 (anti-enumeration)', function () {
    // Verifica rota show-catalogue/{business}/{product} respeita escopo Product
    $routes = \Route::getRoutes();
    $uris = collect($routes)->map(fn ($r) => $r->uri())->all();
    expect($uris)->toContain('show-catalogue/{business_id}/{product_id}');

    // Buscar product de biz=99 se existir; se não, criar fictício DB direto
    $productBiz99 = DB::table('products')->where('business_id', BIZ_FICTICIO_PC)->first();

    if (! $productBiz99) {
        DB::table('products')->insert([
            'business_id'   => BIZ_FICTICIO_PC,
            'name'          => 'Product Fictício biz=99 SEC',
            'type'          => 'single',
            'unit_id'       => 1,
            'sku'           => 'SKU-X99-SEC-TEST',
            'created_by'    => 1,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
        $productBiz99 = DB::table('products')
            ->where('sku', 'SKU-X99-SEC-TEST')
            ->first();
    }

    // Atacante: business_id=1 + product_id de biz=99 — deve dar 404
    $resposta = $this->get('/show-catalogue/' . BIZ_WAGNER_PC . '/' . $productBiz99->id);

    expect($resposta->status())->toBeIn([404, 302])
        ->and($resposta->status())->not->toBe(200);
})->afterEach(function () {
    DB::table('products')->where('sku', 'SKU-X99-SEC-TEST')->delete();
});

it('rota publica nao expoe lista enumeravel de business (sem index global)', function () {
    // Não deve haver rota /catalogue ou /catalogues sem business_id (lista global)
    $routes = \Route::getRoutes();
    $uris = collect($routes)->map(fn ($r) => $r->uri())->all();

    expect($uris)->not->toContain('catalogue');
    expect($uris)->not->toContain('catalogues');
    expect($uris)->not->toContain('catalogue/');
});
