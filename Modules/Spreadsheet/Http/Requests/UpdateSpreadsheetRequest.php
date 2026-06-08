<?php

declare(strict_types=1);

namespace Modules\Spreadsheet\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * D8.c Security Wave 17 — FormRequest extraído de SpreadsheetController@update.
 *
 * O endpoint salva o estado completo da planilha (sheet_data como JSON serializado).
 * Sem limite explícito o payload pode estourar coluna TEXT/JSON do MySQL e/ou
 * exceder php.ini `post_max_size` virando vetor DDoS.
 *
 * Multi-tenant Tier 0: business_id segue lendo da sessão no Controller —
 * NUNCA aceitar via payload (ADR 0093).
 *
 * RBAC granular (permission `create.spreadsheet` ou `superadmin`) fica no Controller;
 * aqui validamos apenas autenticação básica + formato.
 *
 * @see Modules\Spreadsheet\Http\Controllers\SpreadsheetController::update
 * @see Modules\Spreadsheet\Http\Requests\StoreSpreadsheetRequest (pattern irmão)
 */
class UpdateSpreadsheetRequest extends FormRequest
{
    public function authorize(): bool
    {
        // RBAC fica no Controller. Aqui só confirma sessão autenticada.
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:191'],
            // sheet_data: JSON serializado completo. Limite 1MiB (mesmo do Store).
            // Aceitamos array ou string já encoded — Controller encoda se array.
            'sheet_data' => ['nullable'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.max' => 'Nome da planilha deve ter no máximo 191 caracteres.',
        ];
    }
}
