<?php

namespace Modules\Crm\Http\Requests;

use App\Utils\ModuleUtil;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * FormRequest pra criar Campaign (email/sms/birthday) no Crm.
 *
 * Extraido de CampaignController@store (D8.c Security — Onda 3).
 * Rules baseadas nas chaves usadas em $request->only(...) do Controller original.
 */
class StoreCampaignRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:191'],
            'campaign_type' => ['required', 'string', 'in:email,sms,birthday_wishes'],
            'subject' => ['nullable', 'string', 'max:255'],
            'email_body' => ['nullable', 'string'],
            'sms_body' => ['nullable', 'string'],
            'to' => ['nullable', 'string', 'max:50'],
            'trans_activity' => ['nullable', 'string', 'max:50'],
            'in_days' => ['nullable', 'integer', 'min:0'],
            'send_notification' => ['nullable'],
        ] + self::regrasDeContato((int) $this->session()->get('user.business_id'));
    }

    /**
     * Destinatários da campanha só do negócio da sessão (achado A1, thread Crm/11 — ADR 0093).
     * Antes era só `integer`: um id de contato de outro negócio era gravado e recebia o envio.
     * Usado também pelo `update` do controller, que não tem FormRequest.
     */
    public static function regrasDeContato(int $businessId): array
    {
        $doNegocio = Rule::exists('contacts', 'id')->where('business_id', $businessId);

        return [
            'contact_id' => ['nullable', 'array'],
            'contact_id.*' => ['integer', $doNegocio],
            'lead_id' => ['nullable', 'array'],
            'lead_id.*' => ['integer', $doNegocio],
            'contact' => ['nullable', 'array'],
            'contact.*' => ['integer', $doNegocio],
        ];
    }
}
