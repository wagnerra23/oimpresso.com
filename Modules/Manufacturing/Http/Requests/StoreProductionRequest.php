<?php

namespace Modules\Manufacturing\Http\Requests;

use App\Utils\ModuleUtil;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Manufacturing\Http\Requests\Concerns\ValidaNumerosDaOrdem;

/**
 * FormRequest pra criar Production (Manufacturing).
 *
 * D8.c Security — Wave S. Extraido de ProductionController@store (L186).
 *
 * Rules originais ($request->validate inline) preservadas + campos adicionais
 * vindos em $request->only/input no método.
 */
class StoreProductionRequest extends FormRequest
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
        // Quantidade, perda, custo extra, forma do custo e consumo por insumo: UC-OP-21 (lote 2.2).
        return $this->regrasDosNumerosDaOrdem() + [
            // Originais do $request->validate() (L186-190)
            'transaction_date'         => ['required'],
            'location_id'              => ['required'],
            'final_total'              => ['required'],

            // Adicionais ($request->only/input)
            'ref_no'                   => ['nullable', 'string', 'max:255'],
            'finalize'                 => ['nullable'],
            'variation_id'             => ['required', 'integer', 'exists:variations,id'],
            'lot_number'               => ['nullable', 'string', 'max:255'],
            'exp_date'                 => ['nullable', 'string'],
            'sub_unit_id'              => ['nullable', 'integer'],
        ];
    }
}
