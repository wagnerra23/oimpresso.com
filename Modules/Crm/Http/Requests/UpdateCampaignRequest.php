<?php

declare(strict_types=1);

namespace Modules\Crm\Http\Requests;

use App\Utils\ModuleUtil;
use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest pra atualizar Campaign — irmão de StoreCampaignRequest.
 *
 * Wave 25 D8 polish — extraído de CampaignController@update. Rules permissivas
 * em fields opcionais (partial update via PATCH/PUT-mass).
 *
 * Multi-tenant Tier 0 (ADR 0093).
 *
 * @see Modules\Crm\Http\Controllers\CampaignController::update
 */
class UpdateCampaignRequest extends FormRequest
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
        $moduleUtil = app(ModuleUtil::class);

        return (bool) $moduleUtil->hasThePermissionInSubscription($businessId, 'crm_module');
    }

    public function rules(): array
    {
        return [
            'name'              => ['sometimes', 'string', 'max:191'],
            'campaign_type'     => ['sometimes', 'string', 'in:email,sms,birthday_wishes'],
            'subject'           => ['nullable', 'string', 'max:255'],
            'email_body'        => ['nullable', 'string'],
            'sms_body'          => ['nullable', 'string'],
            'contact_id'        => ['nullable', 'array'],
            'contact_id.*'      => ['integer'],
            'lead_id'           => ['nullable', 'array'],
            'lead_id.*'         => ['integer'],
            'to'                => ['nullable', 'string', 'max:50'],
            'trans_activity'    => ['nullable', 'string', 'max:50'],
            'in_days'           => ['nullable', 'integer', 'min:0', 'max:365'],
            'send_notification' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'campaign_type.in' => 'Tipo inválido (email | sms | birthday_wishes).',
            'in_days.max'      => 'Janela máxima 365 dias.',
        ];
    }
}
