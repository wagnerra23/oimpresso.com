<?php

declare(strict_types=1);

use App\Domain\Inventory\Models\ProductBom;
use App\Domain\Inventory\Services\BomResolver;
use App\Product;
use App\Variation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;

/**
 * US-INV-001..003 — BomResolver service tests.
 *
 * Default biz=1 (Wagner). Cross-tenant adversário biz=99 (BusinessIdGuard convention,
 * feedback_test_biz_99_cross_tenant_convention).
 *
 * Stack: SQLite :memory: (phpunit.xml). Migration product_bom + tabelas
 * mínimas products/variations criadas inline (UPos schema é grande, só
 * colunas necessárias pro resolver).
 */
beforeEach(function () {
    // products mínimo
    Schema::create('products', function (Blueprint $t) {
        $t->increments('id');
        $t->unsignedInteger('business_id');
        $t->string('name')->default('test');
        $t->string('sku')->default('SKU');
        $t->string('type')->default('single'); // single | variable | combo | modifier
        $t->softDeletes();
        $t->timestamps();
    });

    // variations mínimo (cast combo_variations array vem do Model)
    Schema::create('variations', function (Blueprint $t) {
        $t->increments('id');
        $t->unsignedInteger('product_id');
        $t->string('name')->default('DUMMY');
        $t->string('sub_sku')->default('');
        $t->text('combo_variations')->nullable();
        $t->softDeletes();
        $t->timestamps();
    });

    (require database_path('migrations/2026_05_12_080001_create_product_bom_table.php'))->up();
});

afterEach(function () {
    (require database_path('migrations/2026_05_12_080001_create_product_bom_table.php'))->down();
    foreach (['variations', 'products'] as $tbl) {
        Schema::dropIfExists($tbl);
    }
});

/** Cria produto simples e retorna id. */
function bomMakeProduct(int $bizId, string $type = 'single', string $name = 'Prod'): int
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

