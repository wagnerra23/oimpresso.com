<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Vitrine pública: o selo da LISTAGEM escolhe o desconto pelo mesmo critério do PDV.
 *
 * Contrato (lote 1 da auditoria ProductCatalogue, V2 e V3): quem decide qual desconto vale é
 * `ProductUtil::getProductDiscount` — marca e categoria juntas, a de maior prioridade, só sem
 * grupo de preço (`spg` nulo) e incluindo desconto ligado à variação. A listagem casava pelo
 * primeiro desconto com a mesma marca (ou com marca vazia) e dava selo que o PDV não aplica.
 * O grupo de preço do local fica fora (decisão D3).
 *
 * Cada caso confere a listagem E o detalhe, que já usava getProductDiscount: os dois têm de
 * dizer a mesma coisa. Lane: sells-pest.yml. Tenant 98 contra o 99 (ADR 0358). Valores fictícios.
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('discount_variations') || ! Schema::hasColumn('discounts', 'spg')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $tenant = $this->seededTenant();
    $this->biz = (int) $tenant->id;
    $this->user = (int) $tenant->owner_id;
    $scheme = DB::table('invoice_schemes')->where('business_id', $this->biz)->value('id') ?? DB::table('invoice_schemes')->value('id');
    $layout = DB::table('invoice_layouts')->where('business_id', $this->biz)->value('id') ?? DB::table('invoice_layouts')->value('id');
    if (! $scheme || ! $layout) {
        $this->markTestSkipped('Sem invoice_scheme/invoice_layout no banco — as FKs de business_locations não teriam alvo.');
    }
    $this->local = (int) DB::table('business_locations')->insertGetId([
        'business_id' => $this->biz, 'invoice_scheme_id' => $scheme, 'invoice_layout_id' => $layout,
        'name' => '__CPCRIT_LOCAL__', 'country' => 'Brasil', 'state' => 'SC', 'city' => 'Cidade Teste', 'zip_code' => '00000',
        'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $this->camisas = cpcritCategoria($this->biz, $this->user, '__CPCRIT_CAMISAS__');
    $this->calcas = cpcritCategoria($this->biz, $this->user, '__CPCRIT_CALCAS__');
    $this->marca = (int) DB::table('brands')->insertGetId([
        'business_id' => $this->biz, 'name' => '__CPCRIT_MARCA__', 'created_by' => $this->user,
        'created_at' => now(), 'updated_at' => now(),
    ]);
});

