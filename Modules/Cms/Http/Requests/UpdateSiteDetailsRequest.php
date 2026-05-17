<?php

declare(strict_types=1);

namespace Modules\Cms\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * UpdateSiteDetailsRequest — D8.c Security Wave 27 (2026-05-17).
 *
 * Variante slim de `UpdateCmsSiteDetailsRequest` (Wave 10) focada em endpoints
 * REST/JSON que atualizam APENAS campos textuais (sem upload de logo). Útil
 * pra ações administrativas via API interna (CT 100 → MCP server) onde
 * `multipart/form-data` é inconveniente.
 *
 * Diferenças face `UpdateCmsSiteDetailsRequest`:
 *  - SEM `logo` (upload fica isolado em rota dedicada `POST /admin/site/logo`)
 *  - SEM `meta_tags`/`google_analytics`/`fb_pixel` (mantidos só no full request
 *    que tem fluxo de aprovação superadmin com warning XSS)
 *  - Apenas textos curtos editáveis pela equipe (chat widget, contact_us, etc)
 *
 * Multi-tenant Tier 0 (ADR 0093): CmsSiteDetail tem global scope `business_id`
 * herdado — controller chama `CmsSiteDetail::create([...])` que stampa biz
 * automaticamente via sessão.
 *
 * @see Modules\Cms\Http\Requests\UpdateCmsSiteDetailsRequest (full payload)
 * @see Modules\Cms\Entities\CmsSiteDetail
 */
class UpdateSiteDetailsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Rota destino deve ter middleware `superadmin` ou policy específica.
        // Aqui só confirmamos sessão autenticada antes de aceitar payload.
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            // Contatos públicos do site.
            'contact_us'       => ['nullable', 'string', 'max:5000'],
            'mail_us'          => ['nullable', 'email', 'max:191'],
            'follow_us'        => ['nullable', 'string', 'max:5000'],
            'notifiable_email' => ['nullable', 'email', 'max:191'],

            // Conteúdos estruturados rápidos.
            'chat_widget'      => ['nullable', 'string', 'max:10000'],
            'btns'             => ['nullable', 'string', 'max:10000'],
            'chat'             => ['nullable', 'string', 'max:10000'],
            'statistics'       => ['nullable', 'string', 'max:10000'],
            'faqs'             => ['nullable', 'string', 'max:65535'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'mail_us.email'          => 'O e-mail de contato é inválido.',
            'notifiable_email.email' => 'O e-mail pra notificações de lead é inválido.',
        ];
    }
}
