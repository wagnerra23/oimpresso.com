<?php

declare(strict_types=1);

namespace Modules\ProjectMgmt\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * D8.c Security — Wave 18 RETRY (meta 97 module-grade).
 *
 * FormRequest pra POST /project-mgmt/board/{taskId}/subtask
 * (BoardController@addSubtask — PMG-007 ADR 0100).
 *
 * Subtask herda business_id + project_id da parent task. Status default
 * 'todo'. Estimate em horas (1-240 ~ até 30 dias × 8h).
 *
 * @see Modules\ProjectMgmt\Http\Controllers\BoardController::addSubtask
 */
class AddSubtaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'    => ['required', 'string', 'min:1', 'max:200'],
            'owner'    => ['sometimes', 'string', 'max:50'],
            'estimate' => ['sometimes', 'integer', 'min:1', 'max:240'],
            'priority' => ['sometimes', 'string', 'in:P0,P1,P2,P3'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Título da subtask é obrigatório.',
            'title.max'      => 'Título deve ter no máximo 200 caracteres.',
            'owner.max'      => 'Owner deve ter no máximo 50 caracteres.',
            'estimate.min'   => 'Estimativa mínima 1 hora.',
            'estimate.max'   => 'Estimativa máxima 240 horas (30 dias × 8h).',
            'priority.in'    => 'Prioridade deve ser P0, P1, P2 ou P3.',
        ];
    }
}
