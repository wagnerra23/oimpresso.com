<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
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

    /**
     * Validação cross-business: telefone/identificador único entre TODOS os channels
     * de todos os businesses (não só do business atual).
     *
     * Motivo: dois channels com mesmo `display_identifier` em business diferentes
     * fazem o daemon Baileys disputar a mesma sessão WhatsApp Web — gera
     * `stream:error conflict type="replaced"` em loop e acelera detecção de ban
     * pela Meta. Incidente real 2026-05-13: channels id=2 (biz=1) e id=4 (biz=164)
     * compartilhavam `554888782087` → 99min de loop até purge manual.
     *
     * Para fazer essa checagem precisamos by-passar o `HasBusinessScope` global
     * scope — feito via `Channel::query()->withoutGlobalScopes()` (caso superadmin
     * de plataforma, multi-tenant Tier 0 IRREVOGÁVEL ADR 0093).
     *
     * @see memory/sessions/2026-05-13-whatsapp-incident-zombie-banned-loop.md (Gap B)
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $type = $this->input('type');
            $identifier = $this->extractIdentifier($type);

            if ($identifier === null || $identifier === '') {
                return;
            }

            $normalized = $this->normalizeIdentifier($identifier);
            $channelId = $this->route('channel') ?? $this->route('id'); // edit ignora self

            $exists = Channel::query()
                ->withoutGlobalScopes()
                ->whereIn('type', [
                    Channel::TYPE_WHATSAPP_META,
                    Channel::TYPE_WHATSAPP_ZAPI,
                    Channel::TYPE_WHATSAPP_BAILEYS,
                ])
                ->where(function ($q) use ($normalized, $identifier) {
                    // Compara tanto formato cru quanto normalizado (sem '+')
                    $q->where('display_identifier', $identifier)
                      ->orWhere('display_identifier', $normalized);
                })
                ->when($channelId, fn ($q) => $q->where('id', '!=', $channelId))
                ->exists();

            if ($exists) {
                $field = match ($type) {
                    Channel::TYPE_WHATSAPP_BAILEYS => 'config.baileys_phone_e164',
                    Channel::TYPE_WHATSAPP_ZAPI => 'config.zapi_instance_id',
                    Channel::TYPE_WHATSAPP_META => 'config.meta_phone_number_id',
                    default => 'config',
                };

                $v->errors()->add(
                    $field,
                    'Este identificador já está cadastrado em outro canal (possivelmente em outro negócio). '
                    . 'Pareamento duplicado dispara conflict "replaced" no WhatsApp e acelera ban Meta.'
                );
            }
        });
    }

    /**
     * Extrai display_identifier do payload conforme type.
     */
    private function extractIdentifier(?string $type): ?string
    {
        return match ($type) {
            Channel::TYPE_WHATSAPP_BAILEYS => $this->input('config.baileys_phone_e164'),
            Channel::TYPE_WHATSAPP_ZAPI => $this->input('config.zapi_instance_id'),
            Channel::TYPE_WHATSAPP_META => $this->input('config.meta_phone_number_id'),
            default => null,
        };
    }

    /**
     * Normaliza identifier: remove '+' inicial pra telefones E.164.
     * Z-API instance_id e Meta phone_number_id são opacos — retorna como está.
     */
    private function normalizeIdentifier(string $identifier): string
    {
        return ltrim($identifier, '+');
    }
}
