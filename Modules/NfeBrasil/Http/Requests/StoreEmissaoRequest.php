<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * D8.c Security Wave S — FormRequest extraído de NfeEmissaoController::emitir.
 *
 * Preserva validação fiscal SEFAZ:
 *   - modelo: 55 (NFe B2B) ou 65 (NFC-e B2C) — único conjunto válido CONFAZ.
 *
 * NÃO relaxar regras — compliance fiscal. Multi-tenant guard segue no Controller
 * (business_id da sessão + cross-tenant guard via Transaction lookup).
 */
class StoreEmissaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Auth web stack já é aplicado via middleware. Aqui só valida sessão.
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'modelo' => ['nullable', 'string', Rule::in(['55', '65'])],
        ];
    }

    public function messages(): array
    {
        return [
            'modelo.in' => 'Modelo deve ser 55 (NFe) ou 65 (NFC-e).',
        ];
    }
}
