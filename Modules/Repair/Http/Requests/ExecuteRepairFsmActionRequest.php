<?php

namespace Modules\Repair\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest pra executar ação FSM em JobSheet (Repair).
 *
 * D8.c Security — Wave S. Extraido de RepairFsmActionController@execute (L118).
 *
 * Autorização per-action (sale_stage_action_roles RBAC) fica DELEGADA pro
 * ExecuteStageActionService — aqui só checa auth básica.
 *
 * @see Modules/Repair/Http/Controllers/RepairFsmActionController.php
 * @see app/Domain/Fsm/ExecuteStageActionService.php
 * @see memory/decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md
 */
class ExecuteRepairFsmActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // RBAC per-action acontece no ExecuteStageActionService (sale_stage_action_roles).
        // Aqui só exige auth básica — replica check do controller original.
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'action_key' => ['required', 'string', 'max:80'],
            'payload'    => ['sometimes', 'array'],
        ];
    }
}
