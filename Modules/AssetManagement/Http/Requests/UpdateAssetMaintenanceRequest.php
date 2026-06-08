<?php

declare(strict_types=1);

namespace Modules\AssetManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * D2 Code Quality — Wave 18 saturação AssetManagement.
 *
 * Update de maintenance log de asset. Valida que o maintenance pertence
 * ao asset informado E que ambos pertencem ao business_id da sessao.
 *
 * @see Modules\AssetManagement\Http\Controllers\AssetMaintenanceController@update
 */
class UpdateAssetMaintenanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'asset_id'        => ['required', 'integer', 'min:1'],
            'maintenance_for' => ['nullable', 'string', 'max:255'],
            'description'     => ['nullable', 'string', 'max:5000'],
            'cost'            => ['nullable', 'numeric', 'min:0'],
            'start_date'      => ['nullable', 'date'],
            'end_date'        => ['nullable', 'date', 'after_or_equal:start_date'],
            'status'          => ['nullable', 'in:scheduled,in_progress,completed,cancelled'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $sessionBiz     = session('user.business_id') ?? session('business.id');
            $maintenanceId  = (int) $this->route('id') ?: (int) $this->route('asset_maintenance');
            $assetId        = (int) $this->input('asset_id');

            if ($sessionBiz === null || $maintenanceId <= 0) {
                return;
            }

            // Maintenance + asset ambos pertencem ao business da sessao
            $belongs = \DB::table('am_maintenance_logs as m')
                ->join('am_assets as a', 'a.id', '=', 'm.asset_id')
                ->where('m.id', $maintenanceId)
                ->where('a.business_id', $sessionBiz)
                ->when($assetId > 0, fn ($q) => $q->where('m.asset_id', $assetId))
                ->exists();

            if (! $belongs) {
                $v->errors()->add('id', 'Maintenance ou asset nao pertence ao business da sessao.');
            }
        });
    }
}
