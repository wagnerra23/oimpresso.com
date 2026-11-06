<?php

declare(strict_types=1);

namespace Modules\OficinaAuto\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * D8 Security Wave 15 — FormRequest extraído de ServiceOrderController::update.
 *
 * Update permite completed_at + delivered_at (não permitidos no store — só aparecem
 * após fluxo de produção/entrega). business_id NUNCA vem do request (ADR 0093).
 *
 * @see Modules\OficinaAuto\Http\Controllers\ServiceOrderController::update
 */
class UpdateServiceOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        return $user->can('superadmin') || $user->can('oficinaauto.service_order.update');
    }

    public function rules(): array
    {
        return [
            // exists ESCOPADO por business (Tier 0 ADR 0093): o `exists:vehicles,id` cru
            // aceitava veículo de outra empresa, e a página pública de aprovação mostrava a placa.
            'vehicle_id'          => [
                'required',
                'integer',
                Rule::exists('vehicles', 'id')->where(
                    fn ($query) => $query->where(
                        'business_id',
                        (int) (session('user.business_id') ?? session('business.id') ?? 0)
                    )
                ),
            ],
            'transaction_id'      => ['nullable', 'integer'],
            'mileage_at_service'  => ['nullable', 'integer', 'min:0'],
            // Check-in de entrada (US-OFICINA-038/039) — delta protótipo Cowork Nova OS
            'fuel_level_at_entry' => ['nullable', 'integer', 'min:0', 'max:100'],
            'entry_damages'       => ['nullable', 'array'],
            'entry_damages.*'     => ['string', 'max:80'],
            'status'              => ['required', 'string', 'max:30'],
            'entered_at'          => ['nullable', 'date'],
            'expected_completion' => ['nullable', 'date'],
            'completed_at'        => ['nullable', 'date'],
            'delivered_at'        => ['nullable', 'date'],
            'notes'               => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'vehicle_id.required' => 'Selecione o veículo da OS.',
            'vehicle_id.exists'   => 'Veículo informado não existe ou não pertence ao seu negócio.',
            'status.required'     => 'Informe o status atual da OS.',
        ];
    }
}
