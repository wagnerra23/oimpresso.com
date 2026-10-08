<?php

namespace Modules\Manufacturing\Http\Requests;

use App\Utils\ModuleUtil;
use Illuminate\Contracts\Validation\Validator;
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
            // Handoff Fabricação §5 regra 1 + §9: receita sem ingrediente não salva. Antes o `store()`
            // pulava a gravação em silêncio e respondia "salvo com sucesso".
            'ingredients' => ['required', 'array', 'min:1'],
            'ingredients.*.ingredient_id' => ['required', 'integer'],
            // §9: quantidade <= 0 é recusada. Chega no formato da empresa ("0,044"), por isso o
            // `num_uf` — o mesmo que o `store()` usa para gravar.
            'ingredients.*.quantity' => ['required', 'string', function (string $attribute, $value, $fail) {
                // Não-texto já cai na regra `string`; aqui só se mede o número.
                if (is_string($value) && app(ModuleUtil::class)->num_uf($value) <= 0) {
                    $fail('A quantidade de cada ingrediente precisa ser maior que zero.');
                }
            }],
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
            'production_cost_type' => ['nullable', 'string', 'in:fixed,percentage,per_unit'],
            'instructions' => ['nullable', 'string'],
            'sub_unit_id' => ['nullable', 'integer'],
            'ingredient_groups' => ['nullable', 'array'],
            'ingredient_group_description' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'ingredients.required' => 'A receita precisa de pelo menos 1 ingrediente.',
            'ingredients.min' => 'A receita precisa de pelo menos 1 ingrediente.',
        ];
    }

    /**
     * A janela de ingredientes ainda é Blade, e o layout dela não mostra `$errors` — só o aviso de
     * `session('status')`. Sem isto a recusa voltava para o formulário sem dizer o porquê.
     */
    protected function failedValidation(Validator $validator): void
    {
        $this->session()->flash('status', ['success' => 0, 'msg' => $validator->errors()->first()]);

        parent::failedValidation($validator);
    }
}
