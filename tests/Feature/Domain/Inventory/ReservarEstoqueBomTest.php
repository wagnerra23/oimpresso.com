<?php

declare(strict_types=1);

use App\Domain\Fsm\Models\StockReservation;
use App\Domain\Fsm\SideEffects\ReservarEstoque;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;

/**
 * US-INV-003 — ReservarEstoque v2 (BOM expansion).
 *
 * Valida que ReservarEstoque, ao receber 1 linha do payload representando kit,
 * cria 1 stock_reservation POR COMPONENTE-FOLHA (via BomResolver).
 * Produtos simples (sem BOM) seguem criando 1 reservation — comportamento legacy.
 */

class InvBomSubject extends Model
{
    protected $table = 'fsm_inv_bom_subjects';

    protected $guarded = ['id'];

    public $timestamps = false;
}

beforeEach(function () {
    // era-sqlite: cria schema manual (sqlite-friendly). No MySQL persistente do nightly
    // isso corrompe os testes irmãos (lever do floor SDD). Cobertura real é na lane
    // sqlite (per-PR); pula no MySQL.
    if (config('database.default') !== 'sqlite') {
        $this->markTestSkipped('era-sqlite: corruptor de schema compartilhado no MySQL — sqlite-only no burn-down do floor SDD.');
    }

    Schema::create('fsm_inv_bom_subjects', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->unsignedInteger('business_id');
    });

    Schema::create('products', function (Blueprint $t) {
        $t->increments('id');
        $t->unsignedInteger('business_id');
        $t->string('name')->default('test');
        $t->string('sku')->default('SKU');
        $t->string('type')->default('single');
        $t->softDeletes();
        $t->timestamps();
    });

    Schema::create('variations', function (Blueprint $t) {
        $t->increments('id');
        $t->unsignedInteger('product_id');
        $t->string('name')->default('DUMMY');
        $t->string('sub_sku')->default('');
        $t->text('combo_variations')->nullable();
        $t->softDeletes();
        $t->timestamps();
    });

    (require database_path('migrations/2026_05_11_130001_create_stock_reservations_table.php'))->up();
    (require database_path('migrations/2026_05_12_080001_create_product_bom_table.php'))->up();
});

afterEach(function () {
    if (config('database.default') !== 'sqlite') {
        return;
    }

    (require database_path('migrations/2026_05_12_080001_create_product_bom_table.php'))->down();
    (require database_path('migrations/2026_05_11_130001_create_stock_reservations_table.php'))->down();
    foreach (['variations', 'products', 'fsm_inv_bom_subjects'] as $tbl) {
        Schema::dropIfExists($tbl);
    }
});

function invMakeSubject(int $bizId): InvBomSubject
{
    $s = new InvBomSubject;
    $s->business_id = $bizId;
    $s->save();
    return $s;
}

function invMakeProduct(int $bizId, string $type = 'single', string $name = 'P'): int
{
    return DB::table('products')->insertGetId([
        'business_id' => $bizId,
        'name' => $name,
        'sku' => 'SKU-' . uniqid(),
        'type' => $type,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function invMakeVariation(int $productId, ?string $combo = null): int
{
    return DB::table('variations')->insertGetId([
        'product_id' => $productId,
        'name' => 'DUMMY',
        'sub_sku' => 'SUB-' . uniqid(),
        'combo_variations' => $combo,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function invMakeBomRow(int $bizId, int $parent, int $comp, float $qty): int
{
    return DB::table('product_bom')->insertGetId([
        'business_id' => $bizId,
        'parent_product_id' => $parent,
        'parent_variation_id' => null,
        'component_product_id' => $comp,
        'component_variation_id' => null,
        'qty_required' => $qty,
        'is_optional' => false,
        'allow_substitution' => false,
        'sort_order' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('1. reservar kit cria N stock_reservations — 1 por componente-folha', function () {
    $subject = invMakeSubject(1);

    $kit = invMakeProduct(1, 'combo', 'Kit Bomba');
    $kitVar = invMakeVariation($kit);
    $compA = invMakeProduct(1, 'single', 'Bomba Mahle');
    $compB = invMakeProduct(1, 'single', 'Vedação');
    $compC = invMakeProduct(1, 'single', 'Parafuso');

    invMakeBomRow(1, $kit, $compA, 1.0);
    invMakeBomRow(1, $kit, $compB, 1.0);
    invMakeBomRow(1, $kit, $compC, 4.0); // 4 parafusos / kit

    (new ReservarEstoque)->execute($subject, [
        'items' => [
            ['product_id' => $kit, 'variation_id' => $kitVar, 'location_id' => 1, 'qty' => 2.0],
        ],
        'expires_in_days' => 30,
    ]);

    $rows = StockReservation::withoutGlobalScope(ScopeByBusiness::class)->get();
    // Esperado: 3 reservations (uma por componente) — NÃO 1 do kit-pai.
    expect($rows)->toHaveCount(3);

    $byProduct = $rows->keyBy('product_id');
    expect((float) $byProduct[$compA]->qty_reserved)->toBe(2.0);
    expect((float) $byProduct[$compB]->qty_reserved)->toBe(2.0);
    expect((float) $byProduct[$compC]->qty_reserved)->toBe(8.0); // 4 × 2 kits
    // Pai NÃO reservado diretamente.
    expect($rows->where('product_id', $kit))->toHaveCount(0);
});

it('2. reservar produto simples sem BOM cria 1 stock_reservation (comportamento legacy preservado)', function () {
    $subject = invMakeSubject(1);
    $simples = invMakeProduct(1, 'single', 'Produto simples');

    (new ReservarEstoque)->execute($subject, [
        'items' => [
            ['product_id' => $simples, 'variation_id' => 200, 'location_id' => 1, 'qty' => 5.0],
        ],
    ]);

    $rows = StockReservation::withoutGlobalScope(ScopeByBusiness::class)->get();
    expect($rows)->toHaveCount(1);
    expect($rows->first()->product_id)->toBe($simples);
    expect((float) $rows->first()->qty_reserved)->toBe(5.0);
});

it('3. coexistência: produto combo legacy (variations.combo_variations) resolve via fallback', function () {
    $subject = invMakeSubject(1);

    // Combo legacy: produto type=combo + JSON em variations.combo_variations, SEM rows em product_bom.
    $kitLegacy = invMakeProduct(1, 'combo', 'Combo Legacy');
    $compX = invMakeProduct(1, 'single', 'Componente X');
    $compY = invMakeProduct(1, 'single', 'Componente Y');
    $varX = invMakeVariation($compX);
    $varY = invMakeVariation($compY);

    $comboJson = json_encode([
        ['variation_id' => $varX, 'quantity' => 3, 'unit_id' => 1],
        ['variation_id' => $varY, 'quantity' => 1, 'unit_id' => 1],
    ]);
    $kitVar = invMakeVariation($kitLegacy, $comboJson);

    (new ReservarEstoque)->execute($subject, [
        'items' => [
            ['product_id' => $kitLegacy, 'variation_id' => $kitVar, 'location_id' => 1, 'qty' => 1.0],
        ],
    ]);

    $rows = StockReservation::withoutGlobalScope(ScopeByBusiness::class)->get();
    // Fallback legacy expandiu kit em 2 componentes.
    expect($rows)->toHaveCount(2);

    $byProduct = $rows->keyBy('product_id');
    expect((float) $byProduct[$compX]->qty_reserved)->toBe(3.0);
    expect((float) $byProduct[$compY]->qty_reserved)->toBe(1.0);
});
