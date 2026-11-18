<?php

declare(strict_types=1);
// Cobre a etapa 2 da ADR 0448: as receitas da Fabricação viram linhas do product_bom, fiéis ao
// consumo que o sistema calcula hoje, sem duplicar e sem cruzar empresas.

use App\Contracts\Fabricacao\InsumosDaReceita;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Manufacturing\Entities\MfgRecipe;
use Modules\Manufacturing\Entities\MfgRecipeIngredient;
use Modules\Manufacturing\Services\ReceitasParaProductBom;

uses(Tests\TestCase::class, DatabaseTransactions::class);

require_once __DIR__.'/../Support/receita-empresa-fixtures.php';

/**
 * ÂNCORA (contrato, não implementação): ADR 0448 §1 (por_quantidade, sub_unit_id e
 * ingredient_group_id levados; desperdício não, D4) e §6 etapa 2 (comando idempotente, por empresa,
 * com simulação e antes→depois). A conta de referência é a do contrato `InsumosDaReceita`, que é o
 * que o sistema usa hoje: a cópia tem de dar o mesmo consumo por unidade.
 *
 * Tenant 98 contra 99 (ADR 0358). NUNCA biz=4.
 * ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema MySQL do UltimatePOS.');
    }
    if (! Schema::hasTable('mfg_recipes') || ! Schema::hasColumn('product_bom', 'por_quantidade')) {
        $this->markTestSkipped('Tabelas da receita ou colunas da etapa 1 ausentes.');
    }
    $this->usuario = (int) DB::table('users')->min('id');
});

/** Insumo com estoque (o contrato só conta insumo com estoque). Devolve [product_id, variation_id]. */
function bomInsumo(int $biz, string $nome, int $usuario): array
{
    [$produto, $variacao] = mfgEmpProduto($biz, $nome, $usuario);
    DB::table('products')->where('id', $produto)->update(['enable_stock' => 1]);

    return [$produto, $variacao];
}

/**
 * Receita com rendimento 2: 3 un do insumo A, 1 caixa (12 un) do insumo B no grupo G, e mais 1 un
 * do insumo A (insumo repetido soma), com desperdício na linha (que não vai).
 * Devolve [recipe_id, variação feita, variação A, variação B, grupo, sub-unidade caixa].
 */
