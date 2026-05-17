<?php

namespace Modules\Manufacturing\Entities;

use Illuminate\Database\Eloquent\Model;
use Modules\Manufacturing\Concerns\AssertsBusinessChain;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class MfgRecipeIngredient extends Model
{
    use AssertsBusinessChain; // Wave 18 D1 — multi-tenant chain via parent recipe — ADR 0093
    use LogsActivity; // D7.b LGPD — audit trail ingrediente receita (Wave S Batch 2)

    /**
     * Spatie ActivityLog — registra mudanças nos ingredientes da receita.
     *
     * business_id chain via JOIN products->mfg_recipe — não mexer no scope.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'quantity',
                'waste_percent',
                'variation_id',
                'sub_unit_id',
                'mfg_ingredient_group_id',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('manufacturing.recipe_ingredient');
    }

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * Get the variations associated with the ingredient.
     */
    public function variation()
    {
        return $this->belongsTo(\App\Variation::class, 'variation_id');
    }

    /**
     * Get the unit associated with the ingredient.
     */
    public function sub_unit()
    {
        return $this->belongsTo(\App\Unit::class, 'sub_unit_id');
    }

    /**
     * Get the ingredient group associated with the ingredient.
     */
    public function ingredient_group()
    {
        return $this->belongsTo(\Modules\Manufacturing\Entities\MfgIngredientGroup::class, 'mfg_ingredient_group_id');
    }
}
