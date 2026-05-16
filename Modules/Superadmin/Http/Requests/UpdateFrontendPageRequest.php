<?php

declare(strict_types=1);

namespace Modules\Superadmin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest — superadmin atualiza página frontend.
 *
 * D8 Wave 15 Security — extraído de PageController@update.
 *
 * @see Modules/Superadmin/Http/Controllers/PageController.php@update
 */
class UpdateFrontendPageRequest extends FormRequest
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
}
