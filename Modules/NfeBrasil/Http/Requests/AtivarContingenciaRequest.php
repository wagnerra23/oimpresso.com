<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * US-NFE-006 / ADR TECH-0002 — ativação manual de contingência.
 *
 * Permissão `nfe.contingencia.manage` (a ADR nomeia esta chave). Fica no authorize()
 * como no resto do módulo (ver UpsertConfigDefaultRequest).
 */
class AtivarContingenciaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('nfe.contingencia.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            // min:10 pra barrar "teste"/"ok"/"xx": o campo existe pra ser lido por um
            // auditor meses depois. Limite 255 espelha a coluna.
            'motivo' => ['required', 'string', 'min:10', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'motivo.required' => 'Informe o motivo da contingência (exigência de auditoria fiscal).',
            'motivo.min' => 'Descreva o motivo com pelo menos 10 caracteres — ex: "SEFAZ-SC fora do ar desde 14h".',
        ];
    }
}
