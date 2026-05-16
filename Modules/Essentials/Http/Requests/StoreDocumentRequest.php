<?php

declare(strict_types=1);

namespace Modules\Essentials\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * D8 Security Wave 15 — FormRequest extraído de DocumentController::store.
 *
 * Documents tem 2 modos: 'document' (upload file) e 'memos' (texto puro).
 * Detecção via presença de 'body' segue idêntica ao Controller pra preservar paridade.
 *
 * MIME whitelist conservadora — upload de RG/CNH/comprovantes (LGPD PII alta).
 * Max 20MB alinhado ao Controller original.
 */
class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        // Modo memos: body presente → name é string textual
        if ($this->has('body')) {
            return [
                'name'        => ['required', 'string', 'max:255'],
                'body'        => ['required', 'string'],
                'description' => ['nullable', 'string', 'max:2000'],
            ];
        }

        // Modo document: upload de arquivo (PII alta — RG/CNH/contrato)
        return [
            'name'        => ['required', 'file', 'max:20480', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx,csv,txt,zip'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'    => 'Selecione um arquivo ou informe o nome do memorando.',
            'name.file'        => 'É preciso enviar um arquivo válido.',
            'name.max'         => 'Arquivo deve ter no máximo 20MB.',
            'name.mimes'       => 'Tipo de arquivo não permitido (use PDF, imagem, Office ou texto).',
            'body.required'    => 'O conteúdo do memorando é obrigatório.',
        ];
    }
}
