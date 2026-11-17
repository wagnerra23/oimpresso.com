<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Manufacturing\Entities\MfgRecipe;
use Modules\Manufacturing\Entities\MfgRecipeIngredient;
use Spatie\Activitylog\Models\Activity;

/**
 * Leva a empresa para o registro da trilha da receita e dos ingredientes — UC-RECIPE-25.
 * Auditoria do Manufacturing, lote 2.7.
 *
 * `mfg_recipes` e `mfg_recipe_ingredients` não têm `business_id`: a empresa vem pela cadeia até o
 * produto (ADR 0093). O `ActivityCauserKindObserver` (núcleo, no `saving`) resolve a empresa pela coluna
 * ou pela Transaction-pai, não acha nenhuma das duas e deixa o registro como está. Sem empresa, o
 * registro some da /auditoria, que filtra por ela.
 *
 * Fica aqui, ligado no `creating` do Activity pelo provider do módulo, e não nos dois Models: eles estão
 * na tolerância `grandfathered` do `MultiTenantScopeArchitectureTest`, e alterá-los exige pôr escopo
 * automático de empresa neles no mesmo PR — mudança de comportamento de toda consulta de receita, fora
 * deste lote.
 */
final class EmpresaDaTrilhaDaReceita
{
    public static function aplicar(Activity $activity): void
    {
        if (! empty($activity->getAttribute('business_id'))) {
            return;
        }

        $tipo = ltrim((string) $activity->getAttribute('subject_type'), '\\');
        $id = (int) $activity->getAttribute('subject_id');
        if ($id <= 0 || ! in_array($tipo, [MfgRecipe::class, MfgRecipeIngredient::class], true)) {
            return;
        }

        static $temColuna = null;
        if ($temColuna === null) {
            try {
                $temColuna = Schema::hasColumn('activity_log', 'business_id');
            } catch (\Throwable $e) {
                $temColuna = false;
            }
        }
        if (! $temColuna) {
            return;
        }

        $consulta = DB::table('mfg_recipes')->join('products', 'products.id', '=', 'mfg_recipes.product_id');
        if ($tipo === MfgRecipeIngredient::class) {
            $consulta->join('mfg_recipe_ingredients', 'mfg_recipe_ingredients.mfg_recipe_id', '=', 'mfg_recipes.id')
                ->where('mfg_recipe_ingredients.id', $id);
        } else {
            $consulta->where('mfg_recipes.id', $id);
        }

        $empresa = $consulta->value('products.business_id');
        if ($empresa !== null && (int) $empresa > 0) {
            $activity->setAttribute('business_id', (int) $empresa);
        }
    }
}
