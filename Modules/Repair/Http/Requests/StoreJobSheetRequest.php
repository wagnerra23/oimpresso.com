<?php

namespace Modules\Repair\Http\Requests;

use App\Utils\ModuleUtil;
use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest pra criar JobSheet (Repair).
 *
 * D8.c Security — Wave S. Extraido de JobSheetController@store.
 *
 * Sem $request->validate() inline pré-existente — rules derivadas dos campos
 * em $request->only(...) + permission check do método.
 */
class StoreJobSheetRequest extends FormRequest
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

        if (! $moduleUtil->hasThePermissionInSubscription($businessId, 'repair_module')) {
            return false;
        }

        return $user->can('job_sheet.create');
    }

    public function rules(): array
    {
        return [
            'contact_id'             => ['required', 'integer', 'exists:contacts,id'],
            'service_type'           => ['nullable', 'string', 'max:255'],
            'brand_id'               => ['nullable', 'integer'],
            'device_id'              => ['nullable', 'integer'],
            'device_model_id'        => ['nullable', 'integer'],
            'security_pwd'           => ['nullable', 'string', 'max:255'],
            'security_pattern'       => ['nullable', 'string', 'max:255'],
            'serial_no'              => ['nullable', 'string', 'max:255'],
            'status_id'              => ['nullable', 'integer', 'exists:repair_statuses,id'],
            'delivery_date'          => ['nullable', 'string'],
            'estimated_cost'         => ['nullable', 'string'],
            'product_configuration'  => ['nullable', 'string'],
            'defects'                => ['nullable', 'array'],
            'product_condition'      => ['nullable', 'string'],
            'service_staff'          => ['nullable', 'integer'],
            'location_id'            => ['nullable', 'integer', 'exists:business_locations,id'],
            'pick_up_on_site_addr'   => ['nullable', 'string'],
            'comment_by_ss'          => ['nullable', 'string'],
            'custom_field_1'         => ['nullable', 'string', 'max:255'],
            'custom_field_2'         => ['nullable', 'string', 'max:255'],
            'custom_field_3'         => ['nullable', 'string', 'max:255'],
            'custom_field_4'         => ['nullable', 'string', 'max:255'],
            'custom_field_5'         => ['nullable', 'string', 'max:255'],
            'repair_checklist'       => ['nullable', 'array'],
            'send_notification'      => ['nullable', 'array'],
            'send_notification.*'    => ['string', 'in:sms,email'],
            'submit_type'            => ['nullable', 'string', 'in:save_and_add_parts,save_and_upload_docs'],
        ];
    }
}
