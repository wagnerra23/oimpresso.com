<?php

declare(strict_types=1);

namespace Modules\ProjectMgmt\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * D8.c Security — Wave 18 saturação (meta 97 module-grade).
 *
 * FormRequest pra PATCH /admin/projectmgmt/tasks/{taskId}
 * (genérico — futuro endpoint de update). Wagner edita título/descrição/owner/
 * estimate de task ad-hoc via Backlog ou Inbox.
 *
 * Multi-tenant Tier 0 (ADR 0093): `business_id` resolvido da session upstream.
 *
 * @see Modules\ProjectMgmt\Http\Controllers\BacklogController
 * @see Modules\Jana\Entities\Mcp\McpTask
 */
class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'      => ['sometimes', 'string', 'max:200'],
            'description' => ['sometimes', 'string', 'max:10000'],
            'owner'      => ['sometimes', 'string', 'max:50'],
            'priority'   => ['sometimes', 'string', 'in:P0,P1,P2,P3'],
            'estimate'   => ['sometimes', 'integer', 'min:1', 'max:240'],
            'cycle_id'   => ['sometimes', 'integer', 'min:1'],
            'epic_id'    => ['sometimes', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.max'        => 'Título deve ter no máximo 200 caracteres.',
            'priority.in'      => 'Prioridade deve ser P0, P1, P2 ou P3.',
            'estimate.min'     => 'Estimate mínimo: 1 hora.',
            'estimate.max'     => 'Estimate máximo: 240 horas (~30 dias).',
        ];
    }
}
