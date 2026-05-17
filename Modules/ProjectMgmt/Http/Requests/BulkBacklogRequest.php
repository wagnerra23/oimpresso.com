<?php

declare(strict_types=1);

namespace Modules\ProjectMgmt\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * D8.c Security — Wave 18 RETRY (meta 97 module-grade).
 *
 * FormRequest pra POST /project-mgmt/backlog/bulk
 * (BacklogController@bulk). Operações em lote: reprioritize,
 * reassign, move-to-cycle, archive.
 *
 * Defense-in-depth: max 100 tasks por op (evita lock table prolongado).
 * Operações destrutivas (`archive`, `delete`) exigem `confirm: true`.
 *
 * @see Modules\ProjectMgmt\Http\Controllers\BacklogController::bulk
 */
class BulkBacklogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'op'        => ['required', 'string', 'in:reprioritize,reassign,move-to-cycle,archive,delete'],
            'task_ids'  => ['required', 'array', 'min:1', 'max:100'],
            'task_ids.*' => ['string', 'max:50'],
            'payload'   => ['sometimes', 'array'],
            'confirm'   => ['required_if:op,archive,delete', 'boolean', 'accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'op.required'       => 'Operação é obrigatória (reprioritize/reassign/move-to-cycle/archive/delete).',
            'op.in'             => 'Operação inválida.',
            'task_ids.required' => 'Lista de task_ids é obrigatória.',
            'task_ids.max'      => 'Máximo 100 tasks por operação em lote.',
            'confirm.required_if' => 'Operações destrutivas (archive/delete) exigem `confirm: true`.',
            'confirm.accepted'    => 'Deve confirmar operação destrutiva.',
        ];
    }
}
