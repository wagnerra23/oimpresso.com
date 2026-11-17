<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Http\Requests;

use App\Utils\ModuleUtil;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Manufacturing\Http\Requests\Concerns\ValidaNumerosDaOrdem;

/**
 * FormRequest pra UPDATE Production (Manufacturing).
 *
 * Wave 18 D8 SATURATION — extraido de ProductionController@update (validação inline).
 *
 * Permissão `manufacturing.access_production` + subscription `manufacturing_module`.
 * Ligada ao `ProductionController::update` no lote 2.2 da auditoria (até ali nenhum controller a
 * usava e o update validava só data, local e total, inline). Data, local e total seguem obrigatórios
 * como na validação inline que ela substitui (UC-OP-15); os números vêm de `ValidaNumerosDaOrdem`
 * (UC-OP-21), os mesmos do `StoreProductionRequest`.
 */
class UpdateProductionRequest extends FormRequest
{
    use ValidaNumerosDaOrdem;

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
        return $this->regrasDosNumerosDaOrdem() + [
            'transaction_date'         => ['required'],
            'location_id'              => ['required'],
            'final_total'              => ['required'],
            'ref_no'                   => ['nullable', 'string', 'max:255'],
            'finalize'                 => ['nullable'],
            'variation_id'             => ['sometimes', 'integer', 'exists:variations,id'],
            'lot_number'               => ['nullable', 'string', 'max:255'],
            'exp_date'                 => ['nullable', 'string'],
            'sub_unit_id'              => ['nullable', 'integer'],
        ];
    }
}
