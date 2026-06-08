<?php

declare(strict_types=1);

namespace Modules\Connector\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * StoreExpenseApiRequest — Wave 18 D8.g.
 *
 * Extrai validation rules de `Connector\Api\ExpenseController::store` (POS
 * móvel registra despesa). Antes: validação inline com chance de drift entre
 * Controllers Web e API.
 *
 * Tier 0 (ADR 0093): business_id NÃO no input. Expense.business_id vem de
 * `$user->business_id` via Controller. expense_for (user_id) precisa ser do
 * mesmo business — Controller valida via `User::where('business_id', ...)`.
 */
class StoreExpenseApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'location_id'         => ['required', 'integer', 'min:1'],
            'expense_category_id' => ['nullable', 'integer', 'min:1'],
            'expense_for'         => ['nullable', 'integer', 'min:1'],
            'transaction_date'    => ['required', 'date'],
            'total_before_tax'    => ['required', 'numeric', 'min:0', 'max:99999999.9999'],
            'tax_id'              => ['nullable', 'integer', 'min:1'],
            'tax_amount'          => ['nullable', 'numeric', 'min:0'],
            'final_total'         => ['required', 'numeric', 'min:0', 'max:99999999.9999'],
            'expense_sub_category_id' => ['nullable', 'integer', 'min:1'],
            'additional_notes'    => ['nullable', 'string', 'max:1000'],
            'is_recurring'        => ['nullable', 'boolean'],
            'recur_interval'      => ['nullable', 'integer', 'min:1', 'max:365'],
            'recur_interval_type' => ['nullable', 'string', 'in:days,months,years'],
            'recur_repetitions'   => ['nullable', 'integer', 'min:0', 'max:1000'],
            'payment'             => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'transaction_date.required' => 'A data da despesa é obrigatória.',
            'final_total.required'      => 'O valor total final é obrigatório.',
            'recur_interval_type.in'    => 'O intervalo deve ser days, months ou years.',
        ];
    }
}
