<?php

declare(strict_types=1);

namespace Modules\Essentials\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * D8 Security Wave 15 — FormRequest extraído de EssentialsHolidayController::store/update.
 *
 * Feriados são entidade admin-only (criados pelo gestor pra impactar folha de ponto
 * e leave balance). Validação preserva regras do método protected validateHoliday().
 */
class StoreHolidayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:255'],
            'start_date'  => ['required'],
            'end_date'    => ['required'],
            'location_id' => ['nullable', 'integer', 'exists:business_locations,id'],
            'note'        => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'        => 'O nome do feriado é obrigatório.',
            'start_date.required'  => 'A data inicial é obrigatória.',
            'end_date.required'    => 'A data final é obrigatória.',
            'location_id.exists'   => 'Local selecionado é inválido.',
        ];
    }
}
