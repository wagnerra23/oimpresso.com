<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Http\Requests;

use App\Utils\ModuleUtil;
use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest pra UPDATE MfgRecipe (Manufacturing).
 *
 * Wave 18 D8 SATURATION — extraido de RecipeController@update (validação inline).
 *
 * Permissão `manufacturing.edit_recipe` (UltimatePOS Spatie). Subscription
 * `manufacturing_module` obrigatória — ModuleUtil::hasThePermissionInSubscription.
 *
 * Rules pares com StoreRecipeRequest mas com `sometimes` em campos opcionais
 * de PATCH parcial. Multi-tenant Tier 0 ({@see ADR 0093}) garantido pelo
 * Controller via `MfgRecipe::find()` chain validation antes de aplicar mass-assign.
 */
class UpdateRecipeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        if ($user->can('superadmin')) {
            return true;
        }

        $businessId = $this->session()->get('user.business_id');
        $moduleUtil = app(ModuleUtil::class);

        if (! $moduleUtil->hasThePermissionInSubscription($businessId, 'manufacturing_module')) {
            return false;
        }

        return $user->can('manufacturing.edit_recipe');
    }

    public function rules(): array
    {
        return [
            'variation_id'                          => ['sometimes', 'integer', 'exists:variations,id'],
            'ingredients'                           => ['sometimes', 'array'],
            'ingredients.*.ingredient_id'           => ['required_with:ingredients', 'integer'],
            'ingredients.*.quantity'                => ['required_with:ingredients', 'string'],
            'ingredients.*.waste_percent'           => ['nullable', 'string'],
            'ingredients.*.sort_order'              => ['nullable', 'integer'],
            'ingredients.*.sub_unit_id'             => ['nullable', 'integer'],
            'ingredients.*.ig_index'                => ['nullable', 'integer'],
            'ingredients.*.mfg_ingredient_group_id' => ['nullable', 'integer'],
            'ingredients.*.ingredient_line_id'      => ['nullable', 'integer'],
            'total'                                 => ['nullable', 'string'],
            'total_quantity'                        => ['nullable', 'string'],
            'ingredients_cost'                      => ['nullable', 'string'],
            'waste_percent'                         => ['nullable', 'string'],
            'extra_cost'                            => ['nullable', 'string'],
            'production_cost_type'                  => ['nullable', 'string', 'in:fixed,percentage,per_unit'],
            'instructions'                          => ['nullable', 'string'],
            'sub_unit_id'                           => ['nullable', 'integer'],
            'ingredient_groups'                     => ['nullable', 'array'],
            'ingredient_group_description'          => ['nullable', 'array'],
            'final_price'                           => ['nullable', 'numeric'],
            'name'                                  => ['nullable', 'string', 'max:255'],
        ];
    }
}
