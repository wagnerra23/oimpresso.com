<?php

declare(strict_types=1);

namespace Modules\Crm\Http\Requests;

use App\Utils\ModuleUtil;
use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest pra listagem /crm/leads — Wave 23 D8 Security saturation.
 *
 * Whitelist filtros UI/DataTables. Antes era $request->only(...) implícito;
 * agora rules() formaliza e bloqueia parâmetros maliciosos (SQLi via order_by
 * arbitrário, page > MAX_INT, etc).
 *
 * Multi-tenant Tier 0 (ADR 0093): NUNCA expor business_id em rules; scope
 * automático via session.
 */
class IndexLeadRequest extends FormRequest
{
    /** Status válidos pra crm_life_stage. */
    private const STAGE_WHITELIST = ['lead', 'opportunity', 'qualified', 'proposal', 'won', 'lost'];

    /** Colunas válidas pra ORDER BY (anti-SQLi). */
    private const ORDERABLE = ['created_at', 'updated_at', 'first_name', 'crm_life_stage', 'crm_source'];

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
        $stages = implode(',', self::STAGE_WHITELIST);
        $orderable = implode(',', self::ORDERABLE);

        return [
            'q'           => ['nullable', 'string', 'max:191'],
            'stage'       => ['nullable', 'string', "in:{$stages}"],
            'source'      => ['nullable', 'integer', 'min:0'],
            'assigned_to' => ['nullable', 'integer', 'min:0'],
            'date_from'   => ['nullable', 'date_format:Y-m-d'],
            'date_to'     => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'order_by'    => ['nullable', 'string', "in:{$orderable}"],
            'order_dir'   => ['nullable', 'string', 'in:asc,desc'],
            'per_page'    => ['nullable', 'integer', 'min:5', 'max:200'],
            'page'        => ['nullable', 'integer', 'min:1', 'max:10000'],
        ];
    }
}
