<?php

declare(strict_types=1);

namespace Modules\Connector\Http\Requests;

use App\Utils\ModuleUtil;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

/**
 * D8.c Security Wave 10 — FormRequest extraido de Api\Crm\FollowUpController::update.
 *
 * Regras espelham o $request->validate inline original (linhas 595-602) sem expansao
 * de escopo. authorize() checa Passport + CRM module installed + permission
 * (access_all_schedule OU access_own_schedule).
 */
class UpdateFollowUpRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = Auth::user();

        if ($user === null || empty($user->business_id)) {
            return false;
        }

        $moduleUtil = app(ModuleUtil::class);

        if (! $moduleUtil->isModuleInstalled('Crm')) {
            return false;
        }

        return $user->can('crm.access_all_schedule') || $user->can('crm.access_own_schedule');
    }

    public function rules(): array
    {
        return [
            'title' => ['required'],
            'contact_id' => ['required'],
            'start_datetime' => ['required'],
            'end_datetime' => ['required'],
            'schedule_type' => ['required'],
            'user_id' => ['required'],
        ];
    }
}
