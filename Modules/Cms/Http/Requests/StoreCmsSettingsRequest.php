<?php

declare(strict_types=1);

namespace Modules\Cms\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * StoreCmsSettingsRequest — D8.c Security Wave 17 Batch 1 (2026-05-16).
 *
 * Extrai validation rules de SettingsController@store (linhas 68-121).
 * Settings é operação ADMIN crítica (logo, analytics, tracking pixels, contact).
 *
 * Multi-tenant Tier 0 (ADR 0093): exige business_id na session; CmsSiteDetail
 * persiste vinculado ao business ativo (vide createOrUpdateSiteDetails).
 *
 * D7.a LGPD: campos contact_us/mail_us podem conter email/telefone — PiiRedactor
 * é aplicado em logs no controller, FormRequest valida só formato.
 */
class StoreCmsSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Admin do business — controller já valida permissões finas; aqui só
        // garantimos sessão web autenticada + business ativo.
        return $this->user() !== null
            && session('user.business_id') !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            // Logo upload — tamanho controlado por config('constants.document_size_limit').
            'logo'              => ['nullable', 'file', 'image', 'max:5120'],

            // Conteúdo textual livre.
            'faqs'              => ['nullable', 'string'],
            'statistics'        => ['nullable', 'string'],
            'meta_tags'         => ['nullable', 'string'],

            // Snippets injetados na página — risco XSS mitigado pelo Blade {!! !!}
            // intencional; admin tem confiança elevada. Limita tamanho razoável.
            'google_analytics'  => ['nullable', 'string', 'max:8000'],
            'fb_pixel'          => ['nullable', 'string', 'max:8000'],
            'custom_js'         => ['nullable', 'string', 'max:20000'],
            'custom_css'        => ['nullable', 'string', 'max:20000'],

            // Widgets / contatos (PII redactor aplica em logs no controller).
            'chat_widget'       => ['nullable', 'string', 'max:8000'],
            'contact_us'        => ['nullable', 'string'],
            'mail_us'           => ['nullable', 'string'],
            'follow_us'         => ['nullable', 'string'],
            'notifiable_email'  => ['nullable', 'string', 'max:500'],

            // Botões/chat estruturados.
            'btns'              => ['nullable'],
            'chat'              => ['nullable'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'logo.image'    => 'O logo deve ser um arquivo de imagem válido.',
            'logo.max'      => 'O logo não pode ultrapassar 5MB.',
            'custom_js.max' => 'O JS customizado excedeu o limite de 20.000 caracteres.',
            'custom_css.max'=> 'O CSS customizado excedeu o limite de 20.000 caracteres.',
        ];
    }
}
