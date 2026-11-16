<?php

declare(strict_types=1);

namespace Modules\Superadmin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

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

    /**
     * O controller sempre gravou `Str::slug()` do que foi digitado ("Sobre Nós" vira "sobre-nos").
     * Normaliza ANTES de validar para a tela continuar aceitando texto livre; a regra abaixo
     * barra só o que não sobra nada depois de normalizado.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('slug')) {
            $this->merge(['slug' => Str::slug((string) $this->input('slug'))]);
        }
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
