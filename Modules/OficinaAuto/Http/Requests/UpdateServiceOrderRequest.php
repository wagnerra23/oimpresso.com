<?php

declare(strict_types=1);

namespace Modules\OficinaAuto\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
            'vehicle_id'          => ['required', 'integer', 'exists:vehicles,id'],
            'transaction_id'      => ['nullable', 'integer'],
            'mileage_at_service'  => ['nullable', 'integer', 'min:0'],
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
