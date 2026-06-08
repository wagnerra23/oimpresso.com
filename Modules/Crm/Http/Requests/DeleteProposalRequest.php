<?php

declare(strict_types=1);

namespace Modules\Crm\Http\Requests;

use App\Utils\ModuleUtil;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Crm\Entities\Proposal;

/**
 * FormRequest pra DELETE /crm/proposals/{id} — Wave 23 D8 Security saturation.
 *
 * Authorize compõe: permissão `crm.proposal.delete` + ownership scoped por
 * business + status NÃO `accepted` (acceptd não permite soft-delete sem
 * justificativa — workflow business: aceita ↔ venda criada ↔ FSM Sells).
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093).
 */
class DeleteProposalRequest extends FormRequest
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

        $businessId = (int) $this->session()->get('user.business_id', 0);
        if ($businessId === 0) {
            return false;
        }

        $moduleUtil = app(ModuleUtil::class);
        if (! $moduleUtil->hasThePermissionInSubscription($businessId, 'crm_module')) {
            return false;
        }

        // Ownership: Proposal precisa pertencer ao business da sessão.
        $proposalId = (int) $this->route('id', 0);
        if ($proposalId === 0) {
            return false;
        }

        $proposal = Proposal::query()
            ->where('business_id', $businessId)
            ->where('id', $proposalId)
            ->first();

        if ($proposal === null) {
            return false;
        }

        // Workflow: accepted proposals exigem motivo (regra de negócio).
        if ($proposal->status === 'accepted' && empty($this->input('motivo'))) {
            return false;
        }

        return (bool) ($user->can('crm.proposal.delete') ?? true);
    }

    public function rules(): array
    {
        return [
            'motivo' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'motivo.max' => 'O motivo de exclusão deve ter no máximo 500 caracteres.',
        ];
    }
}
