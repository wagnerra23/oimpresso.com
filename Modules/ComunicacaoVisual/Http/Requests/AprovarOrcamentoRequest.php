<?php

declare(strict_types=1);

namespace Modules\ComunicacaoVisual\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * AprovarOrcamentoRequest — validação aprovação cliente de orçamento.
 *
 * Wave 18 D8 Security.
 *
 * Cobre POST /com-visual/orcamentos/{id}/aprovar (interno) e
 * POST /com-visual/aprovacao-publica/{token}/aprovar (cliente externo).
 *
 * Multi-tenant Tier 0: id resolvido por global scope quando rota interna;
 * token público resolve business_id via lookup separado (não aceita input).
 *
 * @see Modules/ComunicacaoVisual/Http/Controllers/OrcamentoController.php
 */
class AprovarOrcamentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Aceita auth interna OU token público válido (Controller valida).
        return auth()->check() || $this->route('token') !== null;
    }

    public function rules(): array
    {
        return [
            // Nome quem aprovou (cliente externo precisa identificar)
            'aprovado_por' => ['nullable', 'string', 'max:120'],

            // Observações livres
            'observacoes'  => ['nullable', 'string', 'max:1000'],

            // Aceite termos (cliente externo)
            'aceite_termos' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'aprovado_por.max' => 'Nome do aprovador limitado a 120 caracteres.',
            'observacoes.max'  => 'Observações limitadas a 1000 caracteres.',
        ];
    }
}
