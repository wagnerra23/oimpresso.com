<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Wave 29 Agent B (2026-05-17) — Override de bucket de módulo (intent-only).
 *
 * Endpoint NÃO move bucket diretamente — apenas registra intent no audit log
 * + retorna instrução pra Wagner abrir PR manual editando
 * `Modules/<X>/module.json` governance.bucket + label `bucket-change-approved`.
 *
 * Por que intent-only (anti-Goodhart):
 *   - Bucket = fonte-de-verdade module.json, versionado no git
 *   - Move via API quebraria audit-trail PR/review + CI mwart-gate
 *   - Razão (≥20 chars) obrigatória pra registrar contexto de mudança
 *
 * Validação:
 *   - module: PascalCase canônico
 *   - old_bucket / new_bucket: whitelist ADR 0160 (mesmos 4 buckets canon)
 *   - razao: texto ≥20 chars (força explicar — não vira "ajuste rápido")
 *
 * @see Modules\Admin\Http\Controllers\GovernanceV4DashboardController::overrideBucket
 * @see memory/decisions/0160-governance-v4-scoped-scorecards-bucket-meta.md
 */
class OverrideBucketRequest extends FormRequest
{
    /** Whitelist buckets canônicos ADR 0160 (mesmo que CreateInitiativeRequest). */
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
            'module'     => ['required', 'string', 'max:64', 'regex:/^[A-Za-z][A-Za-z0-9_]*$/'],
            'old_bucket' => ['required', 'string', 'in:'.implode(',', self::BUCKETS)],
            'new_bucket' => ['required', 'string', 'in:'.implode(',', self::BUCKETS), 'different:old_bucket'],
            'razao'      => ['required', 'string', 'min:20', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'module.regex'        => 'Nome do módulo deve seguir PascalCase (apenas letras, dígitos, underscore — começar com letra).',
            'old_bucket.in'       => 'old_bucket inválido — apenas: '.implode(', ', self::BUCKETS).' (ADR 0160).',
            'new_bucket.in'       => 'new_bucket inválido — apenas: '.implode(', ', self::BUCKETS).' (ADR 0160).',
            'new_bucket.different' => 'new_bucket deve ser diferente do old_bucket.',
            'razao.min'           => 'Razão deve ter ≥20 chars (força explicar o que justifica mover bucket).',
            'razao.max'           => 'Razão máximo 500 chars (resumir — detalhes ficam no PR).',
        ];
    }
}
