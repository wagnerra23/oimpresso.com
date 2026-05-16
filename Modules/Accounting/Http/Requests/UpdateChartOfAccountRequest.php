<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * UpdateChartOfAccountRequest — D8.c Security Wave 17 Batch 1 (2026-05-16).
 *
 * Extrai validation rules de ChartOfAccountController@update (linhas 290-300).
 * Reusa pattern Store mas ignora UNIQUE no próprio id (Rule::unique ignore).
 */
class UpdateChartOfAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null
            && session('business.id') !== null;
    }

    /**
     * @return array<string, array<int, string|object>>
     */
    public function rules(): array
    {
        // Route binding pode usar {id} ou {chart_of_account}; pega o primeiro segmento numérico.
        $id = $this->route('id') ?? $this->route('chart_of_account');

        return [
            'name'               => ['required', 'string', 'max:191'],
            'gl_code'            => [
                'required',
                'numeric',
                Rule::unique('chart_of_accounts', 'gl_code')->ignore($id),
            ],
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
            'gl_code.unique'              => 'Já existe outra conta com este código GL.',
            'currency_id.required'        => 'Selecione uma moeda.',
            'account_subtype_id.required' => 'Selecione o subtipo da conta.',
            'detail_type_id.required'     => 'Selecione o tipo de detalhe da conta.',
        ];
    }
}
