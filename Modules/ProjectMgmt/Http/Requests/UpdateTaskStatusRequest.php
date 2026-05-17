<?php

declare(strict_types=1);

namespace Modules\ProjectMgmt\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * D8.c Security — Wave 18 saturação (meta 97 module-grade).
 *
 * FormRequest pra PATCH /api/projectmgmt/board/tasks/{taskId}/status
 * (BoardController@updateStatus). Wagner arrasta task no Kanban Board → muda
 * status (todo/doing/blocked/done).
 *
 * Multi-tenant Tier 0 (ADR 0093): `business_id` resolvido da session upstream.
 * Task lookup via McpTask::where('id', $taskId)->where('business_id', $bizId).
 * Diferente de FSM (ADR 0143 — ProjectMgmt usa Kanban free-flow, fsm_n_a=true).
 *
 * @see Modules\ProjectMgmt\Http\Controllers\BoardController::updateStatus
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
class UpdateTaskStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:todo,doing,blocked,done'],
            'note'   => ['sometimes', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Campo status é obrigatório.',
            'status.in'       => 'Status inválido. Valores aceitos: todo, doing, blocked, done (Kanban ProjectMgmt — ADR 0070).',
            'note.max'        => 'Observação deve ter no máximo 1000 caracteres.',
        ];
    }
}
