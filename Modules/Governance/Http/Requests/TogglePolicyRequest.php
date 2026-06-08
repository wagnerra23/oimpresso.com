<?php

declare(strict_types=1);

namespace Modules\Governance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest pra POST /governance/policies/{id}/toggle.
 *
 * D8.c Security — Wave S Batch 2. Extraido de PoliciesController@toggle.
 *
 * Constituicao Art. 8 (governance policies — mcp_governance_rules):
 *   - Toggle de policy e operacao sensivel (afeta enforcement runtime)
 *   - Middleware stack ja garante: 'web' + 'authh' + 'auth' (admin gate)
 *   - PolicyToggleService persiste em mcp_governance_rules + audit history
 *
 * Aqui validamos APENAS o input HTTP: enabled boolean.
 * Authorization superadmin/admin fica DELEGADA pra middleware stack +
 * checks futuros no PolicyToggleService.
 *
 * @see Modules/Governance/Http/Controllers/PoliciesController.php
 * @see Modules/Governance/Services/PolicyToggleService.php
 */
class TogglePolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Middleware 'authh' + 'auth' ja gate admin no routes.php.
        // Aqui so garantimos user autenticado (defesa em profundidade).
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'enabled.required' => 'Estado enabled e obrigatorio (true|false).',
            'enabled.boolean'  => 'Estado enabled deve ser booleano.',
        ];
    }
}
