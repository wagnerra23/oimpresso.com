<?php

namespace Modules\Crm\Http\Requests;

use App\Utils\ModuleUtil;
use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest pra criar follow-up (Schedule) no Crm.
 *
 * Extraido de ScheduleController@store (D8.c Security — Onda 3).
 * Antes: validação inline inexistente; abort(403) inline.
 * Agora: authorize() centraliza permissão + rules() formaliza contrato.
 *
 * Comportamento preservado: mesmas chaves aceitas (date/start_datetime/end_datetime/contact_id/etc.).
 * NUNCA fixar business_id em rules (multi-tenant scope automatico via session).
 */
class StoreScheduleRequest extends FormRequest
{
    /**
     * Apenas quem tem o modulo Crm habilitado na assinatura pode criar follow-up.
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
     * Regras minimas — datas opcionais (3 modos: simples/recursivo/advanced).
     * Preserva flexibilidade do ScheduleService->createFollowUp.
     */
    public function rules(): array
    {
        return [
            'start_datetime' => ['nullable', 'date'],
            'end_datetime' => ['nullable', 'date', 'after_or_equal:start_datetime'],
            'contact_id' => ['nullable', 'integer'],
            'contact_ids' => ['nullable', 'array'],
            'contact_ids.*' => ['integer'],
            'schedule_type' => ['nullable', 'string', 'max:191'],
            'status' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'is_recursive' => ['nullable', 'boolean'],
            'followup_category_id' => ['nullable', 'integer'],
            'follow_up_by' => ['nullable', 'string', 'max:50'],
            'schedule_for' => ['nullable', 'in:customer,lead'],
            'user_id' => ['nullable', 'array'],
            'user_id.*' => ['integer'],
        ];
    }
}
