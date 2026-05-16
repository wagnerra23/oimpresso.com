<?php

declare(strict_types=1);

namespace Modules\Brief\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * D8.c Security Wave 17 — FormRequest auxiliar pra futura ação force-refresh admin.
 *
 * Hoje `force_refresh` chega como flag no body do `BriefFetchToolRequest`. Quando
 * a UI admin (US-COPI-091) entrar com botão "regerar agora", este FormRequest
 * cobre o endpoint dedicado `POST /brief/admin/force-refresh` mantendo separação
 * de responsabilidades:
 *   - BriefFetchToolRequest: tool MCP externa (cap 8/dia já enforced no Controller)
 *   - ForceRefreshBriefRequest: tela admin Wagner-only (RBAC `brief.access` + superadmin)
 *
 * Multi-tenant Tier 0: brief é repo-wide (1 brief para todos businesses) — NÃO
 * scoped por business_id (ver ADR 0091 §3). Endpoint restrito a superadmin Wagner.
 *
 * @see Modules\Brief\Http\Requests\BriefFetchToolRequest (FormRequest irmão, scope tool MCP)
 * @see memory/decisions/0091-daily-brief.md
 */
class ForceRefreshBriefRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Wagner-only: superadmin + permission brief.access.
        return $this->user()?->can('superadmin')
            && $this->user()?->can('brief.access');
    }

    public function rules(): array
    {
        return [
            // motivo é opcional mas útil pra audit log quando entrar.
            'motivo' => ['nullable', 'string', 'max:255'],
            // dry_run permite testar geração sem invalidar cache 'brief.current'.
            'dry_run' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'motivo.max' => 'Motivo deve ter no máximo 255 caracteres.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('dry_run')) {
            $this->merge([
                'dry_run' => filter_var($this->input('dry_run'), FILTER_VALIDATE_BOOLEAN),
            ]);
        }
    }
}
