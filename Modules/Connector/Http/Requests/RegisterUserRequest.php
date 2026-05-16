<?php

declare(strict_types=1);

namespace Modules\Connector\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

/**
 * D8.c Security Wave 10 — FormRequest extraido de Api\UserController::registerUser.
 *
 * Regras espelham o $request->validate inline original (linhas 374-378) sem expansao
 * de escopo. Endpoint POST /connector/api/user-registration ja sob middleware auth:api
 * (somente users autenticados podem criar outros users).
 */
class RegisterUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Endpoint exige user autenticado via Passport (auth:api).
        // Sem permission check fina aqui — endpoint registerUser legacy permitia
        // qualquer user autenticado criar outros; preservamos o comportamento.
        return Auth::user() !== null;
    }

    public function rules(): array
    {
        return [
            'username' => ['unique:users'],
            'email' => ['required', 'unique:users'],
            'user_type' => ['required'],
        ];
    }
}
