<?php

declare(strict_types=1);

namespace Modules\Superadmin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest — superadmin troca senha de usuário arbitrário (cross-tenant intencional).
 *
 * D8.c Security Wave 13 — extraído de BusinessController@updatePassword.
 * Endpoint é SENSÍVEL (admin reseta senha de outro tenant) → exige:
 *   - authorize() → user.can('superadmin') (gate Spatie)
 *   - rules() → password mínima + user_id obrigatório
 *
 * Throttle 60/min aplicado em routes (RateLimiter 'superadmin').
 *
 * @see Modules/Superadmin/Http/Controllers/BusinessController.php@updatePassword
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md (cross-tenant intencional)
 */
class UpdateBusinessPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        // SUPERADMIN: gate único — somente quem tem permission `superadmin` pode resetar senha cross-tenant.
        return $user->can('superadmin');
    }

    public function rules(): array
    {
        return [
            'user_id'  => ['required', 'integer', 'exists:users,id'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required'  => 'Informe o usuário alvo.',
            'user_id.exists'    => 'Usuário não encontrado.',
            'password.required' => 'Informe a nova senha.',
            'password.min'      => 'Senha deve ter ao menos 8 caracteres.',
        ];
    }
}
