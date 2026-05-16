<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * StoreChartOfAccountRequest — D8.c Security Wave 17 Batch 1 (2026-05-16).
 *
 * Extrai validation rules de ChartOfAccountController@store (linhas 172-218)
 * preservando contrato exato. Plano de contas é entrada CONTÁBIL crítica
 * (gl_code unique, currency obrigatória, account_subtype obrigatório).
 *
 * Multi-tenant Tier 0 (ADR 0093): authorize() valida session('business.id').
 * Controller continua atribuindo business_id = session('business.id') no save.
 */
class StoreChartOfAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Accounting é módulo nucleo — auth web + business_id ativo na session.
        return $this->user() !== null
            && session('business.id') !== null;
    }

    /**
     * @return array<string, array<int, string|object>>
     */
    public function rules(): array
    {
        return [
            'name'               => ['required', 'string', 'max:191'],
            'gl_code'            => ['required', 'numeric', 'unique:chart_of_accounts,gl_code'],
            'currency_id'        => ['required', 'integer', 'exists:accounting_acc_currencies,id'],
            'opening_balance'    => ['sometimes', 'required', 'numeric'],
            'payment_type_id'    => ['sometimes', 'required'],
            'account_subtype_id' => ['required', 'integer', 'exists:accounting_acc_account_subtypes,id'],
            'detail_type_id'     => ['required', 'integer', 'exists:accounting_acc_account_detail_types,id'],
            'parent_id'          => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'account_type'       => ['nullable', 'string', 'max:80'],
            'allow_manual'       => ['nullable'],
            'active'             => ['nullable'],
            'notes'              => ['nullable', 'string', 'max:2000'],
            'date'               => ['nullable', 'date'],
            'reference'          => ['nullable', 'string', 'max:191'],
            'cheque_number'      => ['nullable', 'string', 'max:80'],
            'receipt'            => ['nullable', 'string', 'max:191'],
            'account_number'     => ['nullable', 'string', 'max:80'],
            'bank_name'          => ['nullable', 'string', 'max:191'],
            'routing_code'       => ['nullable', 'string', 'max:80'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required'               => 'O nome da conta é obrigatório.',
            'gl_code.required'            => 'O código GL é obrigatório.',
            'gl_code.unique'              => 'Já existe uma conta com este código GL.',
            'currency_id.required'        => 'Selecione uma moeda.',
            'account_subtype_id.required' => 'Selecione o subtipo da conta.',
            'detail_type_id.required'     => 'Selecione o tipo de detalhe da conta.',
        ];
    }
}
