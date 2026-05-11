<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Whatsapp\Entities\Channel;

/**
 * Validação de cadastro/atualização de Channel (ADR 0135).
 *
 * Regras per-type — config_json shape muda por tipo. Pra Fase 0 só os 3
 * tipos WhatsApp são funcionais; Insta/Email/ML aceitos pelo schema mas
 * salvam status='setup' (driver não-implementado lança em runtime).
 */
class ChannelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('whatsapp.settings.manage');
    }

    public function rules(): array
    {
        $type = $this->input('type');

        $rules = [
            'label' => ['required', 'string', 'max:80'],
            'type' => ['required', Rule::in(Channel::TYPES)],
            'config' => ['array'],
            'handles_repair_status' => ['boolean'],
            'handles_billing' => ['boolean'],
            'handles_jana_bot' => ['boolean'],
            'handles_outbound_default' => ['boolean'],
            'bot_enabled' => ['boolean'],
            'lgpd_acknowledged' => ['required_if:type,whatsapp_baileys', 'accepted_if:type,whatsapp_baileys'],
        ];

        // Per-type config validation
        switch ($type) {
            case Channel::TYPE_WHATSAPP_ZAPI:
                $rules['config.zapi_instance_id'] = ['required', 'string', 'max:64'];
                $rules['config.zapi_instance_token'] = ['required', 'string'];
                $rules['config.zapi_client_token'] = ['nullable', 'string'];
                break;

            case Channel::TYPE_WHATSAPP_META:
                $rules['config.meta_phone_number_id'] = ['required', 'string', 'max:64'];
                $rules['config.meta_access_token'] = ['required', 'string'];
                $rules['config.meta_app_secret'] = ['nullable', 'string'];
                $rules['config.meta_webhook_verify_token'] = ['nullable', 'string', 'max:64'];
                break;

            case Channel::TYPE_WHATSAPP_BAILEYS:
                $rules['config.baileys_phone_e164'] = ['required', 'string', 'regex:/^\+[1-9]\d{6,14}$/'];
                break;
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'config.baileys_phone_e164.regex' => 'Telefone deve estar no formato E.164 (ex: +5511987654321).',
            'lgpd_acknowledged.accepted_if' => 'Baileys exige aceite explícito do termo LGPD (driver não-oficial).',
        ];
    }
}
