<?php

namespace Modules\Woocommerce\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * UpdateApiSettingsRequest — validação payload de update das credenciais Woocommerce.
 *
 * Wave 10 D8 Security — endpoint admin que recebe tokens API + URLs Woocommerce.
 * NÃO é o webhook (esse continua usando Request raw pra preservar signature HMAC).
 *
 * Multi-tenant Tier 0 ([ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md)):
 * business_id NUNCA aceito do input — resolvido via session no Controller.
 *
 * Authorization: requer permission 'superadmin' OU 'woocommerce_module' subscription.
 * O Controller (WoocommerceController::updateSettings) já tem o gate; aqui só revalida
 * via authorize() pra defesa em profundidade.
 */
class UpdateApiSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Auth garantido por middleware 'auth' no group da rota.
        // Permissão fina (superadmin OR woocommerce_module) é validada no Controller
        // via $moduleUtil->hasThePermissionInSubscription.
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            // URL da loja Woocommerce
            'woocommerce_app_url'      => ['nullable', 'string', 'max:255', 'url'],

            // Consumer key/secret — tokens API (sensíveis, mas validar tamanho)
            'woocommerce_consumer_key'    => ['nullable', 'string', 'max:255'],
            'woocommerce_consumer_secret' => ['nullable', 'string', 'max:255'],

            // Webhook secrets — HMAC SHA256 keys (cada evento tem secret próprio)
            'woocommerce_wh_oc_secret' => ['nullable', 'string', 'max:255'],
            'woocommerce_wh_ou_secret' => ['nullable', 'string', 'max:255'],
            'woocommerce_wh_od_secret' => ['nullable', 'string', 'max:255'],
            'woocommerce_wh_or_secret' => ['nullable', 'string', 'max:255'],

            // Location ID — onde os pedidos sincronizados serão vinculados
            'location_id'              => ['nullable', 'integer', 'min:1'],

            // Status mapping — strings curtos identificando status interno
            'order_status_processing' => ['nullable', 'string', 'max:100'],
            'order_status_completed'  => ['nullable', 'string', 'max:100'],
            'order_status_on_hold'    => ['nullable', 'string', 'max:100'],
            'order_status_pending'    => ['nullable', 'string', 'max:100'],
            'order_status_cancelled'  => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'woocommerce_app_url.url' => 'A URL da loja Woocommerce precisa ser uma URL válida.',
        ];
    }
}
