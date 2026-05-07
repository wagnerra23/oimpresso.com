<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Modules\Whatsapp\Entities\WhatsappBusinessConfig;
use Modules\Whatsapp\Http\Requests\BusinessSettingsRequest;

/**
 * SettingsController — wizard 2 passos Z-API + Meta Cloud (US-WA-001).
 *
 * Decisão mãe: ADR 0096.
 *
 * **Gating duro (FormRequest BusinessSettingsRequest):**
 * - driver=zapi/baileys → exige meta_* preenchidos (fallback obrigatório)
 *   E lgpd_acknowledged=true
 * - driver=evolution → 422 ValidationException (PROIBIDO permanente)
 *
 * Tokens cifrados pelo Model (encrypted cast).
 *
 * UI Inertia/React fica pra Lote 2e — show() retorna placeholder Blade.
 *
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-001
 */
class SettingsController extends Controller
{
    public function show()
    {
        $businessId = (int) session('user.business_id');
        $config = WhatsappBusinessConfig::where('business_id', $businessId)->first();

        return view('whatsapp::placeholder', [
            'titulo' => 'Configurações Whatsapp',
            'mensagem' => $config === null
                ? 'Nenhuma config Whatsapp cadastrada — wizard Inertia/React em Lote 2e.'
                : "Driver atual: {$config->driver}. Health: {$config->driver_health}.",
            'webhook_meta' => $config ? URL::to('/api/whatsapp/webhook/meta/' . $config->business_uuid) : null,
            'webhook_zapi' => $config ? URL::to('/api/whatsapp/webhook/zapi/' . $config->business_uuid) : null,
        ]);
    }

    public function update(BusinessSettingsRequest $request): RedirectResponse
    {
        $businessId = (int) $request->session()->get('user.business_id');
        $validated = $request->validated();

        $config = WhatsappBusinessConfig::firstOrNew(['business_id' => $businessId]);

        if (! $config->exists) {
            $config->business_id = $businessId;
            $config->business_uuid = Str::uuid()->toString();
        }

        // Atribui campos validados — encryption automática via Model casts
        $fields = [
            'driver', 'fallback_driver',
            'meta_phone_number_id', 'meta_access_token', 'meta_app_secret', 'meta_webhook_verify_token',
            'zapi_instance_id', 'zapi_instance_token', 'zapi_client_token',
            'baileys_instance_id', 'baileys_daemon_url', 'baileys_api_key',
            'bot_enabled',
            'template_repair_ready_name',
            'template_repair_waiting_parts_name',
            'template_billing_due_name',
            'template_billing_paid_name',
        ];

        foreach ($fields as $field) {
            if (array_key_exists($field, $validated)) {
                $config->{$field} = $validated[$field];
            }
        }

        // Termo LGPD obrigatório quando driver=zapi/baileys
        $mandatoryForFallback = config('whatsapp.fallback.mandatory_for_drivers', ['zapi', 'baileys']);
        if (in_array($validated['driver'], $mandatoryForFallback, true)
            && (bool) ($validated['lgpd_acknowledged'] ?? false)) {
            $config->lgpd_acknowledged_at = now();
            $config->lgpd_acknowledged_by_user_id = $request->user()?->id;
        }

        $config->save();

        return back()->with('status', 'Configuração Whatsapp salva. Driver ativo: ' . $config->driver . '.');
    }
}
