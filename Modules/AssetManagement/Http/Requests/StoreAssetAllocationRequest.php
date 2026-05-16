<?php

declare(strict_types=1);

namespace Modules\AssetManagement\Http\Requests;

use App\Utils\ModuleUtil;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Wave 14 D8 Security — FormRequest novo pra AssetAllocation@store.
 *
 * Complementa Wave 10 (StoreAssetRequest + UpdateAssetRequest). AssetAllocation
 * legacy nao tinha $request->validate inline — $request->only(...) sem checagem.
 * Esta classe introduz validation defensiva + authorize() seguindo padrao Wave 10.
 *
 * Pegadinhas mantidas pra back-compat:
 *  - quantity aceita string (Util::num_uf converte BR locale "1.234,56")
 *  - transaction_datetime / allocated_upto aceitam string (Util::uf_date normaliza)
 *  - ref_no nullable (Controller gera via setAndGetReferenceCount se vazio)
 */
class StoreAssetAllocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        $businessId = $this->session()->get('user.business_id');

        if (empty($businessId)) {
            return false;
        }

        if ($user->can('superadmin')) {
            return true;
        }

        $moduleUtil = app(ModuleUtil::class);

        return $moduleUtil->hasThePermissionInSubscription($businessId, 'assetmanagement_module');
    }

    public function rules(): array
    {
        return [
            'ref_no' => ['nullable', 'string', 'max:255'],
            'asset_id' => ['required', 'integer', 'exists:assets,id'],
            // quantity em BR locale — num_uf converte string -> float depois.
            'quantity' => ['nullable', 'string'],
            'receiver' => ['nullable', 'integer'],
            // datetime BR (dd/mm/yyyy HH:ii) — uf_date converte.
            'transaction_datetime' => ['nullable', 'string'],
            'allocated_upto' => ['nullable', 'string'],
            'reason' => ['nullable', 'string', 'max:1024'],
        ];
    }
}
