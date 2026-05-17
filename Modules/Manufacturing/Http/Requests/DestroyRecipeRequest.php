<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Http\Requests;

use App\Utils\ModuleUtil;
use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest pra DESTROY de `MfgRecipe` (Manufacturing).
 *
 * Wave 18 D8 SATURATION — fecha o ciclo CRUD (Store/Update/Destroy).
 *
 * **Permissão crítica** ({@see ADR 0093} Tier 0): recipe destroy cascateia
 * em `mfg_recipe_ingredients` + invalida histórico de produção. Exigir
 * `manufacturing.access_recipe` + subscription ativa.
 *
 * **Sem rules de payload** — DELETE não recebe body. authorize() é todo o gate.
 */
class DestroyRecipeRequest extends FormRequest
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
            // DELETE sem body — confirmation opcional (UX UI)
            'confirm' => ['nullable', 'boolean'],
        ];
    }
}
