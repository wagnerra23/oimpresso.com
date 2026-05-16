<?php

namespace Modules\Manufacturing\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class MfgRecipe extends Model
{
    use LogsActivity; // D7.b LGPD — audit trail receita produção (Wave S Batch 2)

    /**
     * Spatie ActivityLog — registra mudanças nos atributos críticos da receita.
     *
     * business_id chain via JOIN products (não há coluna direta) — não mexer no scope.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'name',
                'recipe_yield',
                'final_price',
                'instructions',
                'total_quantity',
                'waste_percent',
                'ingredients_cost',
                'extra_cost',
                'production_cost_type',
                'sub_unit_id',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('manufacturing.recipe');
    }

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * Get the variations associated with the product.
     */
    public function variation()
    {
        return $this->belongsTo(\App\Variation::class, 'variation_id');
    }

    /**
     * Get all the ingredients for the recipe.
     */
    public function ingredients()
    {
        return $this->hasMany(\Modules\Manufacturing\Entities\MfgRecipeIngredient::class, 'mfg_recipe_id');
    }

    public static function forDropdown($business_id, $variation_id = true)
    {
        $recipes = MfgRecipe::join('variations as v', 'mfg_recipes.variation_id', '=', 'v.id')
                        ->join('products as p', 'v.product_id', '=', 'p.id')
                        ->join('product_variations as pv', 'v.product_variation_id', '=', 'pv.id')
                        ->where('p.business_id', $business_id)
                        ->select(
                            DB::raw('IF(
                                        p.type="variable", 
                                        CONCAT(p.name, " - ", pv.name, " - ", v.name, " (", v.sub_sku, ")"), 
                                        CONCAT(p.name, " (", v.sub_sku, ")") 
                                        ) as recipe_name'),
                            'mfg_recipes.variation_id',
                            'mfg_recipes.id'
                        )->get();
        if ($variation_id) {
            return $recipes->pluck('recipe_name', 'variation_id');
        } else {
            return $recipes->pluck('recipe_name', 'id');
        }
    }

    /**
     * Get the unit associated with the recipe.
     */
    public function sub_unit()
    {
        return $this->belongsTo(\App\Unit::class, 'sub_unit_id');
    }
}
