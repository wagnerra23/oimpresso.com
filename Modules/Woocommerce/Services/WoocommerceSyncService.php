<?php

namespace Modules\Woocommerce\Services;

use App\Util\OtelHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Woocommerce\Exceptions\WooCommerceError;
use Modules\Woocommerce\Utils\WoocommerceUtil;

/**
 * WoocommerceSyncService — orquestra sync categories/products/orders.
 *
 * Antes (D4 baixo): Controller chamava `$this->woocommerceUtil->syncX()` direto +
 * gerenciava `DB::beginTransaction/commit/rollBack` + montava response array inline.
 * Cada método repetia o mesmo try/catch + check `WooCommerceError`.
 *
 * Depois: Controllers ficam thin — só recebem Request, delegam ao Service,
 * retornam array padronizado. Toda lógica de domínio + transação aqui.
 *
 * Multi-tenant Tier 0 ([ADR 0093]): `$businessId` SEMPRE explícito em todo
 * método — Service é job-safe (não depende de session()).
 *
 * NOTA: `WoocommerceUtil` é mantido como dependência (1541 linhas legacy
 * UltimatePOS — extrair tudo de uma vez quebraria contrato). Service age
 * como **camada de coordenação** sobre o Util — passo 1 D4 governance v3.
 */
class WoocommerceSyncService
{
    public function __construct(private readonly WoocommerceUtil $woocommerceUtil)
    {
    }

    /**
     * Sync de categorias POS → WooCommerce.
     *
     * Retorna array padrão `['success' => 0|1, 'msg' => string]` —
     * mesma assinatura que Controller já expõe (zero breaking).
     */
    public function sincronizarCategorias(int $businessId, int $userId): array
    {
        // D9 Wave 18 — OTel span (no-op em local sem collector — overhead < 1µs)
        return OtelHelper::span('woocommerce.sync.categories', [
            'business_id' => $businessId,
            'user_id' => $userId,
        ], function () use ($businessId, $userId) {
            try {
                DB::beginTransaction();

                $this->woocommerceUtil->syncCategories($businessId, $userId);

                DB::commit();

                return [
                    'success' => 1,
                    'msg' => __('woocommerce::lang.synced_successfully'),
                ];
            } catch (\Exception $e) {
                DB::rollBack();

                return $this->respostaErro($e);
            }
        });
    }

    /**
     * Sync de produtos POS → WooCommerce (batched via limit/offset).
     *
     * @param  string  $syncType  'new_products' | 'all_products'
     */
    public function sincronizarProdutos(
        int $businessId,
        int $userId,
        string $syncType,
        int $limit = 100,
        ?int $offset = null
    ): array {
        // Necessário pra batches grandes — herdou do Controller legacy.
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', 0);

        // D9 Wave 18 — OTel span (sync_type/limit/offset visíveis no trace)
        return OtelHelper::span('woocommerce.sync.products', [
            'business_id' => $businessId,
            'user_id' => $userId,
            'sync_type' => $syncType,
            'limit' => $limit,
            'offset' => $offset ?? -1,
        ], function () use ($businessId, $userId, $syncType, $limit, $offset) {
            try {
                DB::beginTransaction();

                $allProducts = $this->woocommerceUtil->syncProducts(
                    $businessId,
                    $userId,
                    $syncType,
                    $limit,
                    $offset
                );

                DB::commit();

                $totalProducts = is_array($allProducts) ? count($allProducts) : 0;
                $msg = $totalProducts > 0
                    ? __('woocommerce::lang.n_products_synced_successfully', ['count' => $totalProducts])
                    : __('woocommerce::lang.synced_successfully');

                return [
                    'success' => 1,
                    'msg' => $msg,
                    'total_products' => $totalProducts,
                ];
            } catch (\Exception $e) {
                DB::rollBack();

                return $this->respostaErro($e);
            }
        });
    }

    /**
     * Sync de orders WooCommerce → POS (Sells).
     */
    public function sincronizarOrders(int $businessId, int $userId): array
    {
        // D9 Wave 18 — OTel span (orders incoming: WooCommerce → POS Sells)
        return OtelHelper::span('woocommerce.sync.orders', [
            'business_id' => $businessId,
            'user_id' => $userId,
        ], function () use ($businessId, $userId) {
            try {
                DB::beginTransaction();

                $this->woocommerceUtil->syncOrders($businessId, $userId);

                DB::commit();

                return [
                    'success' => 1,
                    'msg' => __('woocommerce::lang.synced_successfully'),
                ];
            } catch (\Exception $e) {
                DB::rollBack();

                return $this->respostaErro($e);
            }
        });
    }

    /**
     * Resposta de erro padronizada. WooCommerceError exibe `getMessage()`,
     * exceção genérica vira "something_went_wrong" + log emergency.
     */
    private function respostaErro(\Exception $e): array
    {
        if ($e instanceof WooCommerceError) {
            return [
                'success' => 0,
                'msg' => $e->getMessage(),
            ];
        }

        Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

        return [
            'success' => 0,
            'msg' => __('messages.something_went_wrong'),
        ];
    }
}
