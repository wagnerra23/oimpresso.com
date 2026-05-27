<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Whatsapp\Entities\WhatsappBusinessConfig;
use Modules\Whatsapp\Http\Requests\BusinessSettingsRequest;
use Modules\Whatsapp\Http\Requests\MetaEmbeddedCallbackRequest;
use Modules\Whatsapp\Services\Drivers\MetaCloudDriver;
use Throwable;

/**
 * SettingsController — Templates HSM + toggle Bot Jana + Embedded Signup v4.
 *
 * Drivers Z-API/Baileys legacy migraram pra Modules\Whatsapp\Channels (US-WA-067
 * + ADR 0135). Esta controller mantém apenas:
 *  - `show()` / `update()` — templates HSM + bot_enabled (US-WA-070)
 *  - `settings()` — render página principal `/whatsapp/settings` (US-WA-310)
 *  - `metaOauthInit()` — gera state CSRF + URL popup OAuth Meta (US-WA-310)
 *  - `metaEmbeddedCallback()` — recebe code → provisiona via driver (US-WA-310)
 *
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-067, US-WA-070, US-WA-310
 * @see memory/decisions/0202-whatsapp-profissionalizacao-baileys-out.md Fase 2
 */
class SettingsController extends Controller
{
    public function show(): Response
    {
        $businessId = (int) session('user.business_id');
        $config = WhatsappBusinessConfig::where('business_id', $businessId)->first();

        $configForUi = $config === null ? null : [
            'bot_enabled' => (bool) $config->bot_enabled,
            'template_repair_ready_name' => $config->template_repair_ready_name,
            'template_repair_waiting_parts_name' => $config->template_repair_waiting_parts_name,
            'template_billing_due_name' => $config->template_billing_due_name,
            'template_billing_paid_name' => $config->template_billing_paid_name,
        ];

        return Inertia::render('Atendimento/JanaTemplates', [
            'config' => $configForUi,
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

        $fields = [
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

        $config->save();

        return back()->with('status', 'Templates Jana salvos.');
    }

    /**
     * US-WA-310 — render página `/whatsapp/settings` (wizard Embedded Signup v4).
     *
     * Inertia page lê config atual + env vars Meta pra montar popup OAuth.
     * Multi-tenant Tier 0: filtra config por `session('user.business_id')`.
     */
    public function settings(Request $request): Response
    {
        $businessId = (int) $request->session()->get('user.business_id');
        $config = WhatsappBusinessConfig::where('business_id', $businessId)->first();

        $currentConfig = $config === null ? null : [
            'driver' => $config->driver,
            'display_phone' => $config->display_phone,
            'meta_waba_id' => $config->meta_waba_id ?? null,
            'driver_health' => $config->driver_health,
            'connected_at' => $config->last_health_check_at?->toIso8601String(),
        ];

        return Inertia::render('Whatsapp/Settings', [
            'currentConfig' => $currentConfig,
            'metaAppId' => (string) config('whatsapp.meta.app_id', ''),
            'metaBusinessConfigId' => (string) config('whatsapp.meta.business_config_id', ''),
            'metaGraphVersion' => (string) config('whatsapp.meta.api_version', 'v21.0'),
        ]);
    }

    /**
     * US-WA-310 — gera state CSRF (32 bytes hex = 64 chars) + URL do popup OAuth Meta.
     *
     * State é gravado em session e re-checado em `metaEmbeddedCallback` (defesa
     * CSRF — popup Facebook não tem acesso ao cookie da sessão Laravel via
     * postMessage, então passamos via JSON e validamos lookup server-side).
     *
     * URL do popup segue formato Embedded Signup v4:
     *   https://www.facebook.com/{version}/dialog/oauth?client_id={app_id}
     *     &config_id={config_id}&response_type=code&override_default_response_type=true
     *     &state={state}&redirect_uri={callback}&extras=...
     */
    public function metaOauthInit(Request $request): JsonResponse
    {
        $appId = (string) config('whatsapp.meta.app_id', '');
        $configId = (string) config('whatsapp.meta.business_config_id', '');
        $version = (string) config('whatsapp.meta.api_version', 'v21.0');

        if ($appId === '' || $configId === '') {
            return response()->json([
                'error' => 'meta_app_not_configured',
                'message' => 'META_APP_ID/META_BUSINESS_CONFIG_ID não configurados. '
                    .'Ver runbook onboarding-meta-cloud-embedded-signup.md.',
            ], 503);
        }

        $state = bin2hex(random_bytes(32)); // 64 chars hex
        $request->session()->put('whatsapp_oauth_state', $state);

        // Redirect URI = callback no nosso domínio. Meta exige cadastro no App
        // Dashboard antes (whitelist). Frontend pega code via postMessage
        // (response_type=code + override_default_response_type=true), então
        // o redirect_uri é só placeholder válido — não recebe redirect real.
        $redirectUri = url('/whatsapp/settings/meta-embedded-callback');

        $popupUrl = 'https://www.facebook.com/'.$version.'/dialog/oauth?'
            .http_build_query([
                'client_id' => $appId,
                'config_id' => $configId,
                'response_type' => 'code',
                'override_default_response_type' => 'true',
                'state' => $state,
                'redirect_uri' => $redirectUri,
            ]);

        return response()->json([
            'state' => $state,
            'url' => $popupUrl,
        ]);
    }

    /**
     * US-WA-310 — recebe `code` + `state` do popup OAuth → provisiona via MetaCloudDriver.
     *
     * Fluxo (multi-tenant Tier 0 ADR 0093):
     *  1. Valida CSRF state (session match)
     *  2. Chama driver.provisionViaEmbeddedSignup(code) → 4 calls Graph
     *  3. Persiste config cifrada (encrypted cast no Model)
     *  4. Log estruturado com phone PII-redacted (5 primeiros chars)
     *
     * Em caso de erro Meta (RuntimeException), retorna 500 com mensagem mascarada
     * + log completo internamente (Log::error com stack).
     *
     * @return JsonResponse 200 success | 422 csrf_mismatch | 500 provisioning_failed
     */
    public function metaEmbeddedCallback(
        MetaEmbeddedCallbackRequest $request,
        MetaCloudDriver $driver
    ): JsonResponse {
        $code = (string) $request->validated('code');
        $state = (string) $request->validated('state');

        // CSRF check — session state foi gerado em metaOauthInit
        $sessionState = $request->session()->pull('whatsapp_oauth_state'); // pull = delete
        if ($sessionState === null || ! hash_equals((string) $sessionState, $state)) {
            return response()->json([
                'error' => 'csrf_state_mismatch',
                'message' => 'Token CSRF state inválido ou expirado. Reinicie o fluxo.',
            ], 422);
        }

        $businessId = (int) $request->session()->get('user.business_id');
        if ($businessId <= 0) {
            return response()->json(['error' => 'no_business_session'], 401);
        }

        try {
            $data = $driver->provisionViaEmbeddedSignup($code);

            // Persist — multi-tenant Tier 0: HasBusinessScope global scope
            // garante isolamento. firstOrNew com business_id explícito faz
            // defesa em profundidade extra.
            $config = WhatsappBusinessConfig::firstOrNew(['business_id' => $businessId]);

            if (! $config->exists) {
                $config->business_id = $businessId;
                $config->business_uuid = Str::uuid()->toString();
            }

            $config->driver = 'meta_cloud';
            $config->fallback_driver = 'meta_cloud';
            $config->meta_phone_number_id = $data['phone_number_id'];
            $config->meta_waba_id = $data['waba_id'];
            $config->meta_access_token = $data['access_token']; // encrypted cast
            $config->display_phone = $data['display_phone'];
            $config->driver_health = 'healthy';
            $config->driver_health_consecutive_failures = 0;
            $config->last_health_check_at = now();
            $config->last_health_message = 'Embedded Signup v4 OK';
            $config->save();

            // Audit log estruturado — telefone redacted (PII).
            Log::info('whatsapp.embedded_signup.success', [
                'business_id' => $businessId,
                'waba_id' => $data['waba_id'],
                'phone_number_id' => $data['phone_number_id'],
                'display_phone_redacted' => $this->redactPhone($data['display_phone']),
                'business_name' => $data['business_name'],
            ]);

            return response()->json([
                'success' => true,
                'display_phone' => $data['display_phone'],
                'business_name' => $data['business_name'],
            ]);
        } catch (Throwable $e) {
            Log::error('whatsapp.embedded_signup.failed', [
                'business_id' => $businessId,
                'error_class' => $e::class,
                'error_message' => $e->getMessage(),
                'trace_hash' => substr(hash('sha256', $e->getTraceAsString()), 0, 16),
            ]);

            return response()->json([
                'error' => 'provisioning_failed',
                'message' => 'Erro ao conectar com Meta. Tente novamente em alguns segundos.',
                'debug_id' => substr(hash('sha256', $e->getMessage().$businessId.time()), 0, 12),
            ], 500);
        }
    }

    /**
     * Mascara telefone preservando 5 primeiros chars (DDI+DDD).
     * Ex: "+5548999999999" → "+5548...".
     */
    private function redactPhone(string $phone): string
    {
        if (mb_strlen($phone) <= 5) {
            return $phone;
        }

        return mb_substr($phone, 0, 5).'...';
    }
}
