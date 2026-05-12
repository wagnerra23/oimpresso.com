<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Http\Requests;

use App\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * GrantChannelUserRequest — valida grant de acesso a canal (US-WA-068, ADR 0135).
 *
 * Tier 0 IRREVOGÁVEL (ADR 0093) — `user_id` precisa ser do MESMO business
 * que o canal alvo. Validação cross-tenant via after() hook (após o exists
 * básico).
 *
 * Permission: `whatsapp.settings.manage` (mesma da CRUD de Channels).
 *
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-068
 */
class GrantChannelUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('whatsapp.settings.manage');
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }

    /**
     * Cross-tenant guard — user_id precisa estar no mesmo business + ter
     * permission básica de Whatsapp (`whatsapp.access` ou `whatsapp.send`).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $userId = (int) $this->input('user_id');
            if ($userId <= 0) {
                return;
            }

            $businessId = (int) session('user.business_id');
            $target = User::find($userId);

            if (! $target) {
                return; // exists rule já cobre
            }

            if ((int) $target->business_id !== $businessId) {
                $v->errors()->add(
                    'user_id',
                    'Usuário não pertence ao mesmo business.'
                );
                return;
            }

            $hasWhatsappPerm = $target->can('whatsapp.access')
                || $target->can('whatsapp.send');

            if (! $hasWhatsappPerm) {
                $v->errors()->add(
                    'user_id',
                    'Usuário precisa ter permissão whatsapp.access ou whatsapp.send.'
                );
            }
        });
    }
}
