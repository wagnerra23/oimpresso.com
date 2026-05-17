<?php

namespace Modules\Crm\Http\Requests;

use App\Utils\ModuleUtil;
use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest pra atualizar Proposal existente.
 *
 * Wave 18 D8.b: complementa `StoreProposalRequest` (Wave 15). Antes update
 * estava sem FormRequest (Controller método vazio). Habilita ProposalService
 * `updateProposal()` chamado por Controller numa próxima Wave.
 *
 * Subset rules `sometimes` — payload PATCH parcial aceito.
 *
 * @see Modules\Crm\Services\ProposalService::updateProposal
 * @see Modules\Crm\Http\Controllers\ProposalController::update
 */
class UpdateProposalRequest extends FormRequest
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
        $businessId = $this->session()->get('user.business_id');

        return (bool) app(ModuleUtil::class)->hasThePermissionInSubscription($businessId, 'crm_module');
    }

    public function rules(): array
    {
        return [
            'subject'    => ['sometimes', 'required', 'string', 'max:255'],
            'body'       => ['sometimes', 'required', 'string'],
            'contact_id' => ['sometimes', 'required', 'integer', 'min:1'],
            'cc'         => ['nullable', 'string', 'max:500'],
            'bcc'        => ['nullable', 'string', 'max:500'],
        ];
    }
}
