<?php

namespace Modules\Cms\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest pra criar CmsPage (paginas estaticas / posts).
 *
 * Extraido de CmsPageController@store (D8.c Security — Onda 3).
 * Antes: nenhuma validacao; controller pegava direto via $request->only(...).
 * Agora: title obrigatorio, type whitelisted, feature_image validado como upload.
 */
class StoreCmsPageRequest extends FormRequest
{
    public function authorize(): bool
    {
        // CMS e modulo nucleo — quem tem sessao web autenticada pode publicar.
        // Validacao mais fina (permissoes) fica no Controller via session/policy.
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:191'],
            'content' => ['nullable', 'string'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'tags' => ['nullable', 'string', 'max:500'],
            'priority' => ['nullable', 'integer', 'min:0'],
            'type' => ['nullable', 'string', 'in:page,post,banner'],
            'feature_image' => ['nullable', 'file', 'image', 'max:5120'],
            'is_enabled' => ['nullable'],
        ];
    }
}
