<?php

declare(strict_types=1);

namespace Modules\Essentials\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * D8 Security Wave 15 — FormRequest extraído de EssentialsMessageController::store.
 *
 * Chat interno do tenant — limita mensagem a 5000 chars (preserva regra do Controller).
 * location_id opcional pra mensagens scopadas por loja física.
 */
class StoreMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && (bool) $this->user()->can('essentials.create_message');
    }

    public function rules(): array
    {
        return [
            'message'     => ['required', 'string', 'max:5000'],
            'location_id' => ['nullable', 'integer', 'exists:business_locations,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'message.required' => 'A mensagem não pode ficar em branco.',
            'message.max'      => 'A mensagem é muito longa (máx 5000 caracteres).',
        ];
    }
}
