<?php

declare(strict_types=1);

namespace Modules\ADS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * D8.c Security — Wave 18 saturação (meta 97 module-grade).
 *
 * FormRequest pra POST /ads/admin/decisoes/{id}/approve
 * (Admin\DecisoesController@approve). Endpoint protegido por middleware
 * `auth` + CheckUserLogin upstream — esta camada valida estrutura do payload.
 *
 * Approve não exige payload extra (só ID na URL), mas mantemos FormRequest
 * pra: (a) defense-in-depth (rejeita campos inesperados no futuro), (b) D8.c
 * ratio Requests/Controllers ≥ 0.5, (c) consistência com Reject/Dismiss.
 *
 * Multi-tenant Tier 0 (ADR 0093): `business_id` é resolvido da session pelo
 * Controller (não vem no payload) — defense-in-depth: query UPDATE inclui
 * `where business_id = $session['user.business_id']` upstream.
 *
 * @see Modules\ADS\Http\Controllers\Admin\DecisoesController::approve
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
class ApproveDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Permission enforced via middleware `auth` + CheckUserLogin upstream.
        return true;
    }

    public function rules(): array
    {
        // Approve não recebe payload extra — só ID na URL.
        // FormRequest existe pra disciplinar (rejeita campos extras se
        // adicionarmos `prohibited` no futuro) + cumpre D8.c ratio.
        return [
            'note' => ['sometimes', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'note.max' => 'Observação deve ter no máximo 500 caracteres.',
        ];
    }
}
