<?php

namespace Modules\Cms\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest pra atualizar configuracoes do site CMS (SettingsController@store).
 *
 * Wave 10 D8 — extraido de SettingsController@store que pegava direto via $request->only(...).
 * Endpoint sensivel: salva custom_js/custom_css/google_analytics/fb_pixel/meta_tags injetados
 * no <head> do site publico — vetor classico de XSS persistente se nao validado.
 *
 * Multi-tenant: SettingsController ja le business_id da session() — model CmsSiteDetail tem global scope.
 */
class UpdateCmsSiteDetailsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Rota usa middleware 'superadmin' (ver Routes/web.php) — autorizacao no nivel da rota.
        // Aqui so confirmamos que ha sessao autenticada antes de aceitar payload.
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            // Snippets de codigo injetados no head/body — limite generoso mas finito.
            'custom_js' => ['nullable', 'string', 'max:65535'],
            'custom_css' => ['nullable', 'string', 'max:65535'],
            'google_analytics' => ['nullable', 'string', 'max:5000'],
            'fb_pixel' => ['nullable', 'string', 'max:5000'],
            'meta_tags' => ['nullable', 'string', 'max:5000'],
            'chat_widget' => ['nullable', 'string', 'max:10000'],

            // Conteudo estruturado.
            'faqs' => ['nullable', 'string', 'max:65535'],
            'statistics' => ['nullable', 'string', 'max:10000'],
            'contact_us' => ['nullable', 'string', 'max:5000'],
            'mail_us' => ['nullable', 'email', 'max:191'],
            'follow_us' => ['nullable', 'string', 'max:5000'],
            'notifiable_email' => ['nullable', 'email', 'max:191'],
            'btns' => ['nullable', 'string', 'max:10000'],
            'chat' => ['nullable', 'string', 'max:10000'],

            // Upload de logo — formato + tamanho.
            'logo' => ['nullable', 'file', 'image', 'max:5120'],
        ];
    }
}
