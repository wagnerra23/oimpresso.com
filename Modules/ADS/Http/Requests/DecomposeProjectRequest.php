<?php

declare(strict_types=1);

namespace Modules\ADS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * D8.c Security — Wave 18 RETRY (meta 97 module-grade).
 *
 * FormRequest pra POST /ads/admin/projects/{id}/decompose
 * (ProjectMgmt\Admin\ProjectsController@decompose). Dispara
 * ProjectDecomposerService que quebra um Project em N Parts + N Tasks
 * usando LLM (Brain B) — custo ~R$ 0,15 por chamada.
 *
 * Defense-in-depth crítico: limita `max_tasks` (default 20) e exige
 * `confirm: true` pra disparar — evita uso acidental.
 *
 * @see Modules\ADS\Services\ProjectDecomposerService
 * @see Modules\ProjectMgmt\Http\Controllers\Admin\ProjectsController::decompose
 */
class DecomposeProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'confirm'   => ['required', 'boolean', 'accepted'],
            'max_tasks' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'strategy'  => ['sometimes', 'string', 'in:default,deep,shallow'],
            'note'      => ['sometimes', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'confirm.required' => 'Confirmação é obrigatória (custo LLM ~R$ 0,15).',
            'confirm.accepted' => 'Deve aceitar custo LLM (passar `confirm: true`).',
            'max_tasks.min'    => 'Mínimo 1 task gerada.',
            'max_tasks.max'    => 'Máximo 50 tasks geradas (evita explosão LLM).',
            'strategy.in'      => 'Estratégia deve ser default, deep ou shallow.',
        ];
    }
}
