<?php

declare(strict_types=1);

namespace Modules\OficinaAuto\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\OficinaAuto\Http\Controllers\VehicleController;

/**
 * D8 Security Wave 15 — FormRequest extraído de VehicleController::update.
 *
 * Update não permite trocar legacy_id (preserva trilha de origem da migração Firebird).
 * business_id NUNCA vem do request (multi-tenant Tier 0 — ADR 0093).
 *
 * @see Modules\OficinaAuto\Http\Controllers\VehicleController::update
 */
class UpdateVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        return $user->can('superadmin') || $user->can('oficinaauto.vehicle.update');
    }

    public function rules(): array
    {
        return [
            'plate'             => ['required', 'string', 'max:10'],
            'secondary_plate'   => ['nullable', 'string', 'max:10'],
            'chassis'           => ['nullable', 'string', 'max:30'],
            'secondary_chassis' => ['nullable', 'string', 'max:30'],
            'contact_id'        => ['nullable', 'integer'],
            'manufacture_year'  => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'model_year'        => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'renavam'           => ['nullable', 'string', 'max:11'],
            'vehicle_type'      => ['required', 'in:' . implode(',', array_keys(VehicleController::vehicleTypes()))],
            'engine'            => ['nullable', 'string', 'max:50'],
            'mileage_at_entry'  => ['nullable', 'integer', 'min:0'],
            'fuel_type'         => ['nullable', 'string', 'max:30'],
            'color'             => ['nullable', 'string', 'max:30'],
            'notes'             => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'plate.required'        => 'A placa do veículo é obrigatória.',
            'vehicle_type.required' => 'Selecione o tipo do veículo.',
            'vehicle_type.in'       => 'Tipo de veículo inválido.',
        ];
    }
}
