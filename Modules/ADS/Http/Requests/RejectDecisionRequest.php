<?php

declare(strict_types=1);

namespace Modules\ADS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * D8.c Security — Wave 18 saturação (meta 97 module-grade).
 *
 * FormRequest pra POST /ads/admin/decisoes/{id}/reject
 * (Admin\DecisoesController@reject). Wagner clica "Rejeitar" no Inbox e
 * opcionalmente preenche razão — ConfidenceEngine usa razão pra aprender
 * (-2.0 score do par domain×event_type).
 *
 * Multi-tenant Tier 0 (ADR 0093): `business_id` resolvido da session upstream.
 * PII (Tier 0 IRREVOGÁVEL): `reason` é texto livre Wagner — proibido CPF/CNPJ
 * cliente real; validação max:2000 mitiga injection longa.
 *
 * @see Modules\ADS\Http\Controllers\Admin\DecisoesController::reject
 */
class RejectDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['sometimes', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.max' => 'Razão deve ter no máximo 2000 caracteres.',
        ];
    }
}
