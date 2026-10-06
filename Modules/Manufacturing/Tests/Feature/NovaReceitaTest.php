<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Manufacturing\Entities\MfgRecipe;
use Modules\Manufacturing\Entities\MfgRecipeIngredient;
use Modules\Manufacturing\Services\RecipeBomService;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Janela "Nova receita" — US-MANU-006, decisões [W] 2026-10-06 (SPEC da Fabricação):
 *  - a busca de produto carrega e devolve Categoria/Subcategoria DO PRODUTO + a receita que ele
 *    já tem (para a janela avisar antes de a pessoa preencher), só da empresa da sessão;
 *  - a cópia leva desperdício, custo extra e instruções, e NÃO o preço de venda;
 *  - a cópia cria grupos de ingredientes PRÓPRIOS: renomear o grupo da cópia não renomeia o da
 *    original (antes renomeava — o formulário levava o id do grupo da original).
 *
 * Âncoras positivas: a receita da cópia existe com a linha gravada (o POST chegou ao código que
 * grava), e o grupo da própria receita continua sendo renomeado no lugar (a regra nova não
 * quebrou a edição).
 *
 * Tenant 98 (fictício, ADR 0358) e 99 como a "outra empresa". NUNCA biz=4. Tudo roda dentro de
 * transação desfeita no fim (§5 2026-09-18: mutação no CT 100 persiste).
 * ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
 *
 * @see Modules/Manufacturing/Services/RecipeBomService::buscarProdutosParaReceita()
 * @see Modules/Manufacturing/Services/RecipeBomService::grupoProprioDaReceita()
 * @see resources/js/Pages/Manufacturing/_components/NovaReceitaDialog.tsx
 */
require_once __DIR__.'/../Support/receita-empresa-fixtures.php';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: a janela depende do schema MySQL UltimatePOS (products/variations/categories).');
    }
    foreach (['business', 'users', 'units', 'categories', 'products', 'product_variations', 'variations', 'mfg_recipes', 'mfg_recipe_ingredients', 'mfg_ingredient_groups'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema incompleto — tabela {$t} ausente.");
        }
    }
});

