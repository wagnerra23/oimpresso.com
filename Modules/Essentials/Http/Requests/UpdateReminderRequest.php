<?php

declare(strict_types=1);

namespace Modules\Essentials\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * D8 Security Wave 15 — FormRequest extraído de ReminderController::update.
 * Mesmas regras de Store (campos imutáveis pro update). Mantemos arquivo separado
 * pra permitir divergência futura (ex: ID em route param, validações condicionais).
 */
class UpdateReminderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name'     => ['required', 'string', 'max:255'],
            'date'     => ['required'],
            'time'     => ['required', 'string', 'max:10'],
            'end_time' => ['nullable', 'string', 'max:10'],
            'repeat'   => ['required', Rule::in(['one_time', 'every_day', 'every_week', 'every_month'])],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'   => 'O campo nome do lembrete é obrigatório.',
            'date.required'   => 'A data do lembrete é obrigatória.',
            'time.required'   => 'O horário do lembrete é obrigatório.',
            'repeat.required' => 'Selecione a frequência de repetição.',
            'repeat.in'       => 'Frequência deve ser uma vez, todo dia, toda semana ou todo mês.',
        ];
    }
}
