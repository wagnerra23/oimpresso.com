<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Services;

use App\Variation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Manufacturing\Entities\MfgRecipe;
use Modules\Manufacturing\Entities\MfgRecipeIngredient;

/**
 * RecipeBomService — leitura especializada de BOM (Bill of Materials) Manufacturing.
 *
 * Encapsula resolução do BOM e cálculo de custo via chain Variation→Product,
 * extraindo lógica antes embutida em RecipeController + ManufacturingUtil.
 *
 * Service thin: zero side-effect, apenas queries READ + cálculo determinístico.
 * Toda query respeita isolamento multi-tenant Tier 0 ({@see ADR 0093}) via
 * `products.business_id` (JOIN chain — Manufacturing legacy não tem global scope).
 *
 * NUNCA aceita biz=cliente real em smoke ({@see ADR 0101}) — usar biz=1 (Wagner).
 *
 * @see Modules\Manufacturing\Http\Controllers\RecipeController
 * @see Modules\Manufacturing\Utils\ManufacturingUtil
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
class RecipeBomService
{
    /**
     * Resolve o BOM completo de uma recipe — ingredients ordenados + variation chain pre-carregada.
     *
     * Padrão de query usado em ProductionController e addIngredients(). Garante eager-load
     * mínimo necessário pra cálculo de custo (evita N+1).
     *
     * @param  int  $recipeId  ID da recipe (mfg_recipes.id)
     * @param  int  $businessId  Tenant — usado pra validar chain product.business_id
     * @return Collection<int, MfgRecipeIngredient>
     */
    public function resolveBom(int $recipeId, int $businessId): Collection
    {
        // Confirma que a recipe pertence ao business via JOIN chain (multi-tenant Tier 0)
        $pertence = MfgRecipe::join('variations as v', 'mfg_recipes.variation_id', '=', 'v.id')
            ->join('products as p', 'v.product_id', '=', 'p.id')
            ->where('mfg_recipes.id', $recipeId)
            ->where('p.business_id', $businessId)
            ->exists();

        if (! $pertence) {
            return collect();
        }

        return MfgRecipeIngredient::where('mfg_recipe_id', $recipeId)
            ->with([
                'variation',
                'variation.product',
                'variation.product.unit',
                'variation.product_variation',
                'sub_unit',
                'ingredient_group',
            ])
            ->orderBy('sort_order', 'asc')
            ->get();
    }

    /**
     * Calcula custo total dinâmico de uma recipe — soma dos ingredientes × quantidade × multiplier
     * de sub-unidade + production cost (per_unit / percentage / fixed).
     *
     * Mantém paridade com `ManufacturingUtil::getRecipeTotal($row)` legacy — não muda valores,
     * só extrai pra Service testável isolado (D4.a ratio Service/Controller).
     *
     * @param  MfgRecipe  $recipe  Recipe com `ingredients` + `ingredients.variation` + `ingredients.sub_unit` pre-carregados
     * @return float Custo total em moeda base do business
     */
    public function calculateCost(MfgRecipe $recipe): float
    {
        $price = 0.0;

        foreach ($recipe->ingredients as $ingredient) {
            if (empty($ingredient->variation)) {
                continue;
            }

            $ingredientTotal = (float) $ingredient->variation->dpp_inc_tax * (float) $ingredient->quantity;

            if (! empty($ingredient->sub_unit)) {
                $multiplier = ! empty($ingredient->sub_unit->base_unit_multiplier)
                    ? (float) $ingredient->sub_unit->base_unit_multiplier
                    : 1.0;
                $ingredientTotal *= $multiplier;
            }

            $price += $ingredientTotal;
        }

        $productionCost = (float) ($recipe->extra_cost ?? 0);

        if ($recipe->production_cost_type === 'percentage') {
            $productionCost = ($price * (float) $recipe->extra_cost) / 100;
        } elseif ($recipe->production_cost_type === 'per_unit') {
            $productionCost = (float) $recipe->extra_cost * (float) $recipe->total_quantity;
        }

        return $price + $productionCost;
    }

    /**
     * Resolve unit cost (custo por unidade base) — útil pra previsão de preço de venda e relatórios.
     *
     * @param  MfgRecipe  $recipe
     * @return float Custo unitário; zero se `total_quantity` <= 0 (proteção div-by-zero)
     */
    public function calculateUnitCost(MfgRecipe $recipe): float
    {
        $total = $this->calculateCost($recipe);

        if ((float) $recipe->total_quantity <= 0) {
            return 0.0;
        }

        return $total / (float) $recipe->total_quantity;
    }

    /**
     * Lista recipes do business em formato dropdown — wrapper sobre MfgRecipe::forDropdown()
     * com tipagem explícita pra DI em Controllers.
     *
     * @param  int  $businessId
     * @param  bool  $byVariationId  Se true, key = variation_id; senão key = recipe.id
     * @return Collection<string, string>
     */
    public function listForDropdown(int $businessId, bool $byVariationId = true): Collection
    {
        $recipes = MfgRecipe::forDropdown($businessId, $byVariationId);

        return collect($recipes->toArray());
    }
}
