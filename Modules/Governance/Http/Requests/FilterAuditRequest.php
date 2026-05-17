<?php

declare(strict_types=1);

namespace Modules\Governance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest pra GET /governance/audit (filtros drill-down).
 *
 * D8.c Security — Wave 18 saturate. Atualmente AuditController@index lê
 * request->input() direto. Este FormRequest formaliza whitelist de filtros
 * (period enum / endpoint enum / status enum / actor slug pattern) bloqueando
 * SQL injection cosmética + payload malformado downstream em Service.
 *
 * Tier 0 IRREVOGÁVEL (memory/proibicoes.md §"Multi-tenant"):
 *   - mcp_audit_log é cross-tenant table (sem business_id global scope direto —
 *     ADR 0084 trigger MySQL fixa append-only via user_id + ts).
 *   - Service AuditDrillDownService já aplica filtros via DB::table()
 *     ->where() — FormRequest aqui é defesa em profundidade.
 *
 * Pattern canônico igual TogglePolicyRequest (Wave S Batch 2):
 *   - authorize() defesa em profundidade (middleware stack já gate admin)
 *   - rules() whitelist + messages PT-BR
 *
 * @see Modules/Governance/Http/Controllers/AuditController.php
 * @see Modules/Governance/Services/AuditDrillDownService.php
 */
class FilterAuditRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Middleware 'authh' + 'auth' ja gate admin no routes.php (Tier 0).
        // Aqui so garantimos user autenticado (defesa em profundidade).
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            // period enum fixo — bate com AuditDrillDownService::cutoffFor() match.
            'period'   => ['nullable', 'string', 'in:1h,24h,7d,30d'],

            // actor slug pattern (Modules/TeamMcp/Services/ActorResolver — kebab-case).
            // Max 64 chars (defensivo); SQL whereIn já fail-safe se actor nao encontrado.
            'actor'    => ['nullable', 'string', 'max:64', 'regex:/^[a-z0-9-]+$/i'],

            // endpoint enum mcp_audit_log (tools/list, tools/call, etc.). Max 191 = INDEX MySQL limit.
            'endpoint' => ['nullable', 'string', 'max:191'],

            // status enum mcp_audit_log (ok, error, denied, quota_exceeded). Whitelist conservadora.
            'status'   => ['nullable', 'string', 'in:ok,error,denied,quota_exceeded'],
        ];
    }

    public function messages(): array
    {
        return [
            'period.in'    => 'Período inválido. Use 1h, 24h, 7d ou 30d.',
            'actor.regex'  => 'Slug do actor deve conter apenas letras, números e hífen.',
            'actor.max'    => 'Slug do actor não pode ter mais de 64 caracteres.',
            'endpoint.max' => 'Endpoint não pode ter mais de 191 caracteres.',
            'status.in'    => 'Status inválido. Use ok, error, denied ou quota_exceeded.',
        ];
    }

    /**
     * Helper pra retornar filtros normalizados (sem keys vazias).
     *
     * @return array{period: string, actor: ?string, endpoint: ?string, status: ?string}
     */
    public function toFilterArray(): array
    {
        return [
            'period'   => $this->validated('period') ?? '24h',
            'actor'    => $this->validated('actor'),
            'endpoint' => $this->validated('endpoint'),
            'status'   => $this->validated('status'),
        ];
    }
}
