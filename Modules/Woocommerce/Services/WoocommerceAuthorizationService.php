<?php

namespace Modules\Woocommerce\Services;

use App\Util\OtelHelper;
use App\Utils\ModuleUtil;

/**
 * WoocommerceAuthorizationService — autorização central do módulo.
 *
 * Centraliza checks duplicados em 9+ métodos dos Controllers:
 *   - superadmin OR (subscription `woocommerce_module` + permission por ação)
 *
 * Extração D4 Wave 16 governance v3 — antes lógica ficava inline:
 *   if (! (auth()->user()->can('superadmin') || ($this->moduleUtil->hasThePermissionInSubscription(...) && auth()->user()->can(...))))
 *
 * Pattern: Service stateless puro — receber `business_id` + permission key,
 * decidir + abortar 403 quando necessário. Sem session() interna (job-safe).
 *
 * Multi-tenant Tier 0 ([ADR 0093]): `business_id` SEMPRE explícito (assinatura).
 * Nunca lê de `session()` aqui — Controller injeta.
 */
class WoocommerceAuthorizationService
{
    public function __construct(private readonly ModuleUtil $moduleUtil)
    {
    }

    /**
     * Check básico: usuário acessa o módulo Woocommerce?
     * Superadmin OU subscription ativa `woocommerce_module`.
     */
    public function podeAcessarModulo(int $businessId): bool
    {
        if (auth()->user()->can('superadmin')) {
            return true;
        }

        return (bool) $this->moduleUtil->hasThePermissionInSubscription($businessId, 'woocommerce_module');
    }

    /**
     * Check com permissão específica (woocommerce.sync_orders, woocommerce.syc_categories, etc).
     * Superadmin OU (subscription + permission).
     */
    public function podeExecutarAcao(int $businessId, string $permissionKey): bool
    {
        // D9 Wave 18 SATURATION — OTel span (no-op em local sem collector — overhead < 1µs)
        return OtelHelper::span('woocommerce.auth.pode_executar', [
            'business_id' => $businessId,
            'permission_key' => $permissionKey,
        ], function () use ($businessId, $permissionKey) {
            if (auth()->user()->can('superadmin')) {
                return true;
            }

            $temSubscription = (bool) $this->moduleUtil->hasThePermissionInSubscription($businessId, 'woocommerce_module');
            $temPermissao = (bool) auth()->user()->can($permissionKey);

            return $temSubscription && $temPermissao;
        });
    }

    /**
     * Helper Controller — aborta 403 se sem acesso ao módulo.
     */
    public function ensureModulo(int $businessId): void
    {
        if (! $this->podeAcessarModulo($businessId)) {
            abort(403, 'Unauthorized action.');
        }
    }

    /**
     * Helper Controller — aborta 403 se sem permissão específica.
     */
    public function ensureAcao(int $businessId, string $permissionKey): void
    {
        if (! $this->podeExecutarAcao($businessId, $permissionKey)) {
            abort(403, 'Unauthorized action.');
        }
    }
}
