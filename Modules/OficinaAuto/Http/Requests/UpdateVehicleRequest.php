<?php

declare(strict_types=1);

namespace Modules\OficinaAuto\Http\Requests;

use App\Domain\Oficina\PlacaVeiculo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Modules\OficinaAuto\Entities\Vehicle;
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
            // Lembrete de revisão por km (decisão [W] 2026-10-06): km da próxima revisão, manual.
            'next_service_km'   => ['nullable', 'integer', 'min:0'],
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
            'next_service_km.min'   => 'O km da próxima revisão não pode ser negativo.',
        ];
    }

    /**
     * Trocar a placa (principal ou reboque) por uma que já está em OUTRO veículo ativo da empresa é
     * recusado (decisão [W] 2026-10-05: "ativas não pode duplicar"). Só quando a placa MUDA: os
     * veículos que já nasceram duplicados antes da regra continuam editáveis (km, dono, cor...)
     * até alguém corrigir a placa. Mesma regra do app: App\Domain\Oficina\PlacaVeiculo.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $veiculo = $this->route('vehicle');
            if (! $veiculo instanceof Vehicle) {
                return;
            }
            $bizId = (int) $veiculo->business_id;
            foreach (['plate', 'secondary_plate'] as $campo) {
                $nova = PlacaVeiculo::normalizar($this->input($campo));
                if ($v->errors()->has($campo) || $nova === '' || $nova === PlacaVeiculo::normalizar($veiculo->{$campo})) {
                    continue;
                }
                if (PlacaVeiculo::veiculoAtivoCom($bizId, $nova, (int) $veiculo->id) !== null) {
                    $v->errors()->add($campo, PlacaVeiculo::MENSAGEM_DUPLICADA);
                }
            }
        });
    }
}
