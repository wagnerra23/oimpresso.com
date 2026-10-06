<?php

declare(strict_types=1);

namespace Modules\OficinaAuto\Http\Requests;

use App\Domain\Oficina\PlacaVeiculo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
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
            // Lembrete de revisão por km (decisão [W] 2026-10-06): km da próxima revisão, manual.
            'next_service_km'   => ['nullable', 'integer', 'min:0'],
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
            'next_service_km.min'   => 'O km da próxima revisão não pode ser negativo.',
            'renavam.max'           => 'RENAVAM aceita no máximo 11 caracteres (padrão DENATRAN).',
        ];
    }

    /**
     * Placa (principal ou reboque) já em outro veículo ativo da empresa é recusada (decisão [W]
     * 2026-10-05: "ativas não pode duplicar"). Mesma regra do app: App\Domain\Oficina\PlacaVeiculo.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $bizId = (int) ($this->user()?->business_id ?? 0);
            foreach (['plate', 'secondary_plate'] as $campo) {
                if ($v->errors()->has($campo) || ! filled($this->input($campo))) {
                    continue;
                }
                if (PlacaVeiculo::veiculoAtivoCom($bizId, (string) $this->input($campo)) !== null) {
                    $v->errors()->add($campo, PlacaVeiculo::MENSAGEM_DUPLICADA);
                }
            }
        });
    }
}
