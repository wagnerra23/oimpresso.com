<?php

declare(strict_types=1);

namespace Modules\Governance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Esqueleto FormRequest pra atualizar Actor (Governance — quem pode executar
 * que policy/action).
 *
 * Wave S Batch 2 (D8.c Security): Modules/Governance NAO TEM ActorController
 * implementado ainda — routes.php so expoe Dashboard/Policies/Audit/Drift/
 * ModuleGrade. Esqueleto deixado pra Sprint Governance futuro quando UI de
 * actor management for criada (provavelmente vinculado a Spatie role+business_id).
 *
 * Tier 0 IRREVOGAVEL (memory/proibicoes.md §"FSM Pipeline Canonico"):
 *   - Roles Spatie em UltimatePOS tem suffix `#{biz}` quando
 *     `roles.business_id` NOT NULL.
 *   - NAO criar role/actor global (sem business_id) — viola FK.
 *   - Use `Role::firstOrCreate(['name' => "{$role}#{$bizId}", ...])` ou
 *     auto-detect via `Schema::hasColumn('roles', 'business_id')`.
 *
 * Constituicao Art. 8: governance actors tem mapeamento N:M com policies via
 * mcp_governance_rules + permissions Spatie. Mudanca em actor obriga audit
 * log + reason (pattern double-confirm).
 *
 * Pattern canonico ja estabelecido em FormRequests existentes:
 *   - Modules\Admin\Http\Requests\UpdatePermissionRequest (esqueleto similar)
 *
 * @see memory/proibicoes.md §"FSM Pipeline Canonico" (regra roles#{biz})
 * @see Modules/Governance/Services/PolicyToggleService.php
 */
class UpdateActorRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Middleware stack ('web' + 'authh' + 'auth') ja gate admin no
        // routes.php — actor management e sensitivo (Constituicao Art. 8).
        // Sprint Governance futuro vai adicionar Spatie permission check
        // explicito (ex: governance.actor.update).
        return $this->user() !== null;
    }

    /**
     * Regras placeholder — Sprint Governance futuro vai concretizar.
     *
     * Notas pro Sprint futuro:
     *   - `actor_id` precisa exists no business_id correto (Spatie multi-tenant)
     *   - `role_name` precisa whitelist + suffix `#{biz}` enforced
     *   - `policy_ids` array de mcp_governance_rules.id no business_id correto
     *   - Reason obrigatoria pra audit log (min 10 chars como RevertActivity)
     */
    public function rules(): array
    {
        return [
            'actor_id'   => ['nullable', 'integer', 'exists:users,id'],
            'role_name'  => ['nullable', 'string', 'max:191'],
            'policy_ids' => ['nullable', 'array'],
            'policy_ids.*' => ['integer', 'exists:mcp_governance_rules,id'],
            'reason'     => ['nullable', 'string', 'min:10', 'max:500'],
        ];
    }
}
