<?php

declare(strict_types=1);

namespace Modules\SRS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest pra ChatController::ask.
 *
 * D8.c Security — pergunta de chat vai pra LLM externo quando AI habilitado
 * (custo $$$). Limites estritos: session_id 64 chars, question 2000 chars
 * (≈400 tokens, ~$0.001 GPT-4o-mini), module_context restrito a 64 chars.
 *
 * D7 LGPD — content da pergunta passa por PiiRedactor no ChatAssistant
 * antes de log/LLM (defense-in-depth).
 */
class ChatAskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'session_id'     => 'required|string|max:64|regex:/^[A-Za-z0-9_-]+$/',
            'question'       => 'required|string|min:3|max:2000',
            'module_context' => 'nullable|string|max:64|regex:/^[A-Za-z0-9_-]+$/',
        ];
    }

    public function messages(): array
    {
        return [
            'session_id.regex'     => 'session_id contém caracteres inválidos.',
            'question.min'         => 'Pergunta muito curta (mínimo 3 caracteres).',
            'question.max'         => 'Pergunta excede 2000 caracteres (controle de custo LLM).',
            'module_context.regex' => 'module_context contém caracteres inválidos.',
        ];
    }
}
