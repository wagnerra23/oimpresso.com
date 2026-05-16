<?php

namespace Modules\Cms\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest pra submissao de form publico de contato (CmsController@postContactForm).
 *
 * Endpoint POST /c/submit-contact-form — PUBLICO (sem auth), portanto:
 *   - authorize() libera geral
 *   - Honeypot field `_gotcha` (nome enganador pra bot) — se vier preenchido,
 *     rejeita silenciosamente como SPAM. Humano nao ve esse campo (CSS hidden).
 *   - Limites de tamanho conservadores anti-flood
 *   - email valida formato; mobile aceita BR (10-15 digitos com mascara)
 *
 * Anti-spam camadas adicionais (alem deste FormRequest):
 *   - middleware throttle (definido em routes/web.php)
 *   - reCAPTCHA (se config('cms.recaptcha.enabled'))
 *   - rate-limit por IP no Controller
 *
 * D8.c Security — Wave S Batch 2.
 *
 * @see Modules\Cms\Http\Controllers\CmsController::postContactForm
 */
class SubmitContactFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Endpoint publico — qualquer visitante pode submeter contato.
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:191'],
            'mobile' => ['nullable', 'string', 'max:40'],
            'message' => ['required', 'string', 'max:5000'],

            // Honeypot — campo invisivel via CSS. Bot preenche tudo, humano nao.
            // Se vier preenchido = SPAM. Deve estar SEMPRE vazio/ausente.
            '_gotcha' => ['nullable', 'string', 'size:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Informe seu nome.',
            'email.required' => 'Informe seu e-mail.',
            'email.email' => 'E-mail invalido.',
            'message.required' => 'Escreva sua mensagem.',
            'message.max' => 'Mensagem muito longa (max 5000 caracteres).',
            '_gotcha.size' => 'Submissao rejeitada.',
        ];
    }

    /**
     * Sanitiza inputs antes da validacao — trim + remove caracteres de controle.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : null,
            'email' => is_string($this->input('email')) ? trim(strtolower($this->input('email'))) : null,
            'mobile' => is_string($this->input('mobile')) ? trim($this->input('mobile')) : null,
            'message' => is_string($this->input('message')) ? trim($this->input('message')) : null,
        ]);
    }
}
