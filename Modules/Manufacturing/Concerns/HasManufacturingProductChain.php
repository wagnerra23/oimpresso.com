<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Trait HasManufacturingProductChain — Wave 25 D1 polish Manufacturing (2026-05-16).
 *
 * Trait COMPANION ao {@see AssertsBusinessChain} — oferece helpers de lookup
 * agregado pra Services/Controllers que precisam saber quantos descendants
 * (recipes/ingredients) pertencem a um business via product chain, sem
 * duplicar o JOIN canônico em N pontos do código.
 *
 * Diferença vs AssertsBusinessChain:
 *  - `AssertsBusinessChain` → scope query builder (filtro) + check unitário
 *  - `HasManufacturingProductChain` → contagem agregada + count cross-table
 *
 * Pattern útil pra dashboards Manufacturing (ex: "quantas receitas biz=1 ativas")
 * sem precisar carregar a collection inteira em memória.
 *
 * **Multi-tenant Tier 0 IRREVOGÁVEL** ({@see ADR 0093}) — toda agregação cross-business
 * exige explicit-cast com comentário `// SUPERADMIN: <razão>`. Caller controla biz_id.
 *
 * **Lifecycle Wave 25:**
 * - 2026-05-16 — Wave 25 POLISH ≥90 D1 (18/30 → 23/30): trait companion criado
 *   pra consolidar pattern de agregação chain product/variation. Pest robusto
 *   valida via biz=1 vs biz=99 ({@see ADR 0101} — nunca biz=cliente real).
 *
 * @see Modules\Manufacturing\Concerns\AssertsBusinessChain (filtro)
 * @see Modules\Manufacturing\Tests\Feature\Wave25BomRecipesExpandedTest
 */
trait HasManufacturingProductChain
{
    /**
     * Conta registros pertencentes ao business via chain product/variation.
     *
     * Cobre tanto Models com `variation_id` direto (MfgRecipe) quanto Models
     * descendant via `mfg_recipe_id` (MfgRecipeIngredient).
     *
     * @param  int  $businessId  Tier 0 — caller injeta, NUNCA omitir
     * @return int  Quantidade matching (0 se nenhum)
     */
    public function countForBusinessChain(int $businessId): int
    {
        $table = $this->getTable();

        // Caso 1: Model tem variation_id direta (ex: mfg_recipes)
        if ($this->detectDirectVariationChain()) {
            return DB::table("{$table} as cht")
                ->join('variations as chv', "cht.variation_id", '=', 'chv.id')
                ->join('products as chp', 'chv.product_id', '=', 'chp.id')
                ->where('chp.business_id', $businessId)
                ->count();
        }

        // Caso 2: Model descendant via mfg_recipe_id (ex: mfg_recipe_ingredients)
        return DB::table("{$table} as cht")
            ->join('mfg_recipes as chr', 'cht.mfg_recipe_id', '=', 'chr.id')
            ->join('variations as chv', 'chr.variation_id', '=', 'chv.id')
            ->join('products as chp', 'chv.product_id', '=', 'chp.id')
            ->where('chp.business_id', $businessId)
            ->count();
    }

    /**
     * Retorna IDs dos registros pertencentes ao business via chain.
     *
     * Útil pra bulk operations downstream (delete, update, sync) sem precisar
     * carregar Models hidratadas — economiza memória em larguras altas.
     *
     * @param  int  $businessId  Tier 0
     * @param  int  $limit  Limite proteção contra runaway query (default 1000)
     * @return array<int>  Array de IDs (vazio se nenhum)
     */
    public function idsForBusinessChain(int $businessId, int $limit = 1000): array
    {
        $table = $this->getTable();

        if ($this->detectDirectVariationChain()) {
            return DB::table("{$table} as cht")
                ->join('variations as chv', "cht.variation_id", '=', 'chv.id')
                ->join('products as chp', 'chv.product_id', '=', 'chp.id')
                ->where('chp.business_id', $businessId)
                ->limit($limit)
                ->pluck('cht.id')
                ->all();
        }

        return DB::table("{$table} as cht")
            ->join('mfg_recipes as chr', 'cht.mfg_recipe_id', '=', 'chr.id')
            ->join('variations as chv', 'chr.variation_id', '=', 'chv.id')
            ->join('products as chp', 'chv.product_id', '=', 'chp.id')
            ->where('chp.business_id', $businessId)
            ->limit($limit)
            ->pluck('cht.id')
            ->all();
    }

    /**
     * Detecta se a Model tem coluna `variation_id` direta vs via parent recipe.
     *
     * Heurística: tabelas core Manufacturing com chain direto (mfg_recipes).
     * Override em Models específicas via property `$hasDirectChain` se necessário.
     */
    protected function detectDirectVariationChain(): bool
    {
        if (property_exists($this, 'hasDirectChain')) {
            return (bool) $this->hasDirectChain;
        }

        return in_array($this->getTable(), ['mfg_recipes'], true);
    }
}
