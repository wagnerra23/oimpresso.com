<?php

declare(strict_types=1);

namespace Modules\RecurringBilling\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest pra pausar Subscription — Onda 3 v9,75 RecurringBilling.
 *
 * paused_until OPCIONAL — se vazio, pausa indefinida (cliente reativa manualmente).
 */
class PauseSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'paused_until' => ['nullable', 'date', 'after_or_equal:today'],
            'motivo'       => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'paused_until.after_or_equal' => 'Data de retomada não pode ser no passado.',
        ];
    }
}
