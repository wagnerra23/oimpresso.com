<?php

namespace Modules\Manufacturing\Http\Requests;

use App\Utils\ModuleUtil;
use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest pra criar/atualizar MfgRecipe (Manufacturing).
 *
 * Extraido de RecipeController@store (D8.c Security — Onda 3).
 * Rules cobrem chaves usadas em $request->only(...) + ingredient_groups vindos em paralelo.
 */
class StoreRecipeRequest extends FormRequest
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

        return $user->can('manufacturing.add_recipe');
    }

    public function rules(): array
    {
        return [
            'variation_id' => ['required', 'integer', 'exists:variations,id'],
            'ingredients' => ['nullable', 'array'],
            'ingredients.*.ingredient_id' => ['required_with:ingredients', 'integer'],
            'ingredients.*.quantity' => ['required_with:ingredients', 'string'],
            'ingredients.*.waste_percent' => ['nullable', 'string'],
            'ingredients.*.sort_order' => ['nullable', 'integer'],
            'ingredients.*.sub_unit_id' => ['nullable', 'integer'],
            'ingredients.*.ig_index' => ['nullable', 'integer'],
            'ingredients.*.mfg_ingredient_group_id' => ['nullable', 'integer'],
            'ingredients.*.ingredient_line_id' => ['nullable', 'integer'],
            'total' => ['nullable', 'string'],
            'total_quantity' => ['nullable', 'string'],
            'ingredients_cost' => ['nullable', 'string'],
            'waste_percent' => ['nullable', 'string'],
            'extra_cost' => ['nullable', 'string'],
            'production_cost_type' => ['nullable', 'string', 'in:fixed,percentage'],
            'instructions' => ['nullable', 'string'],
            'sub_unit_id' => ['nullable', 'integer'],
            'ingredient_groups' => ['nullable', 'array'],
            'ingredient_group_description' => ['nullable', 'array'],
        ];
    }
}
