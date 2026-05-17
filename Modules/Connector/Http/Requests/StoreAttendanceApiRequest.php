<?php

declare(strict_types=1);

namespace Modules\Connector\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest pra POST /api/attendance (clock in/out via API external — POS móvel).
 *
 * Wave 18 RETRY D8.e: substitui validação inline de `AttendanceController`.
 * Ponto eletrônico (Portaria MTP 671/2021): IMUTÁVEL em append-only,
 * `Marcacao::anular()` se ajuste. FormRequest valida apenas formato; lógica
 * de imutabilidade fica no Service.
 *
 * Tier 0 (ADR 0093): user_id resolvido via token Passport. business_id
 * derivado de user. NUNCA confiar no input.
 */
class StoreAttendanceApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'user_id'    => ['nullable', 'integer', 'min:1'],
            'clock_in_time'  => ['required_without:clock_out_time', 'nullable', 'date'],
            'clock_out_time' => ['required_without:clock_in_time', 'nullable', 'date', 'after_or_equal:clock_in_time'],
            'clock_in_note'  => ['nullable', 'string', 'max:500'],
            'clock_out_note' => ['nullable', 'string', 'max:500'],
            'ip_address' => ['nullable', 'ip'],
            'latitude'   => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'  => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }

    public function messages(): array
    {
        return [
            'clock_in_time.required_without'  => 'clock_in_time OU clock_out_time é obrigatório.',
            'clock_out_time.after_or_equal'   => 'clock_out_time deve ser >= clock_in_time.',
            'latitude.between'                => 'Latitude inválida (-90 a 90).',
            'longitude.between'               => 'Longitude inválida (-180 a 180).',
        ];
    }
}
