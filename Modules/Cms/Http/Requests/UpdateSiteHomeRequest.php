<?php

namespace Modules\Cms\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest pra atualizar Site/Home payload (CmsSiteDetail).
 *
 * Extraido de SettingsController@store (D8.c Security — Wave S).
 * Antes: SettingsController@store pegava direto via $request->only([...]) sem
 * validacao de URL/email/tipo.
 *
 * Cobre o conjunto de chaves persistidas em CmsSiteDetail::createOrUpdateSiteDetails
 * que alimentam Pages/Site/Home.tsx via SiteContentService::getHomePayload().
 *
 * Campos sao opcionais (nullable) porque settings podem ser preenchidos parcialmente
 * — admin atualiza so o que mudou. Validacao reforca formato quando enviado.
 *
 * @see Modules\Cms\Http\Controllers\SettingsController::store
 * @see Modules\Cms\Services\SiteContentService::getHomePayload
 * @see Modules\Cms\Entities\CmsSiteDetail
 */
class UpdateSiteHomeRequest extends FormRequest
{
    public function authorize(): bool
    {
        // CMS settings — quem tem sessao web autenticada acessa. Permission
        // 'cms.access_cms_settings' validada no Controller/middleware.
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            // Conteudo institucional (JSON-encoded ao salvar via CmsSiteDetail).
            'faqs' => ['nullable', 'string'],
            'statistics' => ['nullable', 'string'],
            'btns' => ['nullable', 'string'],
            'chat' => ['nullable', 'string'],
            'meta_tags' => ['nullable', 'string'],

            // Tracking / analytics.
            'google_analytics' => ['nullable', 'string', 'max:5000'],
            'fb_pixel' => ['nullable', 'string', 'max:5000'],
            'custom_js' => ['nullable', 'string', 'max:10000'],
            'custom_css' => ['nullable', 'string', 'max:10000'],
            'chat_widget' => ['nullable', 'string', 'max:5000'],

            // Contato.
            'contact_us' => ['nullable', 'string', 'max:5000'],
            'mail_us' => ['nullable', 'string', 'max:5000'],
            'follow_us' => ['nullable', 'string', 'max:5000'],
            'notifiable_email' => ['nullable', 'email', 'max:191'],

            // Logo upload (mesma regra de feature_image em StoreCmsPageRequest).
            'logo' => ['nullable', 'file', 'image', 'max:5120'],
        ];
    }
}
