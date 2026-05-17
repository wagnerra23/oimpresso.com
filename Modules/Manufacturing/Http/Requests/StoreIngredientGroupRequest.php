<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Http\Requests;

use App\Utils\ModuleUtil;
use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest pra STORE de `MfgIngredientGroup` (Manufacturing).
 *
 * Wave 18 D8 SATURATION — primeiro FormRequest pra entity Group
 * (até Wave 17, apenas Recipe + Production tinham requests).
 *
 * **Permissão**: `manufacturing.access_recipe` + subscription
 * `manufacturing_module` (rota web admin: settings/ingredient-groups).
 *
 * **Tier 0 multi-tenant** ({@see ADR 0093}): `mfg_ingredient_groups` tem
 * `business_id` direto — global scope via `HasBusinessScope` na Model.
 * authorize() resolve $businessId via session (Tier 0 obrigatório).
 */
class StoreIngredientGroupRequest extends FormRequest
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

        return $user->can('manufacturing.access_recipe');
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nome do grupo de ingredientes é obrigatório.',
            'name.max'      => 'Nome do grupo limitado a 255 caracteres.',
        ];
    }
}
