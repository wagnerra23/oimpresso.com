<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Manufacturing\Entities\MfgIngredientGroup;
use Modules\Manufacturing\Entities\MfgRecipe;
use Modules\Manufacturing\Entities\MfgRecipeIngredient;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Editor de ingredientes React (US-MANU-006) — etapa 1: abrir a ficha.
 * GET /manufacturing/add-ingredient?variation_id=N&tela=nova → Manufacturing/IngredientesEditor
 * GET /manufacturing/editor-receita/insumos?q= → busca de insumo
 *
 * Fonte dos casos: handoff Fabricação §5 (regras 3 e 5, a busca de 7) e §9 (tenant), não o código.
 * Contrato: resources/js/Pages/Manufacturing/IngredientesEditor.casos.md.
 *
 * Tenant 98 (fictício, ADR 0358) e 99 como a "outra empresa". NUNCA biz=4. Transação desfeita no fim.
 * ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
 */
require_once __DIR__.'/../Support/receita-empresa-fixtures.php';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: depende do schema MySQL UltimatePOS (products/variations/units).');
    }
    foreach (['business', 'users', 'units', 'products', 'product_variations', 'variations', 'mfg_recipes', 'mfg_recipe_ingredients', 'mfg_ingredient_groups'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema incompleto — tabela {$t} ausente.");
        }
    }
});

/**
 * Usuário da empresa 98 com EXATAMENTE as permissões pedidas. Está na lista de administradores só
 * para passar a barreira do pacote (`can('superadmin')`); as permissões `manufacturing.*` seguem
 * pelo Spatie — o `Gate::before` só libera `superadmin/backup/manage_modules` por essa lista.
 */
