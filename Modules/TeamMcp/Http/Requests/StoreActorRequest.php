<?php

declare(strict_types=1);

namespace Modules\TeamMcp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest pra criação de McpActor (Identity Mesh ADR 0081).
 *
 * Wave 18 D8 SATURATION — pares com IssueActorTokenRequest (já existente)
 * mas pra entity primária `mcp_actors` (criação humano ou IA).
 *
 * **Permissão**: `copiloto.mcp.usage.all` (Wagner/superadmin) — só Wagner
 * cria actors novos (Identity Mesh seedado em migration; novo actor é
 * evento de governança raro).
 *
 * **Tier 0 IRREVOGÁVEL ADR 0081**: `mcp_actors` é cross-tenant — NUNCA
 * gravar `business_id` (transcende tenants by design).
 *
 * Rules (cobertura allow-list hardening):
 *   - slug: kebab-case unique (max 60)
 *   - type: enum human|ai
 *   - trust_level: 0..4 (L0 Wagner; L4 sandbox)
 *   - modules_write/read/blocked: array de strings (módulos canon)
 *   - audit_required: boolean
 */
class StoreActorRequest extends FormRequest
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
        return $user->can('copiloto.mcp.usage.all');
    }

    public function rules(): array
    {
        return [
            'slug'             => ['required', 'string', 'max:60', 'regex:/^[a-z0-9][a-z0-9\-]*$/', 'unique:mcp_actors,slug'],
            'type'             => ['required', 'in:human,ai'],
            'trust_level'      => ['required', 'integer', 'between:0,4'],
            'display_name'     => ['nullable', 'string', 'max:120'],
            'parent_actor_id'  => ['nullable', 'integer', 'exists:mcp_actors,id'],
            'user_id'          => ['nullable', 'integer', 'exists:users,id'],
            'modules_write'    => ['nullable', 'array'],
            'modules_write.*'  => ['string', 'max:40'],
            'modules_read'     => ['nullable', 'array'],
            'modules_read.*'   => ['string', 'max:40'],
            'modules_blocked'  => ['nullable', 'array'],
            'modules_blocked.*'=> ['string', 'max:40'],
            'skills_required'  => ['nullable', 'array'],
            'skills_required.*'=> ['string', 'max:60'],
            'actions_blocked'  => ['nullable', 'array'],
            'actions_blocked.*'=> ['string', 'max:60'],
            'audit_required'   => ['nullable', 'boolean'],
            'notes'            => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.regex'         => 'Slug deve ser kebab-case lowercase (a-z, 0-9, hífen).',
            'slug.unique'        => 'Já existe actor com este slug — Identity Mesh é append-only.',
            'trust_level.between'=> 'trust_level deve estar entre 0 (L0 Wagner) e 4 (L4 sandbox).',
            'type.in'            => 'type deve ser "human" ou "ai".',
        ];
    }
}
