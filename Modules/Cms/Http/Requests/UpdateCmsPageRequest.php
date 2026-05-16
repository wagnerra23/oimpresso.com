<?php

declare(strict_types=1);

namespace Modules\Cms\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * UpdateCmsPageRequest — D8.c Security Wave 17 Batch 1 (2026-05-16).
 *
 * Complementa StoreCmsPageRequest (já existente) cobrindo o método update
 * de CmsPageController. Mesmo contrato de campos; permite parciais via 'sometimes'.
 *
 * Multi-tenant Tier 0 (ADR 0093): CMS é nucleo, sessão web autenticada já
 * garante escopo de business pela política do controller.
 */
class UpdateCmsPageRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Mesma regra do StoreCmsPageRequest: sessão web autenticada.
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'title'            => ['sometimes', 'required', 'string', 'max:191'],
            'content'          => ['nullable', 'string'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'tags'             => ['nullable', 'string', 'max:500'],
            'priority'         => ['nullable', 'integer', 'min:0'],
            'type'             => ['nullable', 'string', 'in:page,post,banner'],
            'feature_image'    => ['nullable', 'file', 'image', 'max:5120'],
            'is_enabled'       => ['nullable'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required'      => 'O título da página é obrigatório.',
            'type.in'             => 'Tipo inválido. Use page, post ou banner.',
            'feature_image.image' => 'A imagem em destaque deve ser um arquivo de imagem válido.',
            'feature_image.max'   => 'A imagem em destaque não pode ultrapassar 5MB.',
        ];
    }
}
