<?php

declare(strict_types=1);

namespace Modules\AssetManagement\Http\Requests;

use App\Utils\ModuleUtil;
use Illuminate\Foundation\Http\FormRequest;

/**
 * D8.c Security Wave 10 — FormRequest novo pra Asset@update.
 *
 * Mesma logica de StoreAssetRequest, porem com permission asset.update + extra:
 *  - edit_warranty array (edicao de warranty existente, indexado por warranty_id)
 *  - asset_code NAO editavel (omitido em $request->only no Controller)
 */
class UpdateAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        if (! $user->can('asset.update')) {
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
            'name' => ['required', 'string', 'max:255'],
            'quantity' => ['nullable', 'string'],
            'model' => ['nullable', 'string', 'max:255'],
            'serial_no' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer'],
            'location_id' => ['nullable', 'integer', 'exists:business_locations,id'],
            'purchase_date' => ['nullable', 'string'],
            'unit_price' => ['nullable', 'string'],
            'depreciation' => ['nullable', 'string'],
            'is_allocatable' => ['nullable'],
            'description' => ['nullable', 'string'],
            'purchase_type' => ['nullable', 'string', 'in:owned,rented,leased'],
            // Warranty edit (existentes) — indexado por warranty_id.
            'edit_warranty' => ['nullable', 'array'],
            'edit_warranty.*.start_date' => ['nullable', 'string'],
            'edit_warranty.*.months' => ['nullable', 'integer'],
            'edit_warranty.*.additional_cost' => ['nullable', 'string'],
            'edit_warranty.*.additional_note' => ['nullable', 'string'],
            // Warranty new (criacao adicional)
            'start_dates' => ['nullable', 'array'],
            'months' => ['nullable', 'array'],
            'additional_cost' => ['nullable', 'array'],
            'additional_note' => ['nullable', 'array'],
        ];
    }
}