function mfgEdUsuario(string $username, array $permissoes): User
{
    mfgEmpNegocio(MFG_EMP_BIZ);
    $user = User::firstOrCreate(['username' => $username], [
        'email' => $username.'@test.local', 'password' => bcrypt('secret'),
        'business_id' => MFG_EMP_BIZ, 'first_name' => 'Editor', 'last_name' => 'Receita',
        'user_type' => 'user', 'allow_login' => 1,
    ]);
    foreach ($permissoes as $p) {
        $user->givePermissionTo(Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']));
    }
    config(['constants.administrator_usernames' => $user->username]);

    return $user;
}

/** Sub-unidade "caixa" de `$unidadeBase` com o multiplicador dado. */
function mfgEdSubUnidade(int $unidadeBase, float $multiplicador, int $userId): int
{
    return DB::table('units')->insertGetId([
        'business_id' => MFG_EMP_BIZ, 'actual_name' => 'Caixa x'.$multiplicador, 'short_name' => 'CX',
        'allow_decimal' => 1, 'base_unit_id' => $unidadeBase, 'base_unit_multiplier' => $multiplicador,
        'created_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

/**
 * Receita com 1 ingrediente em sub-unidade, num grupo. Devolve [variation do produto, recipe_id, linha_id, grupo_id].
 * À mão: 0,5 caixa × 5 un × 4,00 = 10,00 de ingredientes.
 */
function mfgEdReceita(int $userId): array
{
    [$produto, $variacao] = mfgEmpProduto(MFG_EMP_BIZ, 'Banner do editor', $userId);
    [, $insumo, $unidadeInsumo] = mfgEmpProduto(MFG_EMP_BIZ, 'Lona do editor', $userId, 4.0);
    $caixa = mfgEdSubUnidade($unidadeInsumo, 5.0, $userId);
    $grupo = MfgIngredientGroup::create(['name' => 'Impressão', 'business_id' => MFG_EMP_BIZ, 'description' => 'grupo do editor']);
    $recipe = MfgRecipe::create([
        'product_id' => $produto, 'variation_id' => $variacao, 'instructions' => 'instrução do editor',
        'waste_percent' => 10, 'ingredients_cost' => 0, 'extra_cost' => 2.5, 'production_cost_type' => 'fixed',
        'total_quantity' => 2, 'final_price' => 0,
    ]);
    $linha = MfgRecipeIngredient::create([
        'mfg_recipe_id' => $recipe->id, 'variation_id' => $insumo, 'quantity' => 0.5,
        'sub_unit_id' => $caixa, 'mfg_ingredient_group_id' => $grupo->id, 'sort_order' => 1,
    ]);

    return [$variacao, $recipe->id, $linha->id, $grupo->id, $insumo, $caixa];
}

function mfgEdAbrir($test, User $user, int $variationId, string $extra = '')
{
    return $test->actingAs($user)->get("/manufacturing/add-ingredient?variation_id={$variationId}&tela=nova{$extra}");
}

describe('Editor de ingredientes — abrir a ficha (US-MANU-006, etapa 1)', function () {
    it('UC-INGRED-01: abre a ficha da receita com grupo, linha, custo de hoje e as sub-unidades do insumo', function () {
        $user = mfgEdUsuario('mfg_editor_grava', ['manufacturing.access_recipe', 'manufacturing.add_recipe']);
        [$variacao, $recipeId, $linhaId, $grupoId, $insumo, $caixa] = mfgEdReceita($user->id);

        mfgEdAbrir($this, $user, $variacao)->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('Manufacturing/IngredientesEditor')
            ->where('produto.variation_id', $variacao)
            ->where('produto.nome', 'Banner do editor')
            ->where('receita.id', $recipeId)
            ->where('receita.copiada_de', null)
            ->where('receita.total_quantity', fn ($v) => (float) $v === 2.0)
            ->where('receita.waste_percent', fn ($v) => (float) $v === 10.0)
            ->where('receita.extra_cost', 2.5)
            ->where('receita.production_cost_type', 'fixed')
            ->where('receita.instructions', 'instrução do editor')
            ->has('grupos', 1)
            ->where('grupos.0.id', $grupoId)
            ->where('grupos.0.nome', 'Impressão')
            ->has('grupos.0.itens', 1)
            ->where('grupos.0.itens.0.linha_id', $linhaId)
            ->where('grupos.0.itens.0.variation_id', $insumo)
            ->where('grupos.0.itens.0.custo_unitario', fn ($v) => (float) $v === 4.0)
            ->where('grupos.0.itens.0.quantidade', 0.5)
            ->where('grupos.0.itens.0.sub_unit_id', $caixa)
            ->where('perms.editar', true)
            ->etc());

        // As sub-unidades do insumo: a própria unidade (×1) e a caixa (×5), com o multiplicador que o custo usa.
        $sub = collect(mfgEdAbrir($this, $user, $variacao)->viewData('page')['props']['grupos'][0]['itens'][0]['sub_unidades']);
        expect((float) $sub->firstWhere('id', $caixa)['multiplicador'])->toBe(5.0);
        expect($sub->pluck('multiplicador')->map(fn ($m) => (float) $m)->sort()->values()->all())->toBe([1.0, 5.0]);
    });

    it('UC-INGRED-02: ficha de produto de outra empresa não abre, e a busca não traz insumo dela', function () {
        $user = mfgEdUsuario('mfg_editor_grava', ['manufacturing.access_recipe', 'manufacturing.add_recipe']);
        [, $alheio] = mfgEmpProduto(MFG_EMP_OUTRO, 'Banner alheio do editor', $user->id);
        mfgEmpProduto(MFG_EMP_OUTRO, 'Lona alheia do editor', $user->id, 9.0);
        [, $proprio] = mfgEmpProduto(MFG_EMP_BIZ, 'Lona propria do editor', $user->id, 4.0);

        mfgEdAbrir($this, $user, $alheio)->assertNotFound();

        $nomes = collect($this->actingAs($user)->getJson('/manufacturing/editor-receita/insumos?q=do+editor')
            ->assertOk()->json('insumos'))->pluck('nome')->all();
        expect($nomes)->toContain('Lona propria do editor');
        expect($nomes)->not->toContain('Lona alheia do editor');
        expect($nomes)->not->toContain('Banner alheio do editor');
        expect(collect($this->actingAs($user)->getJson('/manufacturing/editor-receita/insumos?q=do+editor')->json('insumos'))
            ->firstWhere('variation_id', $proprio)['custo_unitario'])->toEqual(4);
    });

    it('UC-INGRED-03: quem só consulta abre em leitura; sem consultar, 403; a busca exige poder gravar', function () {
        $leitor = mfgEdUsuario('mfg_editor_le', ['manufacturing.access_recipe']);
        [$variacao] = mfgEdReceita($leitor->id);

        mfgEdAbrir($this, $leitor, $variacao)->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('Manufacturing/IngredientesEditor')
            ->where('perms.editar', false)
            ->etc());
        $this->actingAs($leitor)->getJson('/manufacturing/editor-receita/insumos?q=editor')->assertForbidden();

        $semAcesso = mfgEdUsuario('mfg_editor_sem', []);
        mfgEdAbrir($this, $semAcesso, $variacao)->assertForbidden();
    });

    it('UC-INGRED-04: produto sem receita copiando de outra traz a ficha sem linhas nem grupos da original', function () {
        $user = mfgEdUsuario('mfg_editor_grava', ['manufacturing.access_recipe', 'manufacturing.add_recipe']);
        [, $origem, , , $insumo] = mfgEdReceita($user->id);
        [, $novoProduto] = mfgEmpProduto(MFG_EMP_BIZ, 'Banner novo do editor', $user->id);

        mfgEdAbrir($this, $user, $novoProduto, "&copy_recipe_id={$origem}")->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('receita.id', null)
            ->where('receita.copiada_de', $origem)
            ->where('receita.extra_cost', 2.5)
            ->where('receita.instructions', 'instrução do editor')
            ->where('receita.sub_unit_id', null)
            ->where('grupos.0.id', null)
            ->where('grupos.0.nome', 'Impressão')
            ->where('grupos.0.itens.0.linha_id', null)
            ->where('grupos.0.itens.0.variation_id', $insumo)
            ->etc());
    });

    it('UC-INGRED-05: a busca de insumo devolve no máximo 7', function () {
        $user = mfgEdUsuario('mfg_editor_grava', ['manufacturing.access_recipe', 'manufacturing.add_recipe']);
        for ($i = 1; $i <= 9; $i++) {
            mfgEmpProduto(MFG_EMP_BIZ, "Tinta lote editor {$i}", $user->id, 1.0);
        }

        $this->actingAs($user)->getJson('/manufacturing/editor-receita/insumos?q=lote+editor')
            ->assertOk()->assertJsonCount(7, 'insumos');
    });
});