/** Cria variação simples e retorna id. */
function bomMakeVariation(int $productId, ?string $comboJson = null): int
{
    return DB::table('variations')->insertGetId([
        'product_id' => $productId,
        'name' => 'DUMMY',
        'sub_sku' => 'SUB-' . uniqid(),
        'combo_variations' => $comboJson,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/** Cria row product_bom (skipa global scope porque test não tem session). */
function bomMakeRow(int $bizId, int $parentId, int $compId, ?int $compVarId, float $qty, array $extra = []): int
{
    return DB::table('product_bom')->insertGetId(array_merge([
        'business_id' => $bizId,
        'parent_product_id' => $parentId,
        'parent_variation_id' => null,
        'component_product_id' => $compId,
        'component_variation_id' => $compVarId,
        'qty_required' => $qty,
        'is_optional' => false,
        'allow_substitution' => false,
        'sort_order' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ], $extra));
}

it('1. resolve produto simples sem BOM retorna 1 row representando o próprio produto', function () {
    $pid = bomMakeProduct(1, 'single', 'Produto simples');

    $resolver = new BomResolver;
    $resolved = $resolver->resolve(businessId: 1, productId: $pid, variationId: null, qtyParent: 3.0);

    expect($resolved)->toHaveCount(1);
    expect($resolved[0]['product_id'])->toBe($pid);
    expect((float) $resolved[0]['qty'])->toBe(3.0);
});

it('2. resolve kit nível 1 retorna N componentes-folha com qty multiplicada', function () {
    $parent = bomMakeProduct(1, 'combo', 'Kit Bomba VW Gol');
    $bomba = bomMakeProduct(1, 'single', 'Bomba Mahle');
    $vedacao = bomMakeProduct(1, 'single', 'Vedação Sabó');
    $parafuso = bomMakeProduct(1, 'single', 'Parafuso M8');

    bomMakeRow(1, $parent, $bomba, null, 1.0);
    bomMakeRow(1, $parent, $vedacao, null, 1.0);
    bomMakeRow(1, $parent, $parafuso, null, 4.0); // 4 parafusos por kit

    $resolver = new BomResolver;
    $resolved = $resolver->resolve(businessId: 1, productId: $parent, qtyParent: 2.0); // 2 kits

    expect($resolved)->toHaveCount(3);

    $byProduct = collect($resolved)->keyBy('product_id');
    expect((float) $byProduct[$bomba]['qty'])->toBe(2.0);
    expect((float) $byProduct[$vedacao]['qty'])->toBe(2.0);
    expect((float) $byProduct[$parafuso]['qty'])->toBe(8.0); // 4 × 2 kits
});

it('3. resolve kit aninhado nível 2 retorna componentes-folha (não intermediário)', function () {
    // Kit pai → Sub-kit → 2 folhas
    $parent = bomMakeProduct(1, 'combo', 'Mega Kit');
    $subKit = bomMakeProduct(1, 'combo', 'Sub Kit Bomba');
    $folhaA = bomMakeProduct(1, 'single', 'Folha A');
    $folhaB = bomMakeProduct(1, 'single', 'Folha B');

    // Mega kit = 1 sub-kit
    bomMakeRow(1, $parent, $subKit, null, 1.0);
    // Sub-kit = 2 folha A + 3 folha B
    bomMakeRow(1, $subKit, $folhaA, null, 2.0);
    bomMakeRow(1, $subKit, $folhaB, null, 3.0);

    $resolver = new BomResolver;
    $resolved = $resolver->resolve(businessId: 1, productId: $parent, qtyParent: 1.0);

    // Esperado: APENAS folhas (não sub-kit).
    $productIds = array_column($resolved, 'product_id');
    expect($productIds)->toContain($folhaA);
    expect($productIds)->toContain($folhaB);
    expect($productIds)->not->toContain($subKit);

    $byProduct = collect($resolved)->keyBy('product_id');
    expect((float) $byProduct[$folhaA]['qty'])->toBe(2.0);
    expect((float) $byProduct[$folhaB]['qty'])->toBe(3.0);
});

it('4. detecta circular dependency e lança LogicException', function () {
    // A → B, B → A (ciclo)
    $a = bomMakeProduct(1, 'combo', 'Kit A');
    $b = bomMakeProduct(1, 'combo', 'Kit B');
    bomMakeRow(1, $a, $b, null, 1.0);
    bomMakeRow(1, $b, $a, null, 1.0);

    $resolver = new BomResolver;

    expect(fn () => $resolver->resolve(businessId: 1, productId: $a))
        ->toThrow(LogicException::class);
});

it('5. fallback combo_variations legacy se product_bom vazio e product.type=combo', function () {
    // Produto combo legacy SEM rows em product_bom — força fallback JSON.
    $parent = bomMakeProduct(1, 'combo', 'Combo Legacy');
    $compA = bomMakeProduct(1, 'single', 'Componente Legacy A');
    $compB = bomMakeProduct(1, 'single', 'Componente Legacy B');

    // Variação dos componentes (legacy aponta variation_id, não product_id)
    $varA = bomMakeVariation($compA);
    $varB = bomMakeVariation($compB);

    // JSON legacy: variation_id + quantity
    $comboJson = json_encode([
        ['variation_id' => $varA, 'quantity' => 5, 'unit_id' => 1],
        ['variation_id' => $varB, 'quantity' => 2, 'unit_id' => 1],
    ]);
    bomMakeVariation($parent, $comboJson);

    $resolver = new BomResolver;
    $resolved = $resolver->resolve(businessId: 1, productId: $parent, qtyParent: 1.0);

    expect($resolved)->toHaveCount(2);

    $byProduct = collect($resolved)->keyBy('product_id');
    expect((float) $byProduct[$compA]['qty'])->toBe(5.0);
    expect((float) $byProduct[$compB]['qty'])->toBe(2.0);
    // Marca rastreável de fallback
    expect($byProduct[$compA]['from_legacy'] ?? false)->toBeTrue();
});

it('6. multi-tenant: kit biz=1 não vaza pra biz=99 (cross-tenant guard)', function () {
    // Mesmo product_id existe em 2 businesses (improvável em prod mas testa guard).
    // Aqui criamos kits separados em biz=1 e biz=99 e validamos isolamento.
    $parentBiz1 = bomMakeProduct(1, 'combo', 'Kit Biz1');
    $compBiz1 = bomMakeProduct(1, 'single', 'Comp Biz1');
    bomMakeRow(1, $parentBiz1, $compBiz1, null, 7.0);

    $parentBiz99 = bomMakeProduct(99, 'combo', 'Kit Biz99');
    $compBiz99 = bomMakeProduct(99, 'single', 'Comp Biz99');
    bomMakeRow(99, $parentBiz99, $compBiz99, null, 11.0);

    $resolver = new BomResolver;

    // Resolvendo COM business_id=1, NUNCA deve enxergar BOM da biz=99.
    $resolvedBiz1 = $resolver->resolve(businessId: 1, productId: $parentBiz1, qtyParent: 1.0);
    expect($resolvedBiz1)->toHaveCount(1);
    expect($resolvedBiz1[0]['product_id'])->toBe($compBiz1);
    expect((float) $resolvedBiz1[0]['qty'])->toBe(7.0);

    // Resolvendo o ID do kit biz=99 mas com business_id=1 → trata como produto
    // simples (sem BOM visível). NÃO vaza dados da biz=99.
    $resolvedCross = $resolver->resolve(businessId: 1, productId: $parentBiz99, qtyParent: 1.0);
    expect($resolvedCross)->toHaveCount(1);
    expect($resolvedCross[0]['product_id'])->toBe($parentBiz99);
    // qty deve ser 1.0 (próprio produto, não 11.0 do componente da biz=99)
    expect((float) $resolvedCross[0]['qty'])->toBe(1.0);

    // Sanity check: ProductBom Model com Tier 0 não enxerga rows de outra biz.
    // (skip global scope porque test não tem session ativa)
    $allBiz1 = ProductBom::withoutGlobalScope(ScopeByBusiness::class)
        ->where('business_id', 1)
        ->count();
    expect($allBiz1)->toBe(1);
});
