<?php

declare(strict_types=1);

namespace Modules\Connector\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * StoreOauthClientRequest — D8.c Security Wave 17 Batch 1 (2026-05-16).
 *
 * Extrai validation rules de ClientController@store (Passport OAuth client).
 *
 * IMPORTANTE: criar OAuth client é operação SUPERADMIN exclusiva (linha 63 do
 * controller: `auth()->user()->can('superadmin')`). authorize() abaixo replica
 * essa regra — fail-secure 403 antes do controller mesmo executar.
 *
 * Multi-tenant Tier 0 (ADR 0093): clients OAuth ficam em oauth_clients (tabela
 * Passport) com user_id; escopo cross-tenant é controlado por superadmin role.
 */
class StoreOauthClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        // Apenas superadmin pode emitir credenciais OAuth — alinha com a
        // verificação inline do ClientController e blinda mesmo se a checagem
        // do controller for futuramente removida.
        return $user !== null && $user->can('superadmin');
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:191'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'O nome do client OAuth é obrigatório.',
            'name.max'      => 'O nome do client não pode ultrapassar 191 caracteres.',
        ];
    }
}
