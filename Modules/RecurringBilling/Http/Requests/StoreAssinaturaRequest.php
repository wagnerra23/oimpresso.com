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
 *   - contact_id tem de existir na empresa da sessão e não estar excluído (Rule::exists
 *     filtrado por business_id abaixo). `App\Contact` não tem escopo global, então sem este
 *     filtro um id de outra empresa passava e a lista devolvia nome, documento e telefone
 *     desse cliente (auditoria RecurringBilling 2026-11-19, achado a1).
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
        $businessId = (int) session('user.business_id');

        return [
            'contact_id' => [
                'required',
                'integer',
                'min:1',
                Rule::exists('contacts', 'id')
                    ->where('business_id', $businessId)
                    ->whereNull('deleted_at'),
            ],
            'plan_id' => ['nullable', 'integer', 'min:1'],
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
            'contact_id.exists' => 'Cliente não encontrado nesta empresa.',
            'ciclo.in' => 'Ciclo deve ser mensal, trimestral, semestral ou anual.',
            'gateway.in' => 'Gateway suportado: asaas ou inter.',
            'forma_pagamento.in' => 'Forma de pagamento suportada: boleto, pix ou cartao.',
            'data_proxima_cobranca.after_or_equal' => 'A próxima cobrança não pode ser no passado.',
        ];
    }
}
