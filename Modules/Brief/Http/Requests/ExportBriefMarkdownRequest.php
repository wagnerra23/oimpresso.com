<?php

declare(strict_types=1);

namespace Modules\Brief\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * ExportBriefMarkdownRequest — Wave 27 D8 SECURITY.
 *
 * FormRequest pro endpoint admin `GET /brief/admin/{id}/export.md` (download
 * de 1 brief como markdown puro pra Wagner colar em relatório/handoff).
 *
 * Filtros opcionais:
 *   - `redact_pii` (default true) — força PiiRedactor mesmo se brief já passou
 *      por BriefValidator (defesa em profundidade pra export).
 *   - `include_metadata` (default true) — header YAML com generated_at, tokens,
 *      custo USD/BRL. Útil pra auditor; nem sempre desejado em handoff.
 *   - `wrap_columns` — wrap manual em N cols (default 0 = sem wrap). Útil pra
 *     colar em ferramenta que limita largura (ex: certo cliente email).
 *
 * Multi-tenant Tier 0 ({@see ADR 0093}): Brief é repo-wide, permission
 * `brief.history.view` (Wagner-only via RBAC, mesma de FetchBriefHistoryRequest).
 *
 * @see Modules\Brief\Http\Requests\FetchBriefHistoryRequest (listing)
 * @see memory/decisions/0091-daily-brief.md
 */
class ExportBriefMarkdownRequest extends FormRequest
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
            'redact_pii'       => ['nullable', 'boolean'],
            'include_metadata' => ['nullable', 'boolean'],
            // 0 (sem wrap) ou entre 40 e 200 (largura razoável). 0 padrão.
            'wrap_columns'     => ['nullable', 'integer', 'in:0,40,60,80,100,120,160,200'],
        ];
    }

    public function messages(): array
    {
        return [
            'redact_pii.boolean'       => 'Campo redact_pii deve ser booleano.',
            'include_metadata.boolean' => 'Campo include_metadata deve ser booleano.',
            'wrap_columns.in'          => 'wrap_columns aceita: 0 (sem wrap), 40, 60, 80, 100, 120, 160 ou 200.',
        ];
    }

    /**
     * Defaults seguros: PII redaction ON, metadata ON, sem wrap.
     */
    protected function prepareForValidation(): void
    {
        $merge = [];
        foreach (['redact_pii', 'include_metadata'] as $key) {
            if ($this->has($key)) {
                $merge[$key] = filter_var($this->input($key), FILTER_VALIDATE_BOOLEAN);
            }
        }
        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    /**
     * Helper: opções resolvidas com defaults seguros.
     *
     * @return array{redact_pii: bool, include_metadata: bool, wrap_columns: int}
     */
    public function optionsOrDefaults(): array
    {
        $v = $this->validated();

        return [
            'redact_pii'       => (bool) ($v['redact_pii'] ?? true),
            'include_metadata' => (bool) ($v['include_metadata'] ?? true),
            'wrap_columns'     => (int) ($v['wrap_columns'] ?? 0),
        ];
    }
}
