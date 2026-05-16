<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * UpdateMonthlyBudgetRequest — D8.c Security Wave 17 Batch 2 (2026-05-16).
 *
 * Extrai validation rules de BudgetController@update_monthly_budget (linhas 67-85)
 * preservando contrato exato (inclui correção da pegadinha histórica em 'month_11'
 * onde regra 'numeric' estava deslocada do array — aqui consolidado).
 *
 * Multi-tenant Tier 0 (ADR 0093): authorize() valida session('business.id').
 * Controller continua usando session('business.id') como tenant scope no Budget::updateOrCreate.
 *
 * @see Modules/Accounting/Http/Controllers/BudgetController.php
 */
class UpdateMonthlyBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null
            && session('business.id') !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'chart_of_account_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
            'business_id'         => ['required', 'integer'],
            'financial_year'      => ['required', 'integer', 'min:2000', 'max:2100'],
            'month_1'             => ['required', 'numeric'],
            'month_2'             => ['required', 'numeric'],
            'month_3'             => ['required', 'numeric'],
            'month_4'             => ['required', 'numeric'],
            'month_5'             => ['required', 'numeric'],
            'month_6'             => ['required', 'numeric'],
            'month_7'             => ['required', 'numeric'],
            'month_8'             => ['required', 'numeric'],
            'month_9'             => ['required', 'numeric'],
            'month_10'            => ['required', 'numeric'],
            'month_11'            => ['required', 'numeric'],
            'month_12'            => ['required', 'numeric'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'chart_of_account_id.required' => 'Selecione a conta contábil do orçamento.',
            'financial_year.required'      => 'Informe o ano fiscal do orçamento.',
            'financial_year.min'           => 'Ano fiscal inválido (mínimo 2000).',
            'financial_year.max'           => 'Ano fiscal inválido (máximo 2100).',
        ];
    }
}
