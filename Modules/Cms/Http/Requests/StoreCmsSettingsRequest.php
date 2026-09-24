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
            // ARRAYS, não strings (corrigido 2026-09-23, thread Cms/01 fase 4a): o formulário
            // sempre mandou `faqs[i][question]`, `statistics[content][i][stats]`, `contact_us[i][num]`…
            // A regra `string` recusava o payload inteiro — salvar os detalhes do site falhava
            // desde 2026-05-16 (prod: nenhuma chave gravada desde 2022, então ninguém viu).
            'faqs'              => ['nullable', 'array', 'max:20'],
            'faqs.*.question'   => ['nullable', 'string', 'max:2000'],
            'faqs.*.answer'     => ['nullable', 'string', 'max:5000'],
            'statistics'        => ['nullable', 'array'],
            'statistics.tagline' => ['nullable', 'string', 'max:500'],
            'statistics.description' => ['nullable', 'string', 'max:2000'],
            'statistics.content' => ['nullable', 'array', 'max:10'],
            'statistics.content.*.stats' => ['nullable', 'string', 'max:100'],
            'statistics.content.*.title' => ['nullable', 'string', 'max:200'],
            'meta_tags'         => ['nullable', 'string'],

            // Snippets injetados na página — risco XSS mitigado pelo Blade {!! !!}
            // intencional; admin tem confiança elevada. Limita tamanho razoável.
            'google_analytics'  => ['nullable', 'string', 'max:8000'],
            'fb_pixel'          => ['nullable', 'string', 'max:8000'],
            'custom_js'         => ['nullable', 'string', 'max:20000'],
            'custom_css'        => ['nullable', 'string', 'max:20000'],

            // Widgets / contatos (PII redactor aplica em logs no controller).
            'chat_widget'       => ['nullable', 'string', 'max:8000'],
            'contact_us'        => ['nullable', 'array', 'max:5'],
            'contact_us.*.label' => ['nullable', 'string', 'max:100'],
            'contact_us.*.num'  => ['nullable', 'string', 'max:30'],
            'mail_us'           => ['nullable', 'array', 'max:5'],
            'mail_us.*.label'   => ['nullable', 'string', 'max:100'],
            'mail_us.*.email'   => ['nullable', 'string', 'max:191'],
            'follow_us'         => ['nullable', 'array'],
            'follow_us.*'       => ['nullable', 'string', 'max:500'],
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
