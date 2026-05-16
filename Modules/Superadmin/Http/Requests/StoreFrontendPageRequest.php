<?php

declare(strict_types=1);

namespace Modules\Superadmin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest — superadmin cria página frontend (CMS público).
 *
 * D8 Wave 15 Security — extraído de PageController@store (Request genérico).
 * Conteúdo CMS público — XSS-prevention via Blade escaping no view layer.
 *
 * @see Modules/Superadmin/Http/Controllers/PageController.php@store
 */
class StoreFrontendPageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        return $user->can('superadmin');
    }

    public function rules(): array
    {
        return [
            'title'       => ['required', 'string', 'max:255'],
            'slug'        => ['required', 'string', 'max:191', 'regex:/^[a-z0-9\-_]+$/i'],
            'content'     => ['nullable', 'string', 'max:65535'],
            'menu_order'  => ['nullable', 'integer', 'min:0'],
            'is_shown'    => ['nullable'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Informe o título da página.',
            'slug.required'  => 'Informe o slug da página.',
            'slug.regex'     => 'Slug deve conter apenas letras, números, hífen ou underscore.',
        ];
    }
}
