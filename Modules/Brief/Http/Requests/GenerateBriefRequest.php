<?php

declare(strict_types=1);

namespace Modules\Brief\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * GenerateBriefRequest — Wave 18 D8.c Security SATURATION.
 *
 * FormRequest dedicado pra futuro endpoint HTTP de geração de brief sob demanda
 * (`POST /brief/admin/generate`). Hoje a geração ocorre por CLI (`brief:generate`)
 * + cron 6x/dia. Esta classe cobre o caso de painel admin Wagner expor botão
 * "gerar agora" via UI Inertia, mantendo separação clara entre os 3 endpoints:
 *
 *   1. BriefFetchToolRequest        — tool MCP externa (cap 60/min throttle)
 *   2. ForceRefreshBriefRequest     — botão "regerar agora" admin (cap 8/dia)
 *   3. GenerateBriefRequest (este)  — geração nova on-demand (separa cache flush)
 *
 * Multi-tenant Tier 0 IRREVOGAVEL (ADR 0093): brief é repo-wide (1 brief pra todos
 * businesses — ver ADR 0091 §3). Sem business_id no payload — endpoint restrito
 * a superadmin via middleware (`tailscale-only` + `is-wagner`) quando vier.
 *
 * Pattern referência: pareado com `ForceRefreshBriefRequest` mas semântica
 * distinta — generate cria nova entry em `mcp_briefs`, refresh apenas invalida
 * cache `brief.current` e força próxima leitura pela base.
 *
 * @see Modules\Brief\Http\Requests\BriefFetchToolRequest (tool MCP scope)
 * @see Modules\Brief\Http\Requests\ForceRefreshBriefRequest (refresh cache scope)
 * @see Modules\Brief\Console\Commands\GenerateBriefCommand (CLI canônico hoje)
 * @see memory/decisions/0091-daily-brief.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
class GenerateBriefRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Wagner-only via permission RBAC; middleware de IP/Tailscale roda upstream.
        return (bool) $this->user()?->can('brief.access');
    }

    public function rules(): array
    {
        return [
            // dry_run=true: gera mas NÃO grava em mcp_briefs (apenas valida + retorna).
            'dry_run' => ['nullable', 'boolean'],

            // motivo: livre-texto pra audit log (mcp_audit_log.notes).
            'motivo' => ['nullable', 'string', 'max:255'],

            // bypass_cap: superadmin pula cap 8/dia de geração. Default false.
            // Apenas Wagner setado em policy futura; FormRequest só valida formato.
            'bypass_cap' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'motivo.max'         => 'Motivo deve ter no máximo 255 caracteres.',
            'dry_run.boolean'    => 'Campo dry_run deve ser booleano.',
            'bypass_cap.boolean' => 'Campo bypass_cap deve ser booleano.',
        ];
    }

    /**
     * Coerce booleanos vindos de query string ("true"/"1") pra bool real.
     */
    protected function prepareForValidation(): void
    {
        $merge = [];
        foreach (['dry_run', 'bypass_cap'] as $key) {
            if ($this->has($key)) {
                $merge[$key] = filter_var($this->input($key), FILTER_VALIDATE_BOOLEAN);
            }
        }
        if ($merge !== []) {
            $this->merge($merge);
        }
    }
}
