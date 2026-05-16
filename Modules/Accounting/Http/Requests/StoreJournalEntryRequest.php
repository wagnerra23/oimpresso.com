<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * StoreJournalEntryRequest — D8.c Security Wave 17 Batch 1 (2026-05-16).
 *
 * Extrai validation rules de JournalEntryController@store (linhas 170-204).
 * Entrada contábil dupla balanceada — array 'journal_entry_data' contém
 * {debit, credit, amount, notes} validado em runtime pelo JournalEntryService.
 *
 * Multi-tenant Tier 0 (ADR 0093): authorize() exige session business_id;
 * service confia em session('business.id') pra escopar criação.
 */
class StoreJournalEntryRequest extends FormRequest
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
            'location_id'        => ['required', 'integer'],
            'currency_id'        => ['required', 'integer', 'exists:accounting_acc_currencies,id'],
            'payment_type_id'    => ['nullable', 'integer'],
            'date'               => ['required', 'date'],
            // Estrutura: lista de linhas {debit, credit, amount, notes}.
            // Validação estrutural fina é responsabilidade do JournalEntryService
            // (regra contábil "soma débitos = soma créditos").
            'journal_entry_data' => ['required', 'array', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'location_id.required'        => 'Selecione o local da operação.',
            'currency_id.required'        => 'Selecione uma moeda.',
            'date.required'               => 'A data do lançamento é obrigatória.',
            'date.date'                   => 'Data inválida.',
            'journal_entry_data.required' => 'Informe pelo menos uma linha de débito/crédito.',
            'journal_entry_data.array'    => 'Estrutura de lançamento inválida.',
        ];
    }
}
