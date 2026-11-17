<?php

declare(strict_types=1);

namespace Modules\ComunicacaoVisual\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * AprovarOrcamentoRequest — validação aprovação cliente de orçamento.
 *
 * Wave 18 D8 Security.
 *
 * Cobre POST /comunicacao-visual/api/orcamentos/{id}/aprovar, só com login. O aceite do cliente é outra
 * rota (OrcamentoAceitePublicoController). A permissão comvis.orcamento.create é conferida no controller.
 *
 * Multi-tenant Tier 0: o id é resolvido com o business_id da sessão.
 *
 * @see Modules/ComunicacaoVisual/Http/Controllers/OrcamentoController.php
 */
class AprovarOrcamentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
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
