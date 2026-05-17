<?php

declare(strict_types=1);

namespace Modules\TeamMcp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest pra UPDATE quota MCP de um user (TeamMcp).
 *
 * Wave 18 D8 SATURATION — extraido de TeamController::atualizarQuota (validate inline).
 *
 * **Permissão**: `copiloto.mcp.usage.all` (Wagner/superadmin).
 * **Tier 0 segredo**: nunca expor limit em log de erro de validação.
 *
 * Rules:
 *   - period: enum daily/monthly (allow-list, hardening)
 *   - limit_brl: numérico 0..9999.99 (cap defensivo contra typo overflow)
 *   - block_on_exceed: boolean opcional
 */
class UpdateQuotaRequest extends FormRequest
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

        return $user->can('copiloto.mcp.usage.all');
    }

    public function rules(): array
    {
        return [
            'period'          => ['required', 'in:daily,monthly'],
            'limit_brl'       => ['required', 'numeric', 'min:0', 'max:9999.99'],
            'block_on_exceed' => ['nullable', 'boolean'],
        ];
    }
}
