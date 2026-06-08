<?php

namespace Modules\Spreadsheet\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest pra criar/importar Spreadsheet (SpreadsheetController@store).
 *
 * Wave 10 D8 — extraido de SpreadsheetController@store que pegava direto via $request->input(...).
 * Endpoint aceita payload sheet_data (JSON serializado da planilha) — sem limite vira DDoS vector.
 *
 * Multi-tenant: business_id e injetado pelo Controller a partir da session(), nunca pelo cliente.
 * Spreadsheet model aplica global scope per-business — confirmar em CapterraSeniorAudit.
 */
class StoreSpreadsheetRequest extends FormRequest
{
    public function authorize(): bool
    {
        // RBAC granular fica no Controller (permission spreadsheet_module + create.spreadsheet).
        // Aqui so confirmamos sessao autenticada.
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:191'],
            // sheet_data e JSON serializado de toda a planilha — limite generoso mas finito
            // (1MB) pra evitar payload bomb que estoura MySQL TEXT/JSON column.
            'sheet_data' => ['nullable', 'string', 'max:1048576'],
            'folder_id' => ['nullable', 'integer', 'exists:categories,id'],
        ];
    }
}
