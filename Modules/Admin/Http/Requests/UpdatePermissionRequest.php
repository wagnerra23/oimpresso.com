<?php

namespace Modules\Admin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Esqueleto FormRequest pra atualizar permission (Spatie role/permission).
 *
 * Wave S (D8.c Security): Modules/Admin nao tem PermissionController
 * implementado ainda. Sprint Admin futuro vai expor gestao de permissions
 * Spatie pra superadmin via admin-center (tailscale-only + is-wagner).
 *
 * Tier 0 IRREVOGAVEL (memory/proibicoes.md §"FSM"): roles Spatie em
 * UltimatePOS tem suffix `#{biz}` quando `roles.business_id` NOT NULL.
 * NAO criar role global (sem business_id) — viola FK. Use
 * `Role::firstOrCreate(['name' => "{$role}#{$bizId}", ...])` ou auto-detect
 * via `Schema::hasColumn('roles', 'business_id')`.
 *
 * Pattern canonico ja estabelecido em FormRequests existentes:
 *   - StoreCmsPageRequest (Modules/Cms)
 *
 * @see memory/proibicoes.md §"FSM Pipeline Canonico" (regra roles#{biz})
 * @see memory/decisions/0122-admin-center-ct100.md
 */
class UpdatePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Middleware stack ja garante tailscale-only + auth + is-wagner.
        // Permission update e operacao sensivel — sempre passa por audit log
        // (Modules\Admin\Services\AdminAuditLogger no Controller).
        return $this->user() !== null;
    }

    /**
     * Regras placeholder — Sprint Admin futuro vai concretizar.
     *
     * Notas pro Sprint futuro:
     *   - `name` permission precisa whitelist (ex: 'crud_users', 'view_reports')
     *     pra evitar permission arbitraria.
     *   - `role_id` precisa exists no business_id correto (Spatie multi-tenant).
     *   - Toda mudanca obriga audit log com reason + confirm (pattern
     *     MutationsController double-confirmation).
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:191'],
            'guard_name' => ['nullable', 'string', 'in:web,api'],
        ];
    }
}
