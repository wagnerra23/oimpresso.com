<?php

declare(strict_types=1);

namespace Modules\Crm\Http\Requests;

use App\Utils\ModuleUtil;
use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest pra listagem (index) de Proposals com filtros saneados.
 *
 * Wave 25 D8 polish — antes era query string crua no Controller. Anti-SQLi via
 * whitelist em `order_by`/`order_dir` + bounds em `page`/`per_page` (anti-DoS).
 *
 * Multi-tenant Tier 0 (ADR 0093): authorize() checa permissão crm_module + business_id session.
 *
 * @see Modules\Crm\Http\Controllers\ProposalController::index
 */
class IndexProposalRequest extends FormRequest
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
            'status'    => ['nullable', 'string', 'in:draft,sent,accepted,rejected,expired,canceled'],
            'contact_id' => ['nullable', 'integer', 'min:1'],
            'created_by' => ['nullable', 'integer', 'min:1'],
            'date_from' => ['nullable', 'date'],
            'date_to'   => ['nullable', 'date', 'after_or_equal:date_from'],
            'search'    => ['nullable', 'string', 'max:191'],
            'order_by'  => ['nullable', 'string', 'in:id,created_at,updated_at,total,status'],
            'order_dir' => ['nullable', 'string', 'in:asc,desc'],
            'page'      => ['nullable', 'integer', 'min:1', 'max:10000'],
            'per_page'  => ['nullable', 'integer', 'min:5', 'max:200'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.in'        => 'Status inválido. Valores aceitos: draft, sent, accepted, rejected, expired, canceled.',
            'order_by.in'      => 'Campo de ordenação inválido (whitelist anti-SQLi).',
            'order_dir.in'     => 'Direção inválida (asc ou desc).',
            'date_to.after_or_equal' => 'Data final deve ser igual ou posterior à inicial.',
        ];
    }
}
