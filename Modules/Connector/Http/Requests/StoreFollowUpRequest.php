<?php

declare(strict_types=1);

namespace Modules\Connector\Http\Requests;

use App\Utils\ModuleUtil;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

/**
 * D8.c Security Wave 10 — FormRequest extraido de Api\Crm\FollowUpController::store.
 *
 * Regras espelham o $request->validate inline original (linhas 357-364) sem expansao
 * de escopo. authorize() checa Passport + CRM module installed + permission, mantendo
 * o defense-in-depth do middleware auth:api ja aplicado no group routes/api.php.
 */
class StoreFollowUpRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = Auth::user();

        if ($user === null || empty($user->business_id)) {
            return false;
        }

        // Confere CRM module instalado pro business (chamada original em store()).
        $moduleUtil = app(ModuleUtil::class);

        return $moduleUtil->isModuleInstalled('Crm');
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
