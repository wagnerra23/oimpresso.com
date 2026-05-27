<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * MetaEmbeddedCallbackRequest — valida payload do callback Embedded Signup v4.
 *
 * Fluxo: popup Facebook OAuth → frontend posta `code` + `state` aqui.
 * CSRF state é checado no Controller comparando com session — aqui só
 * validamos shape mínimo (anti-422 antes mesmo de cair no Controller).
 *
 * @see Modules\Whatsapp\Http\Controllers\Admin\SettingsController::metaEmbeddedCallback
 * @see memory/decisions/0202-whatsapp-profissionalizacao-baileys-out.md
 */
class MetaEmbeddedCallbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Auth via middleware na rota — chega aqui já autenticado.
        return true;
    }

    public function rules(): array
    {
        return [
            // OAuth code Facebook — strings opacas, length variável.
            // max=2000 é folga (Meta retorna ~200-400 chars na prática).
            'code' => ['required', 'string', 'min:10', 'max:2000'],

            // CSRF state — gerado por metaOauthInit (32 bytes hex = 64 chars).
            // size:64 confere comprimento exato; alpha_num bloqueia injeções.
            'state' => ['required', 'string', 'size:64', 'alpha_num'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Código OAuth Meta obrigatório.',
            'code.min' => 'Código OAuth Meta muito curto (provável tampering).',
            'state.required' => 'Token CSRF state obrigatório.',
            'state.size' => 'Token CSRF state com tamanho inválido (esperado 64 hex chars).',
            'state.alpha_num' => 'Token CSRF state com caracteres inválidos.',
        ];
    }
}
