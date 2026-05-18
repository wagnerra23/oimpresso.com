<?php

declare(strict_types=1);

namespace Modules\RecurringBilling\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * FormRequest pra cancelar Subscription — Onda 3 v9,75 RecurringBilling.
 *
 * Authorize delegado pro Controller (SubscriptionPolicy::cancel).
 */
class CancelSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'churn_reason' => ['required', 'string', Rule::in([
                'preço',
                'loja fechou',
                'inadimplência',
                'trocou fornecedor',
                'serviço insatisfatório',
                'outro',
            ])],
            'observacao' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'churn_reason.required' => 'Selecione o motivo do cancelamento.',
            'churn_reason.in'       => 'Motivo inválido.',
        ];
    }
}
