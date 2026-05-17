<?php

declare(strict_types=1);

namespace Modules\ADS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * D8.c Security — Wave 27 saturação polish.
 *
 * FormRequest pra POST /ads/admin/meta-skills (MetaSkillsController@store)
 * — endpoint que insere governance rule em `mcp_governance_rules`.
 *
 * Wave 18 já tinha `StoreMetaSkillRequest` (mission-based, scaffold). Esta
 * cobre o segundo endpoint store() que valida `rule_key`/`condition`/`action`
 * direto (uso UI editor governance — não scaffold mission-based).
 *
 * @see Modules\ADS\Http\Controllers\Admin\MetaSkillsController::store
 */
class StoreGovernanceMetaSkillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rule_key'    => ['required', 'string', 'max:80', 'regex:/^[a-z0-9_]+$/', 'unique:mcp_governance_rules,rule_key'],
            'name'        => ['required', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:2000'],
            'category'    => ['required', 'in:promotion,archival,escalation,retry,budget,review'],
            'condition'   => ['required', 'array'],
            'action'      => ['required', 'array'],
            'enabled'     => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'rule_key.regex'   => 'rule_key deve usar apenas [a-z0-9_].',
            'rule_key.unique'  => 'rule_key já existe — escolha outro.',
            'category.in'      => 'category inválida (promotion/archival/escalation/retry/budget/review).',
        ];
    }
}
