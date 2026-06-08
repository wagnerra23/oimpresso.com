<?php

declare(strict_types=1);

namespace Modules\Governance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Esqueleto FormRequest pra POST /governance/reports/generate (Wave 18 saturate).
 *
 * D8.c Security — controller GenerateReportController ainda não existe
 * (rota futura). Esqueleto deixado pra Sprint Governance futuro quando UI
 * de export ModuleGrades/Audit virar disponível.
 *
 * Caso de uso futuro:
 *   - Wagner export CSV/PDF de ModuleGrades pra Daily Brief
 *   - Compliance officer export audit log filtrado por período (LGPD Art. 18)
 *
 * Tier 0 IRREVOGÁVEL:
 *   - format whitelist (csv|pdf|xlsx) — bloqueia path traversal em template
 *     name caso PDF renderer use file-based templates
 *   - business_id scope ENFORCED pelo Service downstream (multi-tenant)
 *
 * Pattern canônico igual TogglePolicyRequest + UpdateActorRequest (Wave S Batch 2):
 *   - authorize() defesa em profundidade
 *   - rules() whitelist conservadora + mensagens PT-BR
 *
 * @see memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md §3 LGPD
 * @see Modules/Governance/Config/retention.php
 */
class GenerateReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Middleware stack ('web' + 'authh' + 'auth') gate admin.
        // Sprint futuro vai adicionar Spatie permission check explicito
        // (ex: governance.reports.generate).
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            // Tipo de report (whitelist conservadora — bloqueia injection).
            'type'        => ['required', 'string', 'in:module_grades,audit_log,drift,policies'],

            // Formato de saída (whitelist — defensivo contra path traversal).
            'format'      => ['required', 'string', 'in:csv,pdf,xlsx'],

            // Período: opcional, default 30d.
            'period'      => ['nullable', 'string', 'in:7d,30d,90d,180d,1y'],

            // business_id scope explícito (Tier 0 IRREVOGÁVEL). Validate exists.
            'business_id' => ['nullable', 'integer', 'min:1', 'exists:business,id'],

            // Reason obrigatória pra audit (mesmo pattern RevertActivity).
            'reason'      => ['required', 'string', 'min:10', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required'        => 'Tipo do report é obrigatório.',
            'type.in'              => 'Tipo inválido. Use module_grades, audit_log, drift ou policies.',
            'format.required'      => 'Formato é obrigatório.',
            'format.in'            => 'Formato inválido. Use csv, pdf ou xlsx.',
            'period.in'            => 'Período inválido. Use 7d, 30d, 90d, 180d ou 1y.',
            'business_id.exists'   => 'Business inexistente.',
            'reason.required'      => 'Razão do export é obrigatória (audit log).',
            'reason.min'           => 'Razão deve ter pelo menos 10 caracteres.',
            'reason.max'           => 'Razão não pode ter mais de 500 caracteres.',
        ];
    }
}
