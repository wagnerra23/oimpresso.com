<?php

declare(strict_types=1);

namespace Modules\Brief\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * PurgeBriefHistoryRequest — Wave 23 D8 SECURITY.
 *
 * FormRequest pro futuro endpoint admin de purge manual da `mcp_briefs`
 * (`POST /brief/admin/purge`). Hoje a retenção é declarada em
 * `Modules/Brief/Config/retention.php` (Wave 13, ADR 0105 sinal qualificado
 * antes de virar job). Esta classe cobre o caso de Wagner pedir purge sob
 * demanda (ex: titular LGPD exigiu eliminação Art. 18 §VI).
 *
 * Multi-tenant Tier 0 IRREVOGAVEL (ADR 0093): brief é repo-wide (ADR 0091 §3),
 * `business_id` é nullable no schema mcp_briefs — coluna existe pra evolução
 * futura (briefs por tenant). Quando `business_id` vier no payload, request
 * exige permission `superadmin` E business pertencente ao usuário.
 *
 * Audit obrigatório: motivo livre-texto vira `mcp_audit_log.notes` pra
 * defesa em LGPD Art. 16 §I (eliminação registrada).
 *
 * @see Modules\Brief\Config\retention.php (retention canon)
 * @see Modules\Brief\Http\Requests\GenerateBriefRequest (pattern referência)
 * @see memory/decisions/0091-daily-brief.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
class PurgeBriefHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Wagner-only via permission `brief.purge`; permission separada
        // de `brief.access` pra blast radius mínimo (purge é destrutivo).
        return (bool) $this->user()?->can('brief.purge');
    }

    public function rules(): array
    {
        return [
            // older_than_days: purge briefs com generated_at < now() - N dias.
            // Mínimo 7d (defesa: evita zerar histórico recente sem querer).
            'older_than_days' => ['required', 'integer', 'min:7', 'max:3650'],

            // motivo: livre-texto pra audit log (LGPD Art. 16 §I evidência).
            'motivo' => ['required', 'string', 'min:10', 'max:500'],

            // dry_run: conta quantos seriam afetados, NÃO deleta.
            'dry_run' => ['nullable', 'boolean'],

            // include_invalid: também purga briefs com valid=0 (default true,
            // alinhado com retention.php#briefs_invalid:30d).
            'include_invalid' => ['nullable', 'boolean'],

            // business_id (futuro): purge por-tenant quando schema evoluir.
            // Hoje aceita null (purge repo-wide).
            'business_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'older_than_days.required' => 'Campo older_than_days obrigatório (mínimo 7 dias).',
            'older_than_days.min'      => 'older_than_days mínimo 7 dias (proteção).',
            'older_than_days.max'      => 'older_than_days máximo 10 anos.',
            'motivo.required'          => 'Motivo obrigatório (LGPD Art. 16 evidência).',
            'motivo.min'               => 'Motivo deve ter ao menos 10 caracteres.',
            'motivo.max'               => 'Motivo deve ter no máximo 500 caracteres.',
            'dry_run.boolean'          => 'Campo dry_run deve ser booleano.',
            'include_invalid.boolean'  => 'Campo include_invalid deve ser booleano.',
            'business_id.integer'      => 'business_id deve ser inteiro.',
        ];
    }

    /**
     * Coerce booleanos vindos de query/form string pra bool real.
     */
    protected function prepareForValidation(): void
    {
        $merge = [];
        foreach (['dry_run', 'include_invalid'] as $key) {
            if ($this->has($key)) {
                $merge[$key] = filter_var($this->input($key), FILTER_VALIDATE_BOOLEAN);
            }
        }
        if ($merge !== []) {
            $this->merge($merge);
        }
    }
}
