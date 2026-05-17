<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Http\Requests;

use App\Utils\ModuleUtil;
use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest pra UPDATE Production (Manufacturing).
 *
 * Wave 18 D8 SATURATION — extraido de ProductionController@update (validação inline).
 *
 * Permissão `manufacturing.access_production` + subscription `manufacturing_module`.
 * Rules pares com StoreProductionRequest mas com `sometimes` em PATCH parcial.
 */
class UpdateProductionRequest extends FormRequest
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

        return $user->can('manufacturing.access_production');
    }

    public function rules(): array
    {
        return [
            'transaction_date'         => ['sometimes', 'required'],
            'location_id'              => ['sometimes', 'required'],
            'final_total'              => ['sometimes', 'required'],
            'ref_no'                   => ['nullable', 'string', 'max:255'],
            'finalize'                 => ['nullable'],
            'variation_id'             => ['sometimes', 'integer', 'exists:variations,id'],
            'quantity'                 => ['sometimes', 'string'],
            'mfg_wasted_units'         => ['nullable', 'string'],
            'production_cost'          => ['nullable', 'string'],
            'mfg_production_cost_type' => ['nullable', 'string', 'in:fixed,percentage,per_unit'],
            'lot_number'               => ['nullable', 'string', 'max:255'],
            'exp_date'                 => ['nullable', 'string'],
            'sub_unit_id'              => ['nullable', 'integer'],
        ];
    }
}
