<?php

declare(strict_types=1);

namespace Modules\Brief\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * CompareBriefRequest — Wave 27 D8 SECURITY.
 *
 * FormRequest pro endpoint admin `GET /brief/admin/compare?a={id_a}&b={id_b}`
 * (side-by-side de 2 briefs pra Wagner debugar regressão Brain B —
 * "por que o brief de 14h ficou pior que o de 7h?").
 *
 * Cobre:
 *   - `a` e `b` mcp_briefs.id distintos e existentes (asserção downstream)
 *   - `diff_mode`: full|sections|headers — granularidade do diff
 *   - `redact_pii`: força redação antes do diff (PII pode aparecer em metadata)
 *
 * Multi-tenant Tier 0 ({@see ADR 0093}): Brief é repo-wide, permission
 * `brief.history.view` (Wagner-only via RBAC).
 *
 * Anti-padrão protegido: a == b é rejeitado (operador errou input).
 *
 * @see Modules\Brief\Http\Requests\FetchBriefHistoryRequest (sibling listing)
 * @see Modules\Brief\Http\Requests\ExportBriefMarkdownRequest (sibling export)
 * @see memory/decisions/0091-daily-brief.md §debug regressão
 */
class CompareBriefRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        return (bool) ($user->can('brief.history.view') || $user->can('superadmin'));
    }

    public function rules(): array
    {
        return [
            'a' => ['required', 'integer', 'min:1', 'different:b'],
            'b' => ['required', 'integer', 'min:1', 'different:a'],

            // Granularidade do diff:
            //   - full: diff char-by-char (default — pra debug regressão LLM)
            //   - sections: compara as 7 seções ADR 0091 separadamente
            //   - headers: apenas presença/ordem dos H2 (sentinela ---END---)
            'diff_mode'  => ['nullable', 'in:full,sections,headers'],

            'redact_pii' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'a.required'   => 'Brief A obrigatório (?a=ID).',
            'a.different'  => 'Brief A deve ser diferente de Brief B (comparação só faz sentido com IDs distintos).',
            'b.required'   => 'Brief B obrigatório (?b=ID).',
            'b.different'  => 'Brief B deve ser diferente de Brief A.',
            'diff_mode.in' => 'diff_mode aceita: full | sections | headers.',
            'redact_pii.boolean' => 'redact_pii deve ser booleano.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('redact_pii')) {
            $this->merge(['redact_pii' => filter_var($this->input('redact_pii'), FILTER_VALIDATE_BOOLEAN)]);
        }
    }

    /**
     * Defaults: diff_mode=full, redact_pii=true.
     *
     * @return array{a:int, b:int, diff_mode:string, redact_pii:bool}
     */
    public function optionsOrDefaults(): array
    {
        $v = $this->validated();

        return [
            'a'          => (int) $v['a'],
            'b'          => (int) $v['b'],
            'diff_mode'  => (string) ($v['diff_mode'] ?? 'full'),
            'redact_pii' => (bool) ($v['redact_pii'] ?? true),
        ];
    }
}
