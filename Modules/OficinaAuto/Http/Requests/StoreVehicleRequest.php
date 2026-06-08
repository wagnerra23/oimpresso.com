<?php

declare(strict_types=1);

namespace Modules\OficinaAuto\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\OficinaAuto\Http\Controllers\VehicleController;

/**
 * D8 Security Wave 15 — FormRequest extraído de VehicleController::store.
 *
 * Substitui $request->validate inline pra elevar D8 Security (governance v3 rubrica).
 * Mantém regras originais (campo placa BR + RENAVAM 11 chars + ENUM vehicle_type).
 *
 * Multi-tenant Tier 0 (ADR 0093): business_id é setado pelo creating() hook do Model
 * — não vem do request (proteção contra mass-assignment cross-tenant).
 *
 * @see Modules\OficinaAuto\Http\Controllers\VehicleController::store
 * @see memory/requisitos/OficinaAuto/SPEC.md US-OFICINA-001
 */
class StoreVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        return $user->can('superadmin') || $user->can('oficinaauto.vehicle.create');
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
            'legacy_id'         => ['nullable', 'string', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'plate.required'        => 'A placa do veículo é obrigatória.',
            'vehicle_type.required' => 'Selecione o tipo do veículo.',
            'vehicle_type.in'       => 'Tipo de veículo inválido.',
            'renavam.max'           => 'RENAVAM aceita no máximo 11 caracteres (padrão DENATRAN).',
        ];
    }
}
