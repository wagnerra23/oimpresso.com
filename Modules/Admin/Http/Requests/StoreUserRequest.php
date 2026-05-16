<?php

namespace Modules\Admin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Esqueleto FormRequest pra criar Admin User.
 *
 * Wave S (D8.c Security): Modules/Admin nao tem UserController@store
 * implementado ainda (apenas IndexController + MutationsController +
 * FeatureFlagsController + DataController). Sprint Admin futuro tera
 * gestao de usuarios separada do core UltimatePOS (app/User), provavelmente
 * limitada a admin-center superadmins (tailscale-only + is-wagner middleware).
 *
 * Pattern canonico ja estabelecido em FormRequests existentes do projeto:
 *   - StoreCmsPageRequest (Modules/Cms)
 *   - StoreVestuarioRequest (Modules/Vestuario — skeleton igual)
 *
 * Tier 0 IRREVOGAVEL: NAO modificar tabela core `users` sem bridge table
 * (memory/proibicoes.md §"Codigo"). User aqui significa admin-center user,
 * NAO UltimatePOS users core.
 *
 * @see Modules\Admin\Http\Controllers\IndexController
 * @see memory/decisions/0122-admin-center-ct100.md
 */
class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Middleware stack ja garante tailscale-only + auth + is-wagner.
        return $this->user() !== null;
    }

    /**
     * Regras placeholder — Sprint Admin futuro vai concretizar.
     *
     * Notas pro Sprint futuro:
     *   - `business_id` NAO entra aqui (admin-center cross-business — escopa
     *     no Controller via session ou parametro explicito).
     *   - `email` precisa unique scoped por business_id quando user core; se
     *     for admin-center user separado, unique global em tabela propria.
     *   - PII (CPF/CNPJ): jamais loggar em log/PR (skill commit-discipline).
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:191'],
            'email' => ['required', 'email', 'max:191'],
            // 'password' validation: bcrypt + min:8 quando endpoint concreto existir.
        ];
    }
}
