<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest pra acknowledge de Tier 0 alert (Admin/Governance V4).
 *
 * Wave 25 SATURATION (Admin D8 boost +4): Wagner pode dar "ack" num ADR alert
 * que sabe estar em yellow/red mas que tem contexto (ex: maintenance window
 * Hostinger). Ack vira audit row em `admin_audit_logs` e snooze por N minutos
 * o widget W4 do dashboard pra evitar fadiga de alerta.
 *
 * Tier 0 IRREVOGÁVEL (memory/proibicoes.md):
 *   - Middleware stack garante tailscale-only + auth + is-wagner.
 *   - Ack NÃO resolve violação Tier 0 — só snooze. Health check continua red
 *     no DB; reset só via `RemediationRequest` (que executa fix real).
 *   - `snooze_minutes` cap em 60 (1h) — Wagner não pode silenciar Tier 0 por
 *     dias. Pode renovar manualmente cada hora se necessário.
 *
 * @see Modules\Admin\Http\Requests\RemediationRequest (sibling, fix real)
 * @see Modules\Admin\Services\AdrAlertReader (consome ack pra suppress)
 * @see memory/decisions/0122-admin-center-ct100.md
 */
class AlertAcknowledgeRequest extends FormRequest
{
    public const MAX_SNOOZE_MINUTES = 60;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'check_name'      => ['required', 'string', 'max:64'],
            'adr_id'          => ['required', 'string', 'in:'.implode(',', RemediationRequest::TIER_0_ADRS)],
            'snooze_minutes'  => ['required', 'integer', 'min:5', 'max:'.self::MAX_SNOOZE_MINUTES],
            'reason'          => ['required', 'string', 'min:5', 'max:500'],
            'confirm'         => ['required', 'boolean', 'in:1,true'],
        ];
    }

    public function messages(): array
    {
        return [
            'adr_id.in'           => 'ADR não está na whitelist Tier 0.',
            'snooze_minutes.max'  => 'Snooze máximo é '.self::MAX_SNOOZE_MINUTES.' minutos (1h). Renove manualmente se necessário.',
            'snooze_minutes.min'  => 'Snooze mínimo é 5 minutos.',
            'reason.min'          => 'Razão é obrigatória (≥5 chars) — vai pro audit log.',
            'confirm.in'          => 'Confirmação dupla obrigatória (confirm=true).',
        ];
    }
}
