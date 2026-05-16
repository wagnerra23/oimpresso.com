<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * UpdateQuarterlyBudgetRequest — D8.c Security Wave 17 Batch 2 (2026-05-16).
 *
 * Extrai validation rules de BudgetController@update_quarterly_budget (linhas 124-134)
 * preservando contrato exato. BudgetService quebra cada quarter em 3 meses
 * (com pegadinha eliminate_decimals — último mês recebe o resto).
 *
 * Multi-tenant Tier 0 (ADR 0093): authorize() valida session('business.id').
 */
class UpdateQuarterlyBudgetRequest extends FormRequest
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
            'quarter_1'           => ['required', 'numeric'],
            'quarter_2'           => ['required', 'numeric'],
            'quarter_3'           => ['required', 'numeric'],
            'quarter_4'           => ['required', 'numeric'],
            'eliminate_decimals'  => ['nullable'],
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
            'quarter_1.required'           => 'Informe o orçamento do 1º trimestre.',
            'quarter_2.required'           => 'Informe o orçamento do 2º trimestre.',
            'quarter_3.required'           => 'Informe o orçamento do 3º trimestre.',
            'quarter_4.required'           => 'Informe o orçamento do 4º trimestre.',
        ];
    }
}
