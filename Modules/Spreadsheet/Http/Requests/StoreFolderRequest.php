<?php

declare(strict_types=1);

namespace Modules\Spreadsheet\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * D8.c Security Wave 17 — FormRequest extraído de SpreadsheetController@addFolder.
 *
 * Cria ou renomeia pasta (Category com category_type='spreadsheet').
 * Mesmo endpoint cobre create (sem folder_id) e update (com folder_id).
 *
 * Multi-tenant Tier 0: business_id vem da sessão; folder_id em update segue
 * filtrado por business no Controller.
 *
 * @see Modules\Spreadsheet\Http\Controllers\SpreadsheetController::addFolder
 */
class StoreFolderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name'      => ['required', 'string', 'max:191'],
            'folder_id' => ['nullable', 'integer', 'exists:categories,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nome da pasta é obrigatório.',
            'name.max'      => 'Nome da pasta deve ter no máximo 191 caracteres.',
        ];
    }
}
