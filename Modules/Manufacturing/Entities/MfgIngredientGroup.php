<?php

namespace Modules\Manufacturing\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class MfgIngredientGroup extends Model
{
    use HasBusinessScope; // ADR 0093 — multi-tenant Tier 0 IRREVOGÁVEL (Wave 13 D1 boost — coluna business_id direta na tabela mfg_ingredient_groups)
    use LogsActivity; // D7.b LGPD — audit trail grupo ingrediente (Wave S Batch 2)

    /**
     * Spatie ActivityLog — registra mudanças no grupo de ingredientes.
     *
     * Tenancy: `business_id` DIRETO (migration 2019_11_05_115136_create_ingredient_groups_table)
     * — scope global ScopeByBusiness aplicado via trait HasBusinessScope.
     *
     * Models irmãs (MfgRecipe / MfgRecipeIngredient) escapam por design:
     *   - mfg_recipes / mfg_recipe_ingredients NÃO têm coluna business_id direta
     *   - Isolamento via JOIN com products.business_id (chain variation→product)
     *   - Pattern usado em MfgRecipe::forDropdown() + RecipeController::index() +
     *     ProductionController. Aplicar HasBusinessScope nessas Models causaria
     *     SQL error ("Unknown column mfg_recipes.business_id") — `na_justified`.
     *
     * @see App\Concerns\HasBusinessScope
     * @see Modules\Manufacturing\Tests\Feature\MultiTenantIsolationTest
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'name',
                'description',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('manufacturing.ingredient_group');
    }

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];
}
