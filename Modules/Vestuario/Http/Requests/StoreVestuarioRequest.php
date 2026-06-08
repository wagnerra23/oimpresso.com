<?php

namespace Modules\Vestuario\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Esqueleto FormRequest pra Modules/Vestuario.
 *
 * Sprint 1: vertical-thin (apenas vestuario_settings). Não há endpoints
 * Store/Update de domínio ainda — este FormRequest é placeholder canônico
 * pra Sprint 2+ quando Vestuario ganhar telas próprias (ficha cliente
 * vestuário, medidas, preferências) conforme ADR 0105 (sinal qualificado).
 *
 * Padrão: ADR 0029 (FormRequest + Inertia) + multi-tenant Tier 0 ([ADR 0093]).
 *
 * @see memory/requisitos/Vestuario/SPEC.md
 * @see memory/requisitos/Vestuario/PII-LGPD.md
 */
class StoreVestuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Regras de validação.
     *
     * Sprint 1: vazio (vertical-thin). Sprint 2+ adicionar regras conforme
     * domínio de Vestuario evoluir. Lembrar de escopar `business_id` via
     * `$this->session()->get('user.business_id')` em Rule::unique.
     */
    public function rules(): array
    {
        return [
            // Sprint 2+ — adicionar regras quando endpoints Store reais existirem.
        ];
    }
}
