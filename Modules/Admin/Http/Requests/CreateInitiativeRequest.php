<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Wave 29 Agent B (2026-05-17) — Criar Initiative manual via Admin Center.
 *
 * Validação pré-Service:
 *   - module: nome string canônico (Vestuario, Governance, etc) — não checa
 *     existência aqui (Service tolera string livre + idempotent por module+rule_id)
 *   - bucket: whitelist ADR 0160 (4 buckets canônicos)
 *   - rule_id: string formato livre (F1.a, V6.b, D9.b — varia por scorecard YAML)
 *   - score_before / score_target: 0..100 (governance scorecard range)
 *   - deadline_days: 1..90 (Cortex/Port pattern, default 14 InitiativeService)
 *
 * Tier 0 IRREVOGÁVEL:
 *   - Middleware stack tailscale-only + auth + is-wagner já restringe pra
 *     Wagner-only. Não rebaixa authorize().
 *   - Audit log (AdminAuditLogger) capturado no Controller — PII redact D7.a.
 *
 * Pattern canônico: RemediationRequest (Wave 25) — mesma estrutura.
 *
 * @see Modules\Admin\Http\Controllers\GovernanceV4DashboardController::createInitiative
 * @see Modules\Governance\Services\InitiativeService::createFromScorecardBreach
 * @see memory/decisions/0160-governance-v4-scoped-scorecards-bucket-meta.md
 */
class CreateInitiativeRequest extends FormRequest
{
    /** Whitelist buckets canônicos ADR 0160. */
    public const BUCKETS = [
        'vertical_client_facing',
        'cross_cutting_infra',
        'ai_central',
        'functional_horizontal',
    ];

    public function authorize(): bool
    {
        // Middleware stack já garante tailscale-only + auth + is-wagner.
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'module'        => ['required', 'string', 'max:64', 'regex:/^[A-Za-z][A-Za-z0-9_]*$/'],
            'bucket'        => ['required', 'string', 'in:'.implode(',', self::BUCKETS)],
            'rule_id'       => ['required', 'string', 'max:64'],
            'score_before'  => ['required', 'integer', 'min:0', 'max:100'],
            'score_target'  => ['required', 'integer', 'min:0', 'max:100'],
            'deadline_days' => ['nullable', 'integer', 'min:1', 'max:90'],
        ];
    }

    public function messages(): array
    {
        return [
            'module.regex'        => 'Nome do módulo deve seguir PascalCase (apenas letras, dígitos, underscore — começar com letra).',
            'bucket.in'           => 'Bucket inválido — apenas: '.implode(', ', self::BUCKETS).' (ADR 0160).',
            'rule_id.required'    => 'rule_id obrigatório (ex: F1.a, V6.b, D9.b — conforme scorecard YAML).',
            'score_before.max'    => 'score_before deve estar entre 0 e 100 pts.',
            'score_target.max'    => 'score_target deve estar entre 0 e 100 pts.',
            'deadline_days.max'   => 'deadline_days máximo 90 dias (Cortex/Port best-practice).',
        ];
    }
}
