<?php

declare(strict_types=1);

namespace Modules\RecurringBilling\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * D8.c Security Wave S — FormRequest baseline pra RecurringBillingController::store.
 *
 * Cobre cadastro de assinatura recorrente (boletos automáticos via Asaas/Inter).
 *
 * Multi-tenant Tier 0 (ADR 0093):
 *   - contact_id validado existindo no business da sessão (Rule\Exists scoped no Controller).
 *   - business_id NUNCA aceito via request — sempre da sessão.
 */
class StoreAssinaturaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'contact_id' => ['required', 'integer', 'min:1'],
            'valor' => ['required', 'numeric', 'min:0.01'],
            'ciclo' => ['required', 'string', Rule::in(['mensal', 'trimestral', 'semestral', 'anual'])],
            'data_proxima_cobranca' => ['required', 'date', 'after_or_equal:today'],
            'descricao' => ['nullable', 'string', 'max:255'],
            'gateway' => ['required', 'string', Rule::in(['asaas', 'inter'])],
            'forma_pagamento' => ['required', 'string', Rule::in(['boleto', 'pix', 'cartao'])],
            'ativa' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'ciclo.in' => 'Ciclo deve ser mensal, trimestral, semestral ou anual.',
            'gateway.in' => 'Gateway suportado: asaas ou inter.',
            'forma_pagamento.in' => 'Forma de pagamento suportada: boleto, pix ou cartao.',
            'data_proxima_cobranca.after_or_equal' => 'A próxima cobrança não pode ser no passado.',
        ];
    }
}
