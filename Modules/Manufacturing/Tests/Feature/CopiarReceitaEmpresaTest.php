<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Manufacturing\Entities\MfgRecipe;
use Modules\Manufacturing\Entities\MfgRecipeIngredient;
use Modules\Manufacturing\Services\RecipeBomService;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * "Copiar da receita" (modal `recipe/create` → GET /manufacturing/add-ingredient) só copia
 * receita do PRÓPRIO business — Tier 0, ADR 0093.
 *
 * O `copy_recipe_id` chega pela query string. Até 2026-10-05 o controller fazia
 * `MfgRecipe::with(...)->find($id)` sem tenant: o servidor LIA a receita de OUTRA empresa, e a
 * página só não a mostrava por acaso — a unidade de cada ingrediente é buscada no business da
 * sessão com `findOrFail`, então a página dava 404 (medido: run 37316751680, este teste antes
 * da correção). Com a regra, a página abre normal (200) e sem a cópia — é o que se asserta.
 *
 * O caso da receita do próprio business é a âncora positiva: prova que a requisição chega
 * ao código da cópia, para a ausência do ingrediente alheio não ser página que nem abriu.
 *
 * Tenant 98 (fictício, ADR 0358) e 99 como a "outra empresa". NUNCA biz=4.
 * Tudo roda dentro de transação desfeita no fim (§5 2026-09-18: mutação no CT 100 persiste).
 * ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
 *
 * @see Modules/Manufacturing/Services/RecipeBomService::receitaParaCopiar()
 * @see Modules/Manufacturing/Http/Controllers/RecipeController::addIngredients()
 */
const COPIA_BIZ = 98;
const COPIA_OUTRO = 99;

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: a cópia depende do schema MySQL UltimatePOS (products/variations/units).');
    }
    foreach (['business', 'users', 'units', 'products', 'product_variations', 'variations', 'mfg_recipes', 'mfg_recipe_ingredients'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema incompleto — tabela {$t} ausente.");
        }
    }
});

/** `id` é guarded no Business: id e dono vão explícitos (mesmo idioma do CrmLeadShowEscopoTest). */
function copiaNegocio(int $biz): void
{
    if (Business::whereKey($biz)->exists()) {
        return;
    }
    $dono = (int) DB::table('users')->min('id');
    (new Business)->forceFill(['id' => $biz, 'name' => 'Tenant fictício mfg '.$biz, 'currency_id' => 1, 'owner_id' => $dono])->save();
}

function copiaUsuario(): User
{
    copiaNegocio(COPIA_BIZ);
    $user = User::firstOrCreate(['username' => 'mfg_copia_receita_test'], [
        'email' => 'mfg_copia_receita_test@test.local', 'password' => bcrypt('secret'),
        'business_id' => COPIA_BIZ, 'first_name' => 'Copia', 'last_name' => 'Receita',
        'user_type' => 'user', 'allow_login' => 1,
    ]);
    $user->givePermissionTo(Permission::firstOrCreate(['name' => 'manufacturing.add_recipe', 'guard_name' => 'web']));

    // A rota exige `superadmin` OU o pacote `manufacturing_module` na assinatura. A lista de
    // administradores dá o `superadmin` sem depender do pacote do ambiente de teste.
    config(['constants.administrator_usernames' => $user->username]);

    return $user;
}

