<?php

namespace Modules\Spreadsheet\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest pra compartilhar Spreadsheet (SpreadsheetController@postShareSpreadsheet).
 *
 * Wave 10 D8 — endpoint sensivel: define a quem (user/role/todo) a planilha sera exposta.
 * Sem validacao, attacker autenticado em biz=X poderia tentar shared_id de biz=Y (cross-tenant).
 *
 * Multi-tenant Tier 0: Controller DEVE checar que shared_id pertence ao mesmo business_id
 * que a planilha. Aqui validamos apenas tipo + presenca; isolamento e responsabilidade do Service.
 */
class ShareSpreadsheetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'spreadsheet_id' => ['required', 'integer', 'exists:sheet_spreadsheets,id'],
            // 3 tipos suportados pelo SpreadsheetShare: user, role, todo.
            'shared_with' => ['required', 'string', 'in:user,role,todo'],
            // Lista de IDs alvos do share — limite 100 pra evitar mass-share abuse.
            'shared_ids' => ['nullable', 'array', 'max:100'],
            'shared_ids.*' => ['integer', 'min:1'],
        ];
    }
}
