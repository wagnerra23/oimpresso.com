<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Whatsapp\Entities\WhatsappBusinessConfig;
use Modules\Whatsapp\Http\Requests\BusinessSettingsRequest;
use Modules\Whatsapp\Jobs\BaileysConnectJob;
use Modules\Whatsapp\Services\Centrifugo\CentrifugoTokenIssuer;

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
    public function show(CentrifugoTokenIssuer $tokenIssuer): Response
    {
        $businessId = (int) session('user.business_id');
        $userId = (int) (session('user.id') ?? auth()->id() ?? 0);
        $config = WhatsappBusinessConfig::where('business_id', $businessId)->first();

        // Tokens nunca vão pro frontend (only metadata + booleans pra UI saber estado)
        $configForUi = $config === null ? null : [
            'driver' => $config->driver,
            'fallback_driver' => $config->fallback_driver,
            'display_phone' => $config->display_phone,
            'driver_health' => $config->driver_health,
            'driver_health_consecutive_failures' => $config->driver_health_consecutive_failures,
            'last_health_check_at' => optional($config->last_health_check_at)->toIso8601String(),
            'last_health_message' => $config->last_health_message,
            'lgpd_acknowledged_at' => optional($config->lgpd_acknowledged_at)->toIso8601String(),
            'has_meta_credentials' => $config->hasMetaCloudConfigured(),
            'has_zapi_credentials' => ! empty($config->zapi_instance_id) && ! empty($config->zapi_instance_token),
            // US-WA-022: Baileys exige só phone E.164 cadastrado pelo tenant.
            // baileys_daemon_url + baileys_api_key são server secrets globais — NÃO expor.
            'has_baileys_credentials' => $config->hasBaileysConfigured(),
            'meta_phone_number_id' => $config->meta_phone_number_id, // não-secreto (só ID)
            'meta_webhook_verify_token' => $config->meta_webhook_verify_token, // não-secreto (só pra mostrar na UI)
            'zapi_instance_id' => $config->zapi_instance_id,
            'baileys_instance_id' => $config->baileys_instance_id, // auto-gerado, ok mostrar (read-only UI)
            'baileys_phone_e164' => $config->baileys_phone_e164,
            'baileys_verified_name' => $config->baileys_verified_name,
            'baileys_profile_pic_url' => $config->baileys_profile_pic_url,
            'bot_enabled' => (bool) $config->bot_enabled,
            'template_repair_ready_name' => $config->template_repair_ready_name,
            'template_repair_waiting_parts_name' => $config->template_repair_waiting_parts_name,
            'template_billing_due_name' => $config->template_billing_due_name,
            'template_billing_paid_name' => $config->template_billing_paid_name,
        ];

        $webhookUrls = $config !== null ? [
            'meta' => URL::to('/api/whatsapp/webhook/meta/' . $config->business_uuid),
            'zapi' => URL::to('/api/whatsapp/webhook/zapi/' . $config->business_uuid),
            'baileys' => URL::to('/api/whatsapp/webhook/baileys/' . $config->business_uuid),
        ] : null;

        // Centrifugo subscribe (US-WA-022 §Estado reativo) — UI Settings reage
        // a eventos baileys.qr_updated/connected/banned do daemon.
        $centrifugoConfig = null;
        if ($businessId > 0 && $userId > 0) {
            $channel = "whatsapp:business:{$businessId}";
            $token = $tokenIssuer->issue($userId, [$channel], (int) config('whatsapp.centrifugo.token_ttl_seconds', 3600));
            if ($token !== null) {
                $centrifugoConfig = [
                    'wsUrl' => config('whatsapp.centrifugo.ws_url'),
                    'token' => $token,
                    'channel' => $channel,
                ];
            }
        }

        return Inertia::render('Whatsapp/Settings', [
            'config' => $configForUi,
            'webhookUrls' => $webhookUrls,
            'forbiddenDrivers' => config('whatsapp.forbidden_drivers', ['evolution', 'whatsapp_web_js']),
            'mandatoryFallbackFor' => config('whatsapp.fallback.mandatory_for_drivers', ['zapi', 'baileys']),
            'centrifugoConfig' => $centrifugoConfig,
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

        // Atribui campos validados — encryption automática via Model casts.
        // US-WA-022: baileys_daemon_url/api_key removidos (server secrets globais);
        // baileys_instance_id é auto-gerado pelo BaileysConnectJob.
        $fields = [
            'driver', 'fallback_driver',
            'meta_phone_number_id', 'meta_access_token', 'meta_app_secret', 'meta_webhook_verify_token',
            'zapi_instance_id', 'zapi_instance_token', 'zapi_client_token',
            'baileys_phone_e164',
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

        // US-WA-022: dispara BaileysConnectJob quando driver=baileys, telefone
        // preenchido/mudou e LGPD aceito. Rate limit 3/dia/business (anti-abuse).
        if ($config->driver === 'baileys'
            && ! empty($config->baileys_phone_e164)
            && $config->lgpd_acknowledged_at !== null) {
            $this->maybeDispatchBaileysConnect($config);
        }

        return back()->with('status', 'Configuração Whatsapp salva. Driver ativo: ' . $config->driver . '.');
    }

    /**
     * Rate-limited dispatch do BaileysConnectJob.
     *
     * Limit: 3 connect/business/dia (config('whatsapp.baileys.connect_rate_limit_per_day')).
     * 4ª tentativa retorna 429 ValidationException pra UI tratar.
     */
    private function maybeDispatchBaileysConnect(WhatsappBusinessConfig $config): void
    {
        $limit = (int) config('whatsapp.baileys.connect_rate_limit_per_day', 3);
        $cacheKey = "whatsapp:baileys:connect:business:{$config->business_id}:" . now()->format('Y-m-d');

        $current = (int) Cache::get($cacheKey, 0);
        if ($current >= $limit) {
            throw ValidationException::withMessages([
                'baileys_phone_e164' => [
                    "Limite de {$limit} tentativas de conexão Baileys por dia atingido. "
                    . 'Aguarde até amanhã ou contate suporte.',
                ],
            ])->status(429);
        }

        Cache::put($cacheKey, $current + 1, now()->endOfDay());

        BaileysConnectJob::dispatch($config->business_id);
    }
}
