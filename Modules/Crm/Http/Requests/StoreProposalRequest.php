<?php

namespace Modules\Crm\Http\Requests;

use App\Utils\ModuleUtil;
use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest pra criar Proposal (proposta enviada a contato) no Crm.
 *
 * Extraido de ProposalController@store (Wave 15 D8 Security).
 * Antes: validação inline $request->validate([...]) + abort(403) inline.
 * Agora: authorize() centraliza permissão (subscription + superadmin) + rules() formaliza contrato.
 *
 * NUNCA fixar business_id em rules (multi-tenant scope automatico via session — ADR 0093).
 *
 * @see Modules/Crm/Http/Controllers/ProposalController.php
 */
class StoreProposalRequest extends FormRequest
{
    /**
     * Apenas quem tem o modulo Crm habilitado na assinatura pode criar proposal.
     */
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
        $moduleUtil = app(ModuleUtil::class);

        return (bool) $moduleUtil->hasThePermissionInSubscription($businessId, 'crm_module');
    }

    /**
     * Regras minimas preservando o contrato original ($request->only(subject/body/contact_id/cc/bcc)).
     */
    public function rules(): array
    {
        return [
            'contact_id' => ['required', 'integer'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'cc' => ['nullable', 'string', 'max:500'],
            'bcc' => ['nullable', 'string', 'max:500'],
        ];
    }
}
