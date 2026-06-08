<?php

declare(strict_types=1);

namespace Modules\NFSe\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * D8.c Security Wave 17 — FormRequest extraído de NfseController::cancelar (US-NFSE-006).
 *
 * Cancela NFSe junto à prefeitura (ABRASF padrão nacional + variantes municipais).
 *
 * REGRAS FISCAIS ABRASF — NÃO RELAXAR:
 *   - motivo: 15..255 chars (alinhado com SEFAZ NFe Art. 14 SINIEF 07/2005 + ABRASF).
 *     Prefeituras na prática reaproveitam regra federal pra rastreabilidade fiscal.
 *
 * RBAC: gate `nfse.cancel` (espelhado do `$this->authorize('nfse.cancel')` original).
 *
 * Multi-tenant Tier 0: $nfse é route-model-binding → business_id já isolado via global scope.
 *
 * @see Modules\NFSe\Http\Controllers\NfseController::cancelar
 * @see Modules\NfeBrasil\Http\Requests\CancelarNfeRequest (pattern irmão NFe)
 */
class CancelarNfseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('nfse.cancel') ?? false;
    }

    public function rules(): array
    {
        return [
            'motivo' => ['required', 'string', 'min:15', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'motivo.required' => 'Motivo do cancelamento é obrigatório.',
            'motivo.min'      => 'Motivo ABRASF exige no mínimo 15 caracteres.',
            'motivo.max'      => 'Motivo ABRASF aceita no máximo 255 caracteres.',
        ];
    }
}
