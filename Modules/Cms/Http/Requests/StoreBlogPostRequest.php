<?php

namespace Modules\Cms\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest pra criar Blog Post (CmsPage com type=blog).
 *
 * Extraido como subconjunto especializado de StoreCmsPageRequest (D8.c
 * Security — Wave S). Garante que type seja sempre 'blog' (whitelist
 * curta — mais restrito que StoreCmsPageRequest que aceita page/post/banner).
 *
 * CmsPageController@store hoje aceita StoreCmsPageRequest generico (ja
 * coberto Wave NO). Este FormRequest fica disponivel pra:
 *   1. Rotas /blog/store futuras (split do CmsPageController em Blog vs Page)
 *   2. CmsController@getBlogList → endpoint store equivalente
 *   3. API JSON-only de blog (D9 RESTful — backlog)
 *
 * @see Modules\Cms\Http\Requests\StoreCmsPageRequest (versao generica)
 * @see Modules\Cms\Http\Controllers\CmsController::getBlogList
 */
class StoreBlogPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        // CMS e modulo nucleo — quem tem sessao web autenticada pode publicar.
        // Validacao fina (permissoes 'cms.access_cms') fica no Controller via session/policy.
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
            // Mais restrito que StoreCmsPageRequest: blog post sempre type=blog.
            'type' => ['nullable', 'string', 'in:blog'],
            'feature_image' => ['nullable', 'file', 'image', 'max:5120'],
            'is_enabled' => ['nullable'],
        ];
    }

    /**
     * Garante type='blog' default mesmo se cliente nao mandar.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'type' => 'blog',
        ]);
    }
}
