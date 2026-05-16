<?php

declare(strict_types=1);

namespace Modules\Spreadsheet\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * D8.c Security Wave 17 — FormRequest extraído de SpreadsheetController@moveToFolder.
 *
 * Endpoint sensível: muda parent (folder_id) de uma planilha.
 * Sem validação, attacker autenticado em biz=X poderia tentar mover spreadsheet
 * pra folder de outro business (cross-tenant). Isolamento real fica no Service
 * (where business_id + created_by) — aqui validamos só tipos/presença.
 *
 * Multi-tenant Tier 0 ([ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)):
 * business_id sempre da sessão no Controller — nunca via payload.
 *
 * @see Modules\Spreadsheet\Http\Controllers\SpreadsheetController::moveToFolder
 */
class MoveToFolderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'spreadsheet_id'  => ['required', 'integer', 'exists:sheet_spreadsheets,id'],
            // move_to_folder = 0 significa "raiz" (sem folder).
            'move_to_folder'  => ['required', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'spreadsheet_id.required' => 'ID da planilha é obrigatório.',
            'spreadsheet_id.exists'   => 'Planilha não encontrada.',
            'move_to_folder.required' => 'Selecione a pasta de destino.',
        ];
    }
}
