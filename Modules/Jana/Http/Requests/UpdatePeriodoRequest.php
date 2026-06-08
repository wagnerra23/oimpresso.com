<?php

declare(strict_types=1);

namespace Modules\Jana\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * UpdatePeriodoRequest — atualização parcial de MetaPeriodo.
 *
 * D8.c (Wave 17 governance v3) — endurece `$request->only([...])` do
 * PeriodosController@update. Todos os campos sometimes (PATCH-friendly).
 *
 * Multi-tenant Tier 0 (ADR 0093): authorize confia no scope global aplicado
 * em Meta na rota pai.
 */
class UpdatePeriodoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'tipo_periodo' => ['sometimes', Rule::in(['mes', 'trim', 'ano', 'custom'])],
            'data_ini'     => ['sometimes', 'date'],
            'data_fim'     => ['sometimes', 'date', 'after_or_equal:data_ini'],
            'valor_alvo'   => ['sometimes', 'numeric', 'min:0'],
            'trajetoria'   => ['sometimes', 'nullable', Rule::in(['linear', 'sazonal', 'exponencial', 'manual'])],
        ];
    }

    public function messages(): array
    {
        return [
            'tipo_periodo.in'         => 'Tipo de período inválido.',
            'data_fim.after_or_equal' => 'A data final deve ser igual ou posterior à inicial.',
            'valor_alvo.min'          => 'O valor alvo deve ser positivo.',
            'trajetoria.in'           => 'Trajetória inválida.',
        ];
    }
}
