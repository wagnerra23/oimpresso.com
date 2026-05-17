<?php

declare(strict_types=1);

namespace Modules\ADS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * D8.c Security — Wave 27 saturação polish.
 *
 * FormRequest pra POST /ads/admin/skills/{slug} (SkillsController@store)
 * — endpoint que cria nova version de Skill com 4 rationale fields obrigatórios.
 *
 * Mantém compatibilidade direta com validate inline anterior do Controller
 * (frontmatter_yaml/body_markdown/4 rationale) — ZERO breaking change.
 *
 * @see Modules\ADS\Http\Controllers\Admin\SkillsController::store
 */
class StoreSkillVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'frontmatter_yaml'         => ['required', 'string', 'max:5000'],
            'body_markdown'            => ['required', 'string', 'max:200000'],
            'rationale_problem'        => ['required', 'string', 'min:10', 'max:2000'],
            'rationale_hypothesis'     => ['required', 'string', 'min:10', 'max:2000'],
            'rationale_success_metric' => ['required', 'string', 'min:10', 'max:2000'],
            'rationale_rollback'       => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'rationale_problem.required'        => 'Rationale problem é obrigatório (ADR 0061).',
            'rationale_hypothesis.required'     => 'Rationale hypothesis é obrigatório.',
            'rationale_success_metric.required' => 'Rationale success metric é obrigatório.',
            'rationale_rollback.required'       => 'Rationale rollback é obrigatório.',
        ];
    }
}
