<?php

declare(strict_types=1);

namespace Modules\Brief\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * InvalidateBriefRequest — Wave 23 D8 SECURITY.
 *
 * FormRequest pro endpoint admin de invalidação manual de um brief específico
 * (`POST /brief/admin/{id}/invalidate`). Casos de uso:
 *
 *   1. Brief com PII vazada detectada pós-geração (BriefValidator passou mas
 *      review humana identificou; coluna `valid` vai pra 0 + cache flush)
 *   2. Brief gerado durante incident (dados aggregated cache stale/corrompido)
 *      precisa não-ser-servido até próximo cron substituir
 *   3. Audit LGPD — sinalizar brief específico como "elimine no próximo purge"
 *
 * Diferente do `PurgeBriefHistoryRequest` (purge em massa por idade),
 * `InvalidateBriefRequest` opera 1-by-1 com motivo específico per-brief.
 *
 * Multi-tenant Tier 0 IRREVOGAVEL (ADR 0093): brief é repo-wide, mas
 * invalidate é Wagner-only via permission RBAC.
 *
 * @see Modules\Brief\Http\Requests\PurgeBriefHistoryRequest (sibling em massa)
 * @see Modules\Brief\Http\Controllers\BriefFetchController (cache flush pattern)
 * @see memory/decisions/0091-daily-brief.md §schema
 */
class InvalidateBriefRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Permission `brief.purge` — mesma de purge (operação destrutiva soft).
        return (bool) $this->user()?->can('brief.purge');
    }

    public function rules(): array
    {
        return [
            // motivo: livre-texto pra audit (`mcp_audit_log.notes`).
            'motivo' => ['required', 'string', 'min:5', 'max:500'],

            // mark_for_purge: além de valid=0, sinaliza pra job purge limpar
            // antes da retention default (90d). Default false (apenas invalida).
            'mark_for_purge' => ['nullable', 'boolean'],

            // flush_cache: invalida 'brief.current' no Laravel Cache pra próxima
            // tool MCP brief-fetch puxar fresh do DB. Default true (recomendado).
            'flush_cache' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'motivo.required'         => 'Motivo obrigatório (auditoria).',
            'motivo.min'              => 'Motivo deve ter ao menos 5 caracteres.',
            'motivo.max'              => 'Motivo deve ter no máximo 500 caracteres.',
            'mark_for_purge.boolean'  => 'Campo mark_for_purge deve ser booleano.',
            'flush_cache.boolean'     => 'Campo flush_cache deve ser booleano.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        foreach (['mark_for_purge', 'flush_cache'] as $key) {
            if ($this->has($key)) {
                $merge[$key] = filter_var($this->input($key), FILTER_VALIDATE_BOOLEAN);
            }
        }
        if ($merge !== []) {
            $this->merge($merge);
        }
    }
}