/** Unidade + produto + variação no business. Devolve [product_id, variation_id]. */
function copiaProduto(int $biz, string $nome, int $userId, float $custo = 0.0): array
{
    copiaNegocio($biz);
    $unit = DB::table('units')->insertGetId([
        'business_id' => $biz, 'actual_name' => 'Unidade copia', 'short_name' => 'UC',
        'allow_decimal' => 1, 'created_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $sufixo = random_int(100000, 999999);
    $product = DB::table('products')->insertGetId([
        'business_id' => $biz, 'name' => $nome, 'type' => 'single', 'unit_id' => $unit,
        'tax_type' => 'exclusive', 'sku' => 'MFGCOPIA-'.$sufixo, 'barcode_type' => 'C128',
        'enable_stock' => 0, 'alert_quantity' => 0, 'created_by' => $userId,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $pv = DB::table('product_variations')->insertGetId([
        'product_id' => $product, 'name' => 'DUMMY', 'is_dummy' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $variation = DB::table('variations')->insertGetId([
        'name' => 'DUMMY', 'product_id' => $product, 'product_variation_id' => $pv,
        'sub_sku' => 'MFGCOPIA-'.$sufixo, 'default_purchase_price' => $custo, 'dpp_inc_tax' => $custo,
        'profit_percent' => 0, 'default_sell_price' => 0, 'sell_price_inc_tax' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return [$product, $variation];
}

/** Receita no business com UM ingrediente chamado `$ingrediente`. Devolve o id da receita. */
function copiaReceita(int $biz, int $userId, string $ingrediente): int
{
    // O nome do produto acabado NÃO repete o do insumo: o assertDontSee procura o insumo.
    [$product, $variation] = copiaProduto($biz, 'Produto acabado da receita', $userId);
    [, $insumo] = copiaProduto($biz, $ingrediente, $userId, 7.5);

    $recipe = MfgRecipe::create([
        'product_id' => $product, 'variation_id' => $variation, 'instructions' => 'mfg-copia-receita-test',
        'waste_percent' => 0, 'ingredients_cost' => 0, 'extra_cost' => 0,
        'total_quantity' => 1, 'final_price' => 0,
    ]);
    MfgRecipeIngredient::create([
        'mfg_recipe_id' => $recipe->id, 'variation_id' => $insumo, 'quantity' => 2,
    ]);

    return $recipe->id;
}

describe('Copiar da receita — só do próprio business (Tier 0)', function () {
    it('receitaParaCopiar devolve a receita do business e recusa a de outro', function () {
        $user = copiaUsuario();
        $propria = copiaReceita(COPIA_BIZ, $user->id, 'Insumo copia proprio');
        $alheia = copiaReceita(COPIA_OUTRO, $user->id, 'Insumo copia alheio');

        $service = new RecipeBomService();

        $ok = $service->receitaParaCopiar($propria, COPIA_BIZ, ['ingredients']);
        expect($ok)->not->toBeNull();
        expect($ok->ingredients)->toHaveCount(1);

        // A receita existe (a outra empresa enxerga), mas não para o business 98.
        expect($service->receitaParaCopiar($alheia, COPIA_OUTRO))->not->toBeNull();
        expect($service->receitaParaCopiar($alheia, COPIA_BIZ))->toBeNull();
    });

    it('a página de ingredientes copia a receita do próprio business (âncora positiva)', function () {
        $user = copiaUsuario();
        $propria = copiaReceita(COPIA_BIZ, $user->id, 'Insumo copia proprio');
        [, $alvo] = copiaProduto(COPIA_BIZ, 'Produto novo sem receita', $user->id);

        $sessao = ['currency' => ['code' => 'BRL', 'symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ',']];
        $resposta = $this->actingAs($user)->withSession($sessao)
            ->get('/manufacturing/add-ingredient?variation_id='.$alvo.'&copy_recipe_id='.$propria);

        $resposta->assertOk();
        $resposta->assertSee('Insumo copia proprio');
    });

    it('a página de ingredientes não mostra ingrediente de receita de outra empresa', function () {
        $user = copiaUsuario();
        $alheia = copiaReceita(COPIA_OUTRO, $user->id, 'Insumo copia alheio');
        [, $alvo] = copiaProduto(COPIA_BIZ, 'Produto novo sem receita', $user->id);

        $sessao = ['currency' => ['code' => 'BRL', 'symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ',']];
        $resposta = $this->actingAs($user)->withSession($sessao)
            ->get('/manufacturing/add-ingredient?variation_id='.$alvo.'&copy_recipe_id='.$alheia);

        $resposta->assertOk();
        $resposta->assertDontSee('Insumo copia alheio');
    });
});