function cpcritCategoria(int $bizId, int $userId, string $nome): int
{
    return (int) DB::table('categories')->insertGetId([
        'name' => $nome, 'business_id' => $bizId, 'parent_id' => 0,
        'created_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** @return array{0:int,1:int} [produto, variação] */
function cpcritProduto(object $t, ?int $marca, ?int $categoria, string $tipo = 'single'): array
{
    $carimbo = uniqid();
    $unit = DB::table('units')->where('business_id', $t->biz)->value('id') ?? DB::table('units')->insertGetId([
        'business_id' => $t->biz, 'actual_name' => 'Unidade vitrine', 'short_name' => 'Un', 'allow_decimal' => 1,
        'created_by' => $t->user, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $produto = (int) DB::table('products')->insertGetId([
        'business_id' => $t->biz, 'name' => '__CPCRIT_PRODUTO__', 'type' => $tipo, 'unit_id' => $unit,
        'brand_id' => $marca, 'category_id' => $categoria,
        'tax_type' => 'exclusive', 'enable_stock' => 0, 'sku' => 'CPCRIT-' . $carimbo, 'barcode_type' => 'C128',
        'created_by' => $t->user, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $grupo = DB::table('product_variations')->insertGetId([
        'product_id' => $produto, 'name' => 'DUMMY', 'is_dummy' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $variacao = (int) DB::table('variations')->insertGetId(array_merge([
        'product_id' => $produto, 'product_variation_id' => $grupo, 'name' => 'DUMMY', 'sub_sku' => 'CPCRIT-' . $carimbo,
        'default_purchase_price' => 0, 'dpp_inc_tax' => 0, 'profit_percent' => 0, 'default_sell_price' => 100,
        'sell_price_inc_tax' => 100, 'created_at' => now(), 'updated_at' => now(),
    ], $tipo === 'combo' ? ['combo_variations' => '[]'] : []));
    DB::table('product_locations')->insert(['product_id' => $produto, 'location_id' => $t->local]);

    return [$produto, $variacao];
}

function cpcritDesconto(object $t, ?int $marca, ?int $categoria, float $percentual, array $extra = []): int
{
    return (int) DB::table('discounts')->insertGetId(array_merge([
        'name' => '__CPCRIT_DESCONTO__', 'business_id' => $t->biz, 'brand_id' => $marca, 'category_id' => $categoria,
        'location_id' => $t->local, 'priority' => 1, 'discount_type' => 'percentage', 'discount_amount' => $percentual,
        'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(), 'is_active' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ], $extra));
}

/** Parte inteira do percentual de cada selo, na listagem e no detalhe do produto. */
function cpcritSelos(object $t, int $produto): array
{
    $ler = function (string $html): array {
        preg_match_all('/class="label label-warning discount-badge[^"]*">([^<]*)</', $html, $m);

        return array_map(fn ($s) => (int) preg_replace('/\D/', '', preg_split('/[.,]/', trim($s))[0]), $m[1]);
    };
    $lista = (string) $t->get("/catalogue/{$t->biz}/{$t->local}")->assertOk()->getContent();
    $detalhe = (string) $t->get("/show-catalogue/{$t->biz}/{$produto}?location_id={$t->local}")->assertOk()->getContent();
    expect($lista)->toContain('__CPCRIT_PRODUTO__');

    return ['lista' => $ler($lista), 'detalhe' => $ler($detalhe)];
}

test('controle positivo: produto da categoria do desconto recebe o selo nos dois lugares', function () {
    [$produto] = cpcritProduto($this, null, $this->camisas);
    cpcritDesconto($this, null, $this->camisas, 10);

    expect(cpcritSelos($this, $produto))->toBe(['lista' => [10], 'detalhe' => [10]]);
});

test('produto sem marca não herda desconto de outra categoria só por ter marca vazia', function () {
    [$produto] = cpcritProduto($this, null, $this->calcas);
    cpcritDesconto($this, null, $this->camisas, 10);

    expect(cpcritSelos($this, $produto))->toBe(['lista' => [], 'detalhe' => []]);
});

test('desconto de marca E categoria não vale para a marca em outra categoria', function () {
    [$produto] = cpcritProduto($this, $this->marca, $this->calcas);
    cpcritDesconto($this, $this->marca, $this->camisas, 10);

    expect(cpcritSelos($this, $produto))->toBe(['lista' => [], 'detalhe' => []]);
});

test('vale o de maior prioridade, não o da marca', function () {
    [$produto] = cpcritProduto($this, $this->marca, $this->camisas);
    cpcritDesconto($this, $this->marca, null, 5, ['priority' => 1]);
    cpcritDesconto($this, null, $this->camisas, 20, ['priority' => 5]);

    expect(cpcritSelos($this, $produto))->toBe(['lista' => [20], 'detalhe' => [20]]);
});

test('desconto exclusivo de grupo de preço (spg) não aparece para o público', function () {
    [$produto] = cpcritProduto($this, null, $this->camisas);
    cpcritDesconto($this, null, $this->camisas, 30, ['spg' => '1']);

    expect(cpcritSelos($this, $produto))->toBe(['lista' => [], 'detalhe' => []]);
});

test('desconto ligado à variação aparece na listagem como no detalhe', function () {
    [$produto, $variacao] = cpcritProduto($this, $this->marca, $this->camisas);
    $desconto = cpcritDesconto($this, null, null, 12);
    DB::table('discount_variations')->insert(['discount_id' => $desconto, 'variation_id' => $variacao]);

    expect(cpcritSelos($this, $produto))->toBe(['lista' => [12], 'detalhe' => [12]]);
});

test('combo mostra o selo no detalhe, como o PDV aplica', function () {
    [$produto] = cpcritProduto($this, null, $this->camisas, 'combo');
    cpcritDesconto($this, null, $this->camisas, 10);

    expect(cpcritSelos($this, $produto))->toBe(['lista' => [10], 'detalhe' => [10]]);
});

test('desconto da empresa 99 no mesmo local não vira selo da 98', function () {
    $adversaria = $this->seededSupportClientTenant();
    [$produto] = cpcritProduto($this, null, $this->camisas);
    DB::table('discounts')->insert([
        'name' => '__CPCRIT_ALHEIO__', 'business_id' => $adversaria->id, 'brand_id' => null, 'category_id' => $this->camisas,
        'location_id' => $this->local, 'priority' => 9, 'discount_type' => 'percentage', 'discount_amount' => 40,
        'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(), 'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);

    expect(cpcritSelos($this, $produto))->toBe(['lista' => [], 'detalhe' => []]);
});

test('custo: sem desconto vigente a listagem não faz consulta de desconto; com desconto, uma por produto', function () {
    for ($i = 0; $i < 20; $i++) {
        cpcritProduto($this, null, $this->camisas);
    }
    $contar = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        test()->get("/catalogue/{$this->biz}/{$this->local}")->assertOk();
        $n = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], '`discounts`'))->count();
        DB::disableQueryLog();

        return $n;
    };

    $semDesconto = $contar();
    cpcritDesconto($this, null, $this->calcas, 10);
    $comDesconto = $contar();

    fwrite(STDERR, "\n[custo vitrine] 20 produtos: consultas em discounts sem desconto vigente={$semDesconto}, com desconto vigente={$comDesconto}\n");
    expect($semDesconto)->toBe(1);
    expect($comDesconto)->toBe(21);
});
