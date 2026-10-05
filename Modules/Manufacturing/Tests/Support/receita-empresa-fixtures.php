<?php

declare(strict_types=1);

/**
 * Dados de teste das receitas por empresa — Tier 0 (ADR 0093).
 *
 * Compartilhado por `CopiarReceitaEmpresaTest` e `SalvarReceitaEmpresaTest`: no Pest as funções
 * de um arquivo de teste são GLOBAIS, então declarar o mesmo nome em dois arquivos derruba a suíte
 * inteira. Mesmo padrão de `Modules/Forja/Tests/Support/credential-vectors.php`.
 *
 * Tenant 98 (fictício, ADR 0358) e 99 como a "outra empresa". NUNCA biz=4. Quem usa roda dentro de
 * `DatabaseTransactions`: tudo o que estas funções gravam é desfeito no fim de cada caso.
 */

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;
use Modules\Manufacturing\Entities\MfgRecipe;
use Modules\Manufacturing\Entities\MfgRecipeIngredient;
use Spatie\Permission\Models\Permission;

defined('MFG_EMP_BIZ') || define('MFG_EMP_BIZ', 98);
defined('MFG_EMP_OUTRO') || define('MFG_EMP_OUTRO', 99);

if (! function_exists('mfgEmpNegocio')) {
    /** `id` é guarded no Business: id e dono vão explícitos (mesmo idioma do CrmLeadShowEscopoTest). */
    function mfgEmpNegocio(int $biz): void
    {
        if (Business::whereKey($biz)->exists()) {
            return;
        }
        $dono = (int) DB::table('users')->min('id');
        (new Business)->forceFill(['id' => $biz, 'name' => 'Tenant fictício mfg '.$biz, 'currency_id' => 1, 'owner_id' => $dono])->save();
    }
}

if (! function_exists('mfgEmpUsuario')) {
    /**
     * Usuário da empresa 98 com `manufacturing.add_recipe`. A rota exige `superadmin` OU o pacote
     * `manufacturing_module` na assinatura; a lista de administradores dá o `superadmin` sem
     * depender do pacote do ambiente de teste.
     */
    function mfgEmpUsuario(string $username): User
    {
        mfgEmpNegocio(MFG_EMP_BIZ);
        $user = User::firstOrCreate(['username' => $username], [
            'email' => $username.'@test.local', 'password' => bcrypt('secret'),
            'business_id' => MFG_EMP_BIZ, 'first_name' => 'Receita', 'last_name' => 'Empresa',
            'user_type' => 'user', 'allow_login' => 1,
        ]);
        $user->givePermissionTo(Permission::firstOrCreate(['name' => 'manufacturing.add_recipe', 'guard_name' => 'web']));
        config(['constants.administrator_usernames' => $user->username]);

        return $user;
    }
}

if (! function_exists('mfgEmpProduto')) {
    /** Unidade + produto + variação na empresa. Devolve [product_id, variation_id, unit_id]. */
    function mfgEmpProduto(int $biz, string $nome, int $userId, float $custo = 0.0): array
    {
        mfgEmpNegocio($biz);
        $unit = DB::table('units')->insertGetId([
            'business_id' => $biz, 'actual_name' => 'Unidade receita', 'short_name' => 'UR',
            'allow_decimal' => 1, 'created_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $sufixo = random_int(100000, 999999);
        $product = DB::table('products')->insertGetId([
            'business_id' => $biz, 'name' => $nome, 'type' => 'single', 'unit_id' => $unit,
            'tax_type' => 'exclusive', 'sku' => 'MFGEMP-'.$sufixo, 'barcode_type' => 'C128',
            'enable_stock' => 0, 'alert_quantity' => 0, 'created_by' => $userId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $pv = DB::table('product_variations')->insertGetId([
            'product_id' => $product, 'name' => 'DUMMY', 'is_dummy' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $variation = DB::table('variations')->insertGetId([
            'name' => 'DUMMY', 'product_id' => $product, 'product_variation_id' => $pv,
            'sub_sku' => 'MFGEMP-'.$sufixo, 'default_purchase_price' => $custo, 'dpp_inc_tax' => $custo,
            'profit_percent' => 0, 'default_sell_price' => 0, 'sell_price_inc_tax' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$product, $variation, $unit];
    }
}

if (! function_exists('mfgEmpReceita')) {
    /**
     * Receita na empresa com UM ingrediente chamado `$ingrediente`.
     * Devolve [recipe_id, variation_id do produto acabado, ingredient_line_id].
     */
    function mfgEmpReceita(int $biz, int $userId, string $ingrediente): array
    {
        // O nome do produto acabado NÃO repete o do insumo: há asserts que procuram o insumo.
        [$product, $variation] = mfgEmpProduto($biz, 'Produto acabado da receita', $userId);
        [, $insumo] = mfgEmpProduto($biz, $ingrediente, $userId, 7.5);

        $recipe = MfgRecipe::create([
            'product_id' => $product, 'variation_id' => $variation, 'instructions' => 'mfg-receita-empresa-test',
            'waste_percent' => 0, 'ingredients_cost' => 0, 'extra_cost' => 0,
            'total_quantity' => 1, 'final_price' => 0,
        ]);
        $linha = MfgRecipeIngredient::create([
            'mfg_recipe_id' => $recipe->id, 'variation_id' => $insumo, 'quantity' => 2,
        ]);

        return [$recipe->id, $variation, $linha->id];
    }
}
