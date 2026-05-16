<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * D8.c Security Wave S — FormRequest extraído de NfeInutilizacaoController::store.
 *
 * Cancela/inutiliza faixa de números NFe junto à SEFAZ (CONFAZ SINIEF 07/2005 Art. 14).
 *
 * REGRAS FISCAIS SEFAZ — NÃO RELAXAR:
 *   - justificativa: ABRASF exige 15..255 chars.
 *   - modelo: 55 (NFe) ou 65 (NFC-e) — único enum válido.
 *   - serie: 1..3 chars (SEFAZ aceita 1-999).
 *   - numero_de / numero_ate: ints ≥ 1; range_check via withValidator.
 *
 * Multi-tenant Tier 0: business_id sempre da sessão no Controller.
 */
class CancelarNfeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('fiscal.inutilizar') ?? false;
    }

    public function rules(): array
    {
        return [
            'modelo' => ['required', 'string', Rule::in(['55', '65'])],
            'serie' => ['required', 'string', 'max:3'],
            'numero_de' => ['required', 'integer', 'min:1'],
            'numero_ate' => ['required', 'integer', 'min:1'],
            'justificativa' => ['required', 'string', 'min:15', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'modelo.in' => 'Modelo deve ser 55 (NFe) ou 65 (NFC-e).',
            'justificativa.min' => 'Justificativa SEFAZ exige no mínimo 15 caracteres.',
            'justificativa.max' => 'Justificativa SEFAZ aceita no máximo 255 caracteres.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $de = (int) $this->input('numero_de');
            $ate = (int) $this->input('numero_ate');
            if ($de > 0 && $ate > 0 && $ate < $de) {
                $v->errors()->add(
                    'numero_ate',
                    'numero_ate deve ser maior ou igual a numero_de.'
                );
            }
        });
    }
}
