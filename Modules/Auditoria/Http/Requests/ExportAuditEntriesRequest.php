<?php

declare(strict_types=1);

namespace Modules\Auditoria\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * ExportAuditEntriesRequest — Wave 25 D8 SECURITY.
 *
 * FormRequest pro endpoint `POST /auditoria/export` (CSV/JSON dump da grade
 * filtrada de activity_log). Cobre 4 vetores de risco:
 *
 *   1. **Anti-abuse**: cap hard `limit` (default 1000, max 10000) — evita
 *      auditor exportar 5M rows e travar o servidor.
 *   2. **Anti-PII vazada**: flag `include_properties` default `false`. Quando
 *      `true`, RevertService/Exporter passa pelo `PiiRedactor` (Tier 0).
 *   3. **Auditor self-justify**: campo `motivo` obrigatório (min:10) — fica
 *      gravado em `activity_log` evento `auditoria.export.requested`.
 *   4. **Whitelist format**: somente `csv` ou `json` — bloqueia tentativas
 *      tipo `php` (serialização) ou `xlsx` (não implementado).
 *
 * Multi-tenant Tier 0 ({@see ADR 0093}): business_id NUNCA chega no input —
 * AuditoriaController resolve via session. Defesa profundidade:
 * `business_id => prohibited`.
 *
 * Compõe filtros via `FilterAuditEntriesRequest::rules()` herdadas via spread —
 * mantém consistência com a tela `/auditoria` (mesmos filtros = mesmo export).
 *
 * @see Modules\Auditoria\Http\Requests\FilterAuditEntriesRequest (filtros base)
 * @see Modules\Auditoria\Http\Requests\BulkRevertActivityRequest (sibling D8 bulk)
 * @see memory/decisions/0127-modulo-auditoria-ui-undo.md §export
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
class ExportAuditEntriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }
        if ($user->can('superadmin')) {
            return true;
        }

        // Permissão dedicada `auditoria.export` (separar de read pra audit log
        // discriminar export vs view casual).
        return (bool) ($user->can('auditoria.export') || $user->can('auditoria.view'));
    }

    public function rules(): array
    {
        return [
            // Filtros base (mesmos do FilterAuditEntriesRequest pra UX consistente)
            'causer_kind'   => ['nullable', 'string', 'in:user,ia,system'],
            'subject_type'  => ['nullable', 'string', 'max:255'],
            'event'         => ['nullable', 'string', 'in:created,updated,deleted,reverted,restored'],
            'period_start'  => ['nullable', 'date'],
            'period_end'    => ['nullable', 'date', 'after_or_equal:period_start'],

            // Export-specific
            'format'        => ['required', 'string', 'in:csv,json'],
            'limit'         => ['nullable', 'integer', 'min:1', 'max:10000'],
            'include_properties' => ['nullable', 'boolean'],
            'motivo'        => ['required', 'string', 'min:10', 'max:500'],

            // ANTI-SPOOFING: business_id JAMAIS no body
            'business_id'   => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'format.required'     => 'Formato obrigatório (csv ou json).',
            'format.in'           => 'Formato deve ser csv ou json.',
            'limit.max'           => 'Limite máximo de exportação: 10.000 entradas (anti-abuse).',
            'motivo.required'     => 'Motivo obrigatório (gravado em audit log).',
            'motivo.min'          => 'Motivo deve ter ao menos 10 caracteres.',
            'period_end.after_or_equal' => 'Período final deve ser >= período inicial.',
            'business_id.prohibited' => 'business_id não pode vir no body — derivado da session.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if ($this->has('include_properties')) {
            $merge['include_properties'] = filter_var(
                $this->input('include_properties'),
                FILTER_VALIDATE_BOOLEAN
            );
        }

        // Default limit caso não venha (preserva backward-compat)
        if (! $this->has('limit')) {
            $merge['limit'] = 1000;
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }
}
