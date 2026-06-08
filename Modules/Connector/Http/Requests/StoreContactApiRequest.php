<?php

declare(strict_types=1);

namespace Modules\Connector\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

/**
 * D8.c Security Wave S — FormRequest extraído de Api\ContactController::store.
 *
 * REST API REST endpoint protegido por Passport (`auth:api`); authorize() confirma
 * presença do user autenticado via guard padrão — request sem token já é barrado
 * pelo middleware antes mesmo de chegar aqui, mas mantemos check defensivo.
 *
 * Regras espelham o validate inline original — sem expansão de escopo.
 */
class StoreContactApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Passport auth check — middleware `auth:api` já bloqueia request sem token.
        // Defesa em profundidade: garante user resolvido + business_id presente.
        $user = Auth::user();

        return $user !== null && ! empty($user->business_id);
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required'],
            'type' => ['required', 'in:customer,supplier,both,lead'],
            'mobile' => ['required'],
            'supplier_business_name' => ['required_if:type,supplier'],
        ];
    }
}
