<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * BusinessSettingsRequest — gating duro pra config Whatsapp.
 *
 * Decisão mãe: ADR 0096 (Z-API default + Meta Cloud fallback obrigatório).
 *
 * **Regras Tier 0 (irrevogáveis sem nova ADR):**
 *
 * 1. `driver=zapi|baileys` → exige `meta_*` campos preenchidos como fallback
 *    (R-WA-002b — ban Z-API joga pra Meta automaticamente).
 *
 * 2. `driver=zapi|baileys` → exige `lgpd_acknowledged_at` not null
 *    (termo LGPD obrigatório — ADR 0096 §Risco aceito conscientemente).
 *
 * 3. `driver=evolution|whatsapp_web_js` → 422 (PROIBIDO permanente —
 *    `config('whatsapp.forbidden_drivers')`).
 *
 * 4. Tokens cifrados pelo Model (encrypted cast) — request recebe
 *    plaintext, Model cifra ao salvar.
 *
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-001
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
        $forbidden = config('whatsapp.forbidden_drivers', ['evolution', 'whatsapp_web_js']);

        return [
            'driver' => [
                'required',
                'string',
                Rule::in(['zapi', 'meta_cloud', 'baileys', 'null']),
                Rule::notIn($forbidden),
            ],
            'fallback_driver' => [
                'nullable',
                'string',
                Rule::in(['meta_cloud', 'null']),
                Rule::notIn($forbidden),
            ],

            // Meta Cloud (sempre obrigatório quando driver=zapi/baileys, ou primário)
            'meta_phone_number_id' => ['nullable', 'string', 'max:64'],
            'meta_access_token' => ['nullable', 'string', 'min:50'],
            'meta_app_secret' => ['nullable', 'string', 'min:20'],
            'meta_webhook_verify_token' => ['nullable', 'string', 'min:8', 'max:64'],

            // Z-API
            'zapi_instance_id' => ['nullable', 'string', 'max:64'],
            'zapi_instance_token' => ['nullable', 'string', 'max:255'],
            'zapi_client_token' => ['nullable', 'string', 'max:255'],

            // Baileys (Sprint 3)
            'baileys_instance_id' => ['nullable', 'string', 'max:64'],
            'baileys_daemon_url' => ['nullable', 'url', 'max:255'],
            'baileys_api_key' => ['nullable', 'string', 'max:255'],

            // LGPD acknowledgment
            'lgpd_acknowledged' => ['nullable', 'boolean'],

            // Bot
            'bot_enabled' => ['nullable', 'boolean'],

            // Templates names (opcional)
            'template_repair_ready_name' => ['nullable', 'string', 'max:64'],
            'template_repair_waiting_parts_name' => ['nullable', 'string', 'max:64'],
            'template_billing_due_name' => ['nullable', 'string', 'max:64'],
            'template_billing_paid_name' => ['nullable', 'string', 'max:64'],
        ];
    }

    /**
     * Cross-field validation — gating duro Tier 0.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $driver = $this->input('driver');
            $mandatoryForFallback = config('whatsapp.fallback.mandatory_for_drivers', ['zapi', 'baileys']);
            $businessId = $this->hasSession() ? (int) $this->session()->get('user.business_id') : 0;
            $bypassBusinessIds = config('whatsapp.fallback.bypass_business_ids', []);
            $bypassMetaFallback = $businessId > 0 && in_array($businessId, $bypassBusinessIds, true);

            // Regra 1 — driver não-oficial exige Meta Cloud cadastrado como fallback
            // ADR 0111 (emenda 5 ao 0096): per-business bypass via lista env
            // (LGPD continua exigido; drivers proibidos continuam proibidos)
            if (in_array($driver, $mandatoryForFallback, true)) {
                if (! $bypassMetaFallback
                    && (empty($this->input('meta_phone_number_id'))
                        || empty($this->input('meta_access_token'))
                        || empty($this->input('meta_app_secret')))) {
                    $v->errors()->add(
                        'meta_phone_number_id',
                        "Driver '{$driver}' exige fallback Meta Cloud cadastrado (gating Tier 0 — ADR 0096). "
                        . 'Preencha meta_phone_number_id, meta_access_token e meta_app_secret.'
                    );
                }

                // Regra 2 — termo LGPD obrigatório (independe de bypass)
                if (! $this->boolean('lgpd_acknowledged')) {
                    $v->errors()->add(
                        'lgpd_acknowledged',
                        "Driver '{$driver}' é provedor não-oficial (Whatsapp Web). "
                        . 'É obrigatório aceitar termo LGPD ciente do risco de bloqueio Meta.'
                    );
                }
            }

            // Regra 3 — driver=meta_cloud exige meta_* preenchidos
            if ($driver === 'meta_cloud') {
                if (empty($this->input('meta_phone_number_id'))
                    || empty($this->input('meta_access_token'))) {
                    $v->errors()->add(
                        'meta_phone_number_id',
                        "Driver 'meta_cloud' exige meta_phone_number_id + meta_access_token cadastrados."
                    );
                }
            }

            // Regra 4 — driver=zapi exige zapi_* preenchidos
            if ($driver === 'zapi') {
                if (empty($this->input('zapi_instance_id'))
                    || empty($this->input('zapi_instance_token'))
                    || empty($this->input('zapi_client_token'))) {
                    $v->errors()->add(
                        'zapi_instance_id',
                        "Driver 'zapi' exige zapi_instance_id, zapi_instance_token e zapi_client_token cadastrados."
                    );
                }
            }

            // Regra 5 — driver=baileys exige baileys_* preenchidos (Sprint 3)
            if ($driver === 'baileys') {
                if (empty($this->input('baileys_instance_id'))
                    || empty($this->input('baileys_daemon_url'))
                    || empty($this->input('baileys_api_key'))) {
                    $v->errors()->add(
                        'baileys_instance_id',
                        "Driver 'baileys' exige baileys_instance_id, baileys_daemon_url e baileys_api_key cadastrados."
                    );
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'driver.in' => 'Driver inválido. Valores aceitos: zapi, meta_cloud, baileys, null.',
            'driver.not_in' => 'Driver proibido permanente (ADR 0096 emenda 4). Reabrir só via nova ADR.',
        ];
    }
}
