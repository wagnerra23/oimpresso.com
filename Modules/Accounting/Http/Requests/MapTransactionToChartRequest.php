<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * MapTransactionToChartRequest — D8.c Security Wave 17 Batch 2 (2026-05-16).
 *
 * Extrai validation rules de AccountingTransactionController@map_to_chart_of_account
 * (linhas 102-110) preservando contrato exato.
 *
 * map_type: 'debit' ou 'credit' — define lado contábil.
 * mapping_for: 'purchase', 'sell', 'expense' — origem da transaction UltimatePOS
 *   que está sendo mapeada pra um chart_of_account contábil.
 *
 * Multi-tenant Tier 0 (ADR 0093): authorize() valida session('business.id').
 * Controller verifica transaction.business_id implicitamente via Transaction::find
 * com session scope core UltimatePOS.
 *
 * @see Modules/Accounting/Http/Controllers/AccountingTransactionController.php
 */
class MapTransactionToChartRequest extends FormRequest
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
        return [
            'map_type'            => ['required', Rule::in(['debit', 'credit'])],
            'mapping_for'         => ['required', Rule::in(['purchase', 'sell', 'expense'])],
            'chart_of_account_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
            'transaction_id'      => ['required', 'integer', 'exists:transactions,id'],
            'notes'               => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'map_type.required'            => 'Informe o lado contábil (débito/crédito).',
            'map_type.in'                  => 'Lado contábil inválido — use debit ou credit.',
            'mapping_for.required'         => 'Informe a origem da transação (purchase/sell/expense).',
            'mapping_for.in'               => 'Origem inválida — use purchase, sell ou expense.',
            'chart_of_account_id.required' => 'Selecione a conta contábil de destino.',
            'transaction_id.required'      => 'Transação não informada.',
            'transaction_id.exists'        => 'Transação não existe — pode ter sido removida.',
        ];
    }
}
