<?php

declare(strict_types=1);

namespace Modules\ProjectMgmt\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * D8 Security — Wave 17 saturação.
 *
 * FormRequest pra criar McpTask (futura rota POST /project-mgmt/tasks).
 * Validação canônica alinhada com `Modules\Jana\Entities\Mcp\McpTask::STATUSES`
 * + `::PRIORITIES` e schema `mcp_tasks` (campos visíveis em
 * `BacklogController::serializeTask`).
 *
 * Multi-tenant Tier 0 (ADR 0093): task vincula a project_id que pertence a
 * business via mcp_projects.business_id; Service deve validar consistência
 * cross-tenant (Wave futura D2 — defesa em profundidade).
 *
 * Enums alinhados a McpTask:
 *   - STATUSES: backlog, todo, doing, review, blocked, done, cancelled
 *   - PRIORITIES: p0, p1, p2, p3
 *
 * @see Modules\Jana\Entities\Mcp\McpTask
 * @see Modules\ProjectMgmt\Http\Controllers\BacklogController::serializeTask
 */
class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Permission `copiloto.mcp.usage.all` upstream
    }

    public function rules(): array
    {
        return [
            'project_id'   => ['required', 'integer', 'exists:mcp_projects,id'],
            'title'        => ['required', 'string', 'max:255'],
            'module'       => ['sometimes', 'string', 'max:50'],
            'owner'        => ['sometimes', 'nullable', 'string', 'max:50'],
            'sprint'       => ['sometimes', 'nullable', 'string', 'max:50'],
            'priority'     => ['sometimes', Rule::in(['p0', 'p1', 'p2', 'p3'])],
            'status'       => ['sometimes', Rule::in(['backlog', 'todo', 'doing', 'review', 'blocked', 'done', 'cancelled'])],
            'type'         => ['sometimes', Rule::in(['story', 'bug', 'chore', 'spike', 'epic'])],
            'estimate_h'   => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:999.99'],
            'story_points' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:99.99'],
            'due_date'     => ['sometimes', 'nullable', 'date'],
            'epic_id'      => ['sometimes', 'nullable', 'integer', 'exists:mcp_epics,id'],
            'cycle_id'     => ['sometimes', 'nullable', 'integer'],
            'blocked_by'   => ['sometimes', 'array'],
            'blocked_by.*' => ['string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'project_id.required' => 'Campo project_id é obrigatório.',
            'project_id.exists'   => 'project_id informado não existe em mcp_projects.',
            'title.required'      => 'Title é obrigatório.',
            'priority.in'         => 'Priority deve ser p0, p1, p2 ou p3.',
            'status.in'           => 'Status inválido (allowed: backlog/todo/doing/review/blocked/done/cancelled).',
            'epic_id.exists'      => 'Epic informado não existe em mcp_epics.',
        ];
    }
}
