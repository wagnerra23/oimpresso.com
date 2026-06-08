<?php

declare(strict_types=1);

namespace Modules\RecurringBilling\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * FIN-004 — Update parcial de cobranca recorrente (valor / ciclo / forma_pagamento).
 *
 * Diferenca vs StoreAssinaturaRequest: TODOS os campos sao optional (PATCH-like).
 * Pelo menos um campo obrigatorio — validacao adicional no Service
 * (atualizarCobrancaAssinatura retorna 422 se nenhum campo presente).
 *
 * Multi-tenant Tier 0 (ADR 0093): business_id NUNCA aceito via request;
 * sempre derivado de session('business.id') no Controller.
 *
 * Permissao: checada no Controller (recurringbilling.assinatura.update).
 */
class UpdateAssinaturaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'valor' => ['sometimes', 'numeric', 'min:0.01'],
            'ciclo' => ['sometimes', 'string', Rule::in(['mensal', 'trimestral', 'semestral', 'anual'])],
            'forma_pagamento' => ['sometimes', 'string', Rule::in(['boleto', 'pix', 'cartao'])],
        ];
    }

    public function messages(): array
    {
        return [
            'ciclo.in' => 'Ciclo deve ser mensal, trimestral, semestral ou anual.',
            'forma_pagamento.in' => 'Forma de pagamento suportada: boleto, pix ou cartao.',
            'valor.min' => 'Valor deve ser maior que zero.',
        ];
    }
}
