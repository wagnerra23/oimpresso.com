<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest pra disparar remediation Tier 0 (Admin/Governance V4).
 *
 * Wave 25 SATURATION (Admin D8 boost +4): Admin Center expõe remediation
 * actions pra ADR alerts Tier 0 (multi_tenant_isolation, pii_leak, etc) via
 * GovernanceV4DashboardController. Pattern double-confirmation (igual
 * MutationsController) — `reason` + `confirm` obrigatórios + audit log antes
 * de executar.
 *
 * Tier 0 IRREVOGÁVEL (memory/proibicoes.md):
 *   - Middleware stack já garante tailscale-only + auth + is-wagner — só Wagner
 *     dispara remediation. Não rebaixa authorize() pra public.
 *   - PII NUNCA aparece em `reason` (string opcional do superadmin). Skill
 *     `commit-discipline` (Tier A) faz redact via PiiRedactor antes do audit.
 *   - `adr_id` whitelist (apenas ADRs Tier 0 conhecidas — `0093`, `0094`,
 *     `0053`, `0062`) pra evitar payload arbitrário no audit.
 *
 * Pattern canônico estabelecido em FormRequests existentes:
 *   - StoreUserRequest (Admin) — esqueleto pré-Wave 25
 *   - UpdatePermissionRequest (Admin) — esqueleto pré-Wave 25
 *   - StoreCmsPageRequest (Cms)
 *
 * @see Modules\Admin\Http\Controllers\GovernanceV4DashboardController (Wave 24)
 * @see Modules\Admin\Http\Controllers\MutationsController (pattern double-confirm)
 * @see memory/decisions/0122-admin-center-ct100.md
 */
class RemediationRequest extends FormRequest
{
    /** Whitelist de ADRs Tier 0 elegíveis pra remediation pelo Admin. */
    public const TIER_0_ADRS = ['0093', '0094', '0053', '0062', '0143'];

    public function authorize(): bool
    {
        // Middleware stack já garante tailscale-only + auth + is-wagner.
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'adr_id'            => ['required', 'string', 'in:'.implode(',', self::TIER_0_ADRS)],
            'check_name'        => ['required', 'string', 'max:64'],
            'remediation_kind'  => ['required', 'string', 'in:retry_health_check,invalidate_cache,reset_global_scope,notify_team'],
            'reason'            => ['required', 'string', 'min:5', 'max:500'],
            'confirm'           => ['required', 'boolean', 'in:1,true'],
            // Payload livre por kind, mas sempre array (não objeto arbitrário).
            'payload'           => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'adr_id.in'           => 'ADR não está na whitelist Tier 0 (apenas: '.implode(', ', self::TIER_0_ADRS).').',
            'reason.min'          => 'Razão é obrigatória (≥5 chars) — vai pro audit log.',
            'confirm.in'          => 'Confirmação dupla obrigatória (confirm=true).',
            'remediation_kind.in' => 'Kind de remediation inválido.',
        ];
    }
}
