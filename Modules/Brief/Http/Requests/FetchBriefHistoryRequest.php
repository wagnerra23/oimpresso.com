<?php

declare(strict_types=1);

namespace Modules\Brief\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FetchBriefHistoryRequest — Wave 25 D8 SECURITY.
 *
 * FormRequest pro endpoint admin `GET /brief/admin/history` (paginação
 * read-only do histórico `mcp_briefs` pra Wagner debug regressão Brain B
 * ou auditor revisar geração específica).
 *
 * Cobre:
 *   - paginação canônica (`page`, `per_page` cap 100)
 *   - filtro temporal (`from`, `to` — date ISO)
 *   - filtro `valid` (somente válidos | somente inválidos | tudo)
 *   - filtro `min_tokens` / `max_tokens` (debug variância de output Brain B)
 *
 * Multi-tenant Tier 0 ({@see ADR 0093}): Brief é repo-wide (sem business_id) —
 * permission `brief.history.view` (Wagner-only via RBAC).
 *
 * Diferente de `PurgeBriefHistoryRequest` (escrita destrutiva, exige motivo),
 * este é apenas read com filtros — sem motivo obrigatório.
 *
 * @see Modules\Brief\Http\Requests\PurgeBriefHistoryRequest (escrita destrutiva)
 * @see Modules\Brief\Http\Requests\InvalidateBriefRequest (escrita 1-by-1)
 * @see memory/decisions/0091-daily-brief.md §debug regressão
 */
class FetchBriefHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        // permission `brief.history.view` (Wagner/auditor — read-only).
        // Fallback pra superadmin se permission ainda não cadastrada.
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        return (bool) ($user->can('brief.history.view') || $user->can('superadmin'));
    }

    public function rules(): array
    {
        return [
            'page'     => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],

            'from'     => ['nullable', 'date'],
            'to'       => ['nullable', 'date', 'after_or_equal:from'],

            // valid: 'true' (só válidos), 'false' (só inválidos), null (tudo)
            'valid'    => ['nullable', 'in:true,false,1,0'],

            'min_tokens' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'max_tokens' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ];
    }

    public function messages(): array
    {
        return [
            'per_page.max'          => 'per_page máximo 100 (anti-DoS).',
            'to.after_or_equal'     => 'Data final deve ser >= data inicial.',
            'valid.in'              => "Campo valid deve ser 'true', 'false', '1' ou '0'.",
            'min_tokens.max'        => 'min_tokens cap absoluto 1.000.000.',
            'max_tokens.max'        => 'max_tokens cap absoluto 1.000.000.',
        ];
    }

    /**
     * Defaults pra paginação.
     */
    public function paginationOrDefaults(): array
    {
        return [
            'page'     => (int) ($this->validated()['page'] ?? 1),
            'per_page' => (int) ($this->validated()['per_page'] ?? 25),
        ];
    }
}
