<?php

declare(strict_types=1);

namespace Modules\OficinaAuto\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * D8 Security Wave 15 — FormRequest extraído de ServiceOrderController::store.
 *
 * Substitui $request->validate inline pra elevar D8 Security (governance v3 rubrica).
 * Preserva validações originais (vehicle_id exists, status string livre V0).
 *
 * Multi-tenant Tier 0 (ADR 0093): business_id derivado da sessão pelo Model creating()
 * hook — request nunca pode injetar (proteção mass-assignment cross-tenant).
 *
 * FSM canônica chega em US-OFICINA-003 — quando entregar, status pode virar enum strict.
 *
 * @see Modules\OficinaAuto\Http\Controllers\ServiceOrderController::store
 * @see memory/requisitos/OficinaAuto/SPEC.md US-OFICINA-001
 */
class StoreServiceOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        return $user->can('superadmin') || $user->can('oficinaauto.service_order.create');
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
            'notes'               => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'vehicle_id.required' => 'Selecione o veículo da OS.',
            'vehicle_id.exists'   => 'Veículo informado não existe ou não pertence ao seu negócio.',
            'status.required'     => 'Informe o status inicial da OS.',
        ];
    }
}
