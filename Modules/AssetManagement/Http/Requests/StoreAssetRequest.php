<?php

declare(strict_types=1);

namespace Modules\AssetManagement\Http\Requests;

use App\Utils\ModuleUtil;
use Illuminate\Foundation\Http\FormRequest;

/**
 * D8.c Security Wave 10 — FormRequest novo pra Asset@store.
 *
 * AssetController legacy UltimatePOS nao tinha $request->validate inline — usava
 * $request->only(...) sem validation. Esta classe introduz validation defensiva
 * + authorize() que confere stack UltimatePOS (superadmin OU permissao no subscription
 * + permission asset.create).
 *
 * Pegadinhas mantidas pra back-compat:
 *  - quantity/unit_price/depreciation aceitam string (Util::num_uf converte BR locale)
 *  - purchase_date aceita string (Util::uf_date normaliza)
 *  - is_allocatable nullable (boolean cast no Controller)
 */
class StoreAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        if (! $user->can('asset.create')) {
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
            // asset_code pode vir vazio — Controller gera via setAndGetReferenceCount.
            'asset_code' => ['nullable', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            // quantity vem string (BR locale "1.234,56") — num_uf converte depois.
            'quantity' => ['nullable', 'string'],
            'model' => ['nullable', 'string', 'max:255'],
            'serial_no' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer'],
            'location_id' => ['nullable', 'integer', 'exists:business_locations,id'],
            // purchase_date vem string BR (dd/mm/yyyy) — uf_date converte.
            'purchase_date' => ['nullable', 'string'],
            'unit_price' => ['nullable', 'string'],
            'depreciation' => ['nullable', 'string'],
            'is_allocatable' => ['nullable'],
            'description' => ['nullable', 'string'],
            'purchase_type' => ['nullable', 'string', 'in:owned,rented,leased'],
            // Warranties (arrays paralelos — Controller itera por key).
            'start_dates' => ['nullable', 'array'],
            'months' => ['nullable', 'array'],
            'additional_cost' => ['nullable', 'array'],
            'additional_note' => ['nullable', 'array'],
        ];
    }
}