function mfgNovaSessao(): array
{
    return ['currency' => ['code' => 'BRL', 'symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ',']];
}

/** Categoria + subcategoria no produto. Devolve [categoria_id, subcategoria_id]. */
function mfgNovaCategorias(int $biz, int $productId, int $userId, string $cat, string $sub): array
{
    $c = DB::table('categories')->insertGetId([
        'name' => $cat, 'business_id' => $biz, 'parent_id' => 0, 'created_by' => $userId,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $s = DB::table('categories')->insertGetId([
        'name' => $sub, 'business_id' => $biz, 'parent_id' => $c, 'created_by' => $userId,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('products')->where('id', $productId)->update(['category_id' => $c, 'sub_category_id' => $s]);

    return [$c, $s];
}

/**
 * Receita original com UM ingrediente dentro de um grupo, e os campos que a cópia deve levar.
 * Devolve [recipe_id, grupo_id, insumo_variation_id].
 */
function mfgNovaOriginal(int $userId): array
{
    [$product, $variation] = mfgEmpProduto(MFG_EMP_BIZ, 'Produto da receita original', $userId);
    [, $insumo] = mfgEmpProduto(MFG_EMP_BIZ, 'Insumo da original', $userId, 7.5);

    $grupo = DB::table('mfg_ingredient_groups')->insertGetId([
        'name' => 'Grupo da original', 'business_id' => MFG_EMP_BIZ, 'description' => 'descricao da original',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $recipe = MfgRecipe::create([
        'product_id' => $product, 'variation_id' => $variation,
        'instructions' => 'instrucao-da-receita-original', 'waste_percent' => 5,
        'ingredients_cost' => 0, 'extra_cost' => 12, 'production_cost_type' => 'percentage',
        'total_quantity' => 1, 'final_price' => 0,
    ]);
    MfgRecipeIngredient::create([
        'mfg_recipe_id' => $recipe->id, 'variation_id' => $insumo, 'quantity' => 2,
        'mfg_ingredient_group_id' => $grupo,
    ]);

    return [$recipe->id, $grupo, $insumo];
}

/** POST do editor com UMA linha dentro do grupo 0 — o que o formulário da cópia envia. */
function mfgNovaSalvarComGrupo($test, $user, int $variationId, int $insumo, int $grupoId, string $nomeGrupo, ?int $linhaId = null)
{
    $linha = [
        'ingredient_id' => $insumo, 'quantity' => '2', 'waste_percent' => '0', 'sort_order' => 1,
        'ig_index' => 0, 'mfg_ingredient_group_id' => $grupoId,
    ];
    if ($linhaId !== null) {
        $linha['ingredient_line_id'] = $linhaId;
    }

    return $test->actingAs($user)->withSession(mfgNovaSessao())->post('/manufacturing/recipe', [
        'variation_id' => $variationId,
        'ingredients' => [$linha],
        'ingredient_groups' => [0 => $nomeGrupo],
        'ingredient_group_description' => [0 => 'descricao gravada pelo teste'],
        'total' => '0', 'total_quantity' => '1', 'ingredients_cost' => '0',
        'waste_percent' => '5', 'extra_cost' => '12', 'production_cost_type' => 'percentage',
        'instructions' => 'instrucao-da-receita-original',
    ]);
}

describe('UC-RECIPE-12 · Nova receita — busca de produto', function () {
    it('traz Categoria, Subcategoria e a receita existente do produto da empresa', function () {
        $user = mfgEmpUsuario('mfg_nova_receita_test');
        [$comReceita, , ] = mfgEmpReceita(MFG_EMP_BIZ, $user->id, 'Insumo da busca');
        $produtoComReceita = (int) MfgRecipe::find($comReceita)->product_id;
        mfgNovaCategorias(MFG_EMP_BIZ, $produtoComReceita, $user->id, 'Categoria da busca', 'Subcategoria da busca');
        [, $semReceita] = mfgEmpProduto(MFG_EMP_BIZ, 'Produto acabado sem receita', $user->id);

        $achados = collect((new RecipeBomService())->buscarProdutosParaReceita('Produto acabado', MFG_EMP_BIZ))
            ->keyBy('variation_id');

        $com = $achados->firstWhere('product_id', $produtoComReceita);
        expect($com)->not->toBeNull();
        expect($com['categoria'])->toBe('Categoria da busca');
        expect($com['subcategoria'])->toBe('Subcategoria da busca');
        expect($com['receita_id'])->toBe($comReceita);

        expect($achados->has($semReceita))->toBeTrue();
        expect($achados->get($semReceita)['receita_id'])->toBeNull();
    });

    it('não devolve produto de outra empresa', function () {
        $user = mfgEmpUsuario('mfg_nova_receita_test');
        [, $alheio] = mfgEmpProduto(MFG_EMP_OUTRO, 'Produto exclusivo da outra empresa', $user->id);
        [, $proprio] = mfgEmpProduto(MFG_EMP_BIZ, 'Produto exclusivo da empresa', $user->id);

        $ids = array_column((new RecipeBomService())->buscarProdutosParaReceita('Produto exclusivo', MFG_EMP_BIZ), 'variation_id');

        expect($ids)->toContain($proprio);
        expect($ids)->not->toContain($alheio);
    });

    it('a rota da janela responde JSON com o produto da empresa', function () {
        $user = mfgEmpUsuario('mfg_nova_receita_test');
        [, $proprio] = mfgEmpProduto(MFG_EMP_BIZ, 'Produto pela rota da janela', $user->id);

        $resposta = $this->actingAs($user)->withSession(mfgNovaSessao())
            ->getJson('/manufacturing/nova-receita/produtos?q=rota+da+janela');

        $resposta->assertOk();
        expect(array_column($resposta->json('produtos'), 'variation_id'))->toBe([$proprio]);
    });

    it('is-recipe-exist não enxerga receita de outra empresa', function () {
        $user = mfgEmpUsuario('mfg_nova_receita_test');
        [, $alheio] = mfgEmpReceita(MFG_EMP_OUTRO, $user->id, 'Insumo da outra');
        [, $proprio] = mfgEmpReceita(MFG_EMP_BIZ, $user->id, 'Insumo da empresa');

        $this->actingAs($user)->withSession(mfgNovaSessao())->get('/manufacturing/is-recipe-exist/'.$proprio)->assertSeeText('1');
        $this->actingAs($user)->withSession(mfgNovaSessao())->get('/manufacturing/is-recipe-exist/'.$alheio)->assertSeeText('0');
    });
});

describe('UC-RECIPE-13 · Nova receita — cópia', function () {
    it('a página do editor vem com desperdício, custo extra e instruções da receita copiada', function () {
        $user = mfgEmpUsuario('mfg_nova_receita_test');
        [$original] = mfgNovaOriginal($user->id);
        [, $destino] = mfgEmpProduto(MFG_EMP_BIZ, 'Produto destino da copia', $user->id);

        $resposta = $this->actingAs($user)->withSession(mfgNovaSessao())
            ->get('/manufacturing/add-ingredient?variation_id='.$destino.'&copy_recipe_id='.$original);

        $resposta->assertOk();
        $resposta->assertSee('Insumo da original');
        $resposta->assertSee('instrucao-da-receita-original');
        // O tipo do custo extra vem junto (sem ele, 12 viraria valor fixo em vez de percentual).
        expect(preg_match('/<option[^>]*value="percentage"[^>]*selected/', $resposta->getContent()))->toBe(1);
    });

    it('renomear o grupo na cópia não renomeia o grupo da original', function () {
        $user = mfgEmpUsuario('mfg_nova_receita_test');
        [$original, $grupoOriginal, $insumo] = mfgNovaOriginal($user->id);
        [, $destino] = mfgEmpProduto(MFG_EMP_BIZ, 'Produto destino da copia', $user->id);

        mfgNovaSalvarComGrupo($this, $user, $destino, $insumo, $grupoOriginal, 'Grupo renomeado na copia')->assertRedirect();

        // Âncora positiva: a cópia foi gravada com a linha.
        $copia = MfgRecipe::where('variation_id', $destino)->first();
        expect($copia)->not->toBeNull();
        $linha = MfgRecipeIngredient::where('mfg_recipe_id', $copia->id)->first();
        expect($linha)->not->toBeNull();

        // A cópia ganhou grupo próprio, com o nome digitado nela…
        expect((int) $linha->mfg_ingredient_group_id)->not->toBe($grupoOriginal);
        expect(DB::table('mfg_ingredient_groups')->where('id', $linha->mfg_ingredient_group_id)->value('name'))
            ->toBe('Grupo renomeado na copia');

        // …e o grupo da original ficou como estava, ainda com a linha da original.
        expect(DB::table('mfg_ingredient_groups')->where('id', $grupoOriginal)->value('name'))->toBe('Grupo da original');
        expect((int) MfgRecipeIngredient::where('mfg_recipe_id', $original)->value('mfg_ingredient_group_id'))->toBe($grupoOriginal);
    });

    it('salvar a cópia não mexe no preço de venda do produto de destino', function () {
        $user = mfgEmpUsuario('mfg_nova_receita_test');
        [, $grupoOriginal, $insumo] = mfgNovaOriginal($user->id);
        [, $destino] = mfgEmpProduto(MFG_EMP_BIZ, 'Produto destino da copia', $user->id);
        DB::table('variations')->where('id', $destino)->update(['default_sell_price' => 40, 'sell_price_inc_tax' => 46]);

        mfgNovaSalvarComGrupo($this, $user, $destino, $insumo, $grupoOriginal, 'Grupo da original')->assertRedirect();

        expect(MfgRecipe::where('variation_id', $destino)->exists())->toBeTrue();
        $v = DB::table('variations')->where('id', $destino)->first(['default_sell_price', 'sell_price_inc_tax']);
        expect((float) $v->default_sell_price)->toBe(40.0);
        expect((float) $v->sell_price_inc_tax)->toBe(46.0);
    });

    it('editar a própria receita continua renomeando o grupo dela no lugar', function () {
        $user = mfgEmpUsuario('mfg_nova_receita_test');
        [$original, $grupoOriginal, $insumo] = mfgNovaOriginal($user->id);
        $variacao = (int) MfgRecipe::find($original)->variation_id;
        $linhaId = (int) MfgRecipeIngredient::where('mfg_recipe_id', $original)->value('id');

        mfgNovaSalvarComGrupo($this, $user, $variacao, $insumo, $grupoOriginal, 'Grupo renomeado na propria', $linhaId)->assertRedirect();

        expect((int) MfgRecipeIngredient::find($linhaId)->mfg_ingredient_group_id)->toBe($grupoOriginal);
        expect(DB::table('mfg_ingredient_groups')->where('id', $grupoOriginal)->value('name'))->toBe('Grupo renomeado na propria');
    });
});
