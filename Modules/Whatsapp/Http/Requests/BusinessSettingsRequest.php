<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * BusinessSettingsRequest — validação de Templates HSM + toggle Bot Jana.
 *
 * Drivers (Z-API / Meta Cloud / Baileys) migraram para Modules\Whatsapp\Channels
 * (US-WA-067 + ADR 0135). Esta request é stub temporário até US-WA-070.
 *
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-067
 */
class BusinessSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && method_exists($user, 'can')
            && $user->can('whatsapp.settings.manage');
    }

    public function rules(): array
    {
        return [
            'bot_enabled' => ['nullable', 'boolean'],
            'template_repair_ready_name' => ['nullable', 'string', 'max:64'],
            'template_repair_waiting_parts_name' => ['nullable', 'string', 'max:64'],
            'template_billing_due_name' => ['nullable', 'string', 'max:64'],
            'template_billing_paid_name' => ['nullable', 'string', 'max:64'],
        ];
    }
}
