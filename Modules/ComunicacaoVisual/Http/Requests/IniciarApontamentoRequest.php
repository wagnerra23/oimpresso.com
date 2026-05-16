<?php

namespace Modules\ComunicacaoVisual\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * IniciarApontamentoRequest — validação payload de início de apontamento (Spool Plotter).
 *
 * Wave 10 D8 Security — Sprint 1 US-COMVIS-004.
 *
 * Multi-tenant Tier 0 ([ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md)):
 * business_id NUNCA aceito do input — resolvido via session no Controller.
 */
class IniciarApontamentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'orcamento_id'   => ['required', 'integer', 'min:1'],
            'orcamento_item_id' => ['required', 'integer', 'min:1'],
            'maquina_id'     => ['nullable', 'integer', 'min:1'],
            'operador_id'    => ['nullable', 'integer', 'min:1'],
            'observacoes'    => ['nullable', 'string', 'max:1000'],
        ];
    }
}
