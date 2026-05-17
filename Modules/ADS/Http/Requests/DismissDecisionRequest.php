<?php

declare(strict_types=1);

namespace Modules\ADS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * D8.c Security — Wave 18 saturação (meta 97 module-grade).
 *
 * FormRequest pra POST /ads/admin/decisoes/{id}/dismiss
 * (Admin\DecisoesController@dismiss). Wagner dispensa decisões blocked/não
 * acionáveis do Inbox; não muda outcome, só seta dismissed_at — item vai
 * pra Histórico.
 *
 * @see Modules\ADS\Http\Controllers\Admin\DecisoesController::dismiss
 */
class DismissDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }
}