function bomReceita(int $biz, int $usuario): array
{
    [$feito, $variacaoFeita, $unidade] = mfgEmpProduto($biz, 'Produto feito da cópia', $usuario);
    [, $a] = bomInsumo($biz, 'Insumo A da cópia', $usuario);
    [, $b] = bomInsumo($biz, 'Insumo B da cópia', $usuario);
    $caixa = DB::table('units')->insertGetId([
        'business_id' => $biz, 'actual_name' => 'Caixa com 12', 'short_name' => 'CX12', 'allow_decimal' => 1,
        'base_unit_id' => $unidade, 'base_unit_multiplier' => 12, 'created_by' => $usuario,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $grupo = DB::table('mfg_ingredient_groups')->insertGetId([
        'business_id' => $biz, 'name' => 'Grupo da cópia', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $receita = MfgRecipe::create([
        'product_id' => $feito, 'variation_id' => $variacaoFeita, 'instructions' => 'bom-etapa2',
        'waste_percent' => 5, 'ingredients_cost' => 0, 'extra_cost' => 0, 'total_quantity' => 2, 'final_price' => 0,
    ]);
    MfgRecipeIngredient::create(['mfg_recipe_id' => $receita->id, 'variation_id' => $a, 'quantity' => 3, 'waste_percent' => 10, 'sort_order' => 1]);
    MfgRecipeIngredient::create(['mfg_recipe_id' => $receita->id, 'variation_id' => $b, 'quantity' => 1, 'sub_unit_id' => $caixa, 'mfg_ingredient_group_id' => $grupo, 'sort_order' => 2]);
    MfgRecipeIngredient::create(['mfg_recipe_id' => $receita->id, 'variation_id' => $a, 'quantity' => 1, 'sort_order' => 3]);

    return [$receita->id, $variacaoFeita, $a, $b, $grupo, $caixa];
}

it('ADR 0448 etapa 2 · simulação mostra a cópia, confere o consumo e não grava nada', function () {
    [$receita, $feita, $a, $b, $grupo, $caixa] = bomReceita(MFG_EMP_BIZ, $this->usuario);

    $plano = app(ReceitasParaProductBom::class)->planejar(MFG_EMP_BIZ);

    $linhas = array_values(array_filter($plano['linhas'], fn ($l) => $l['receita_id'] === $receita));
    expect(array_map(fn ($l) => [$l['component_variation_id'], $l['qty_required'], $l['por_quantidade'], $l['sub_unit_id'], $l['ingredient_group_id']], $linhas))
        ->toBe([[$a, '3.0000', '2.0000', null, null], [$b, '1.0000', '2.0000', $caixa, $grupo], [$a, '1.0000', '2.0000', null, null]]);
    expect($plano['divergencias'])->toBe([]);
    expect(DB::table('product_bom')->where('business_id', MFG_EMP_BIZ)->where('parent_variation_id', $feita)->count())->toBe(0);
});

it('ADR 0448 etapa 2 · aplicar grava as linhas e o consumo pelo product_bom é o mesmo do contrato de hoje', function () {
    [, $feita, $a, $b] = bomReceita(MFG_EMP_BIZ, $this->usuario);

    $gravadas = app(ReceitasParaProductBom::class)->aplicar(MFG_EMP_BIZ);
    expect($gravadas)->toBeGreaterThanOrEqual(3);

    $bom = DB::table('product_bom')->where('business_id', MFG_EMP_BIZ)->where('parent_variation_id', $feita)->orderBy('sort_order')->get();
    expect($bom)->toHaveCount(3);
    expect($bom->pluck('regra_consumo')->unique()->all())->toBe([null]);

    // Segunda conta, independente do serviço: por unidade feita, A = (3 + 1) / 2 = 2 e
    // B = 1 caixa × 12 / 2 = 6. O contrato de hoje tem de dar o mesmo.
    $hoje = collect(app(InsumosDaReceita::class)->paraQuantidade(MFG_EMP_BIZ, $feita, '1'))->pluck('quantidade', 'variation_id')->all();
    expect($hoje)->toBe([$a => '2.0000', $b => '6.0000']);
    $pelaCopia = DB::table('product_bom as pb')->leftJoin('units as u', 'u.id', '=', 'pb.sub_unit_id')
        ->where('pb.business_id', MFG_EMP_BIZ)->where('pb.parent_variation_id', $feita)
        ->groupBy('pb.component_variation_id', 'pb.por_quantidade')
        ->selectRaw('pb.component_variation_id, ROUND(SUM(pb.qty_required * COALESCE(u.base_unit_multiplier, 1)) / pb.por_quantidade, 4) AS q')
        ->pluck('q', 'component_variation_id')->map(fn ($q) => (string) $q)->all();
    expect($pelaCopia)->toBe($hoje);
});

it('ADR 0448 etapa 2 · rodar de novo não duplica', function () {
    [, $feita] = bomReceita(MFG_EMP_BIZ, $this->usuario);
    $copia = app(ReceitasParaProductBom::class);

    $copia->aplicar(MFG_EMP_BIZ);
    expect($copia->aplicar(MFG_EMP_BIZ))->toBe(0);
    expect(DB::table('product_bom')->where('business_id', MFG_EMP_BIZ)->where('parent_variation_id', $feita)->count())->toBe(3);
});

it('ADR 0448 etapa 2 · receita de rendimento zero e receita repetida da variação ficam de fora', function () {
    [$usada, $feita] = bomReceita(MFG_EMP_BIZ, $this->usuario);
    $produto = (int) DB::table('variations')->where('id', $feita)->value('product_id');
    [, $insumo] = bomInsumo(MFG_EMP_BIZ, 'Insumo da receita repetida', $this->usuario);
    $repetida = MfgRecipe::create(['product_id' => $produto, 'variation_id' => $feita, 'waste_percent' => 0,
        'ingredients_cost' => 0, 'extra_cost' => 0, 'total_quantity' => 1, 'final_price' => 0]);
    MfgRecipeIngredient::create(['mfg_recipe_id' => $repetida->id, 'variation_id' => $insumo, 'quantity' => 9]);
    [$feitoZero, $variacaoZero] = mfgEmpProduto(MFG_EMP_BIZ, 'Produto de rendimento zero', $this->usuario);
    $zero = MfgRecipe::create(['product_id' => $feitoZero, 'variation_id' => $variacaoZero, 'waste_percent' => 0,
        'ingredients_cost' => 0, 'extra_cost' => 0, 'total_quantity' => 0, 'final_price' => 0]);
    MfgRecipeIngredient::create(['mfg_recipe_id' => $zero->id, 'variation_id' => $insumo, 'quantity' => 1]);

    $plano = app(ReceitasParaProductBom::class)->planejar(MFG_EMP_BIZ);

    expect(collect($plano['receitas'])->pluck('receita_id')->all())->toContain($usada)->not->toContain($repetida->id)->not->toContain($zero->id);
    expect(collect($plano['ignoradas'])->pluck('receita_id')->all())->toContain($repetida->id)->toContain($zero->id);
    expect(collect($plano['linhas'])->pluck('component_variation_id')->all())->not->toContain($insumo);
});

it('ADR 0448 etapa 2 · não copia receita de outra empresa nem insumo de outra empresa [T0]', function () {
    [$nossa, $feita] = bomReceita(MFG_EMP_BIZ, $this->usuario);
    [$alheia, $feitaAlheia] = bomReceita(MFG_EMP_OUTRO, $this->usuario);
    [, $insumoAlheio] = bomInsumo(MFG_EMP_OUTRO, 'Insumo da outra empresa', $this->usuario);
    MfgRecipeIngredient::create(['mfg_recipe_id' => $nossa, 'variation_id' => $insumoAlheio, 'quantity' => 1]);

    $plano = app(ReceitasParaProductBom::class)->planejar(MFG_EMP_BIZ);
    app(ReceitasParaProductBom::class)->aplicar(MFG_EMP_BIZ);

    expect(collect($plano['receitas'])->pluck('receita_id')->all())->not->toContain($alheia);
    expect(collect($plano['ignoradas'])->where('receita_id', $nossa)->pluck('motivo')->all())->toContain('o insumo é de outra empresa');
    expect(DB::table('product_bom')->where('parent_variation_id', $feitaAlheia)->count())->toBe(0);
    expect(DB::table('product_bom')->where('parent_variation_id', $feita)->pluck('component_variation_id')->all())->not->toContain($insumoAlheio);
    expect(DB::table('product_bom')->where('parent_variation_id', $feita)->pluck('business_id')->unique()->all())->toBe([MFG_EMP_BIZ]);
});

it('ADR 0448 etapa 2 · o comando simula por padrão e só grava com --aplicar', function () {
    [, $feita] = bomReceita(MFG_EMP_BIZ, $this->usuario);

    $this->artisan('manufacturing:receitas-para-product-bom', ['business_id' => MFG_EMP_BIZ])
        ->expectsOutputToContain('SIMULAÇÃO, nada é gravado')
        ->assertSuccessful();
    expect(DB::table('product_bom')->where('parent_variation_id', $feita)->count())->toBe(0);

    $this->artisan('manufacturing:receitas-para-product-bom', ['business_id' => MFG_EMP_BIZ, '--aplicar' => true])
        ->assertSuccessful();
    expect(DB::table('product_bom')->where('parent_variation_id', $feita)->count())->toBe(3);
});
