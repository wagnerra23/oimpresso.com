<?php

declare(strict_types=1);

namespace Modules\Woocommerce\Jobs;

use App\Business;
use App\Product;
use App\Utils\ModuleUtil;
use App\VariationLocationDetails;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Modules\Woocommerce\Utils\WoocommerceUtil;

/**
 * Manda para a loja WooCommerce o estoque atual de UM produto simples (US-WOO-007).
 *
 * Só LÊ o estoque do ERP (`variation_location_details.qty_available` no local configurado na Woo)
 * e envia `stock_quantity`. Não grava estoque no ERP. Mesmas regras do sync manual
 * (`WoocommerceUtil::syncProducts`): produto simples com controle de estoque, já ligado à Woo
 * (`woocommerce_product_id`), sem `woocommerce_disable_sync`, local da Woo configurado, e
 * `manage_stock_for_update` diferente de "false".
 *
 * Único por empresa+produto e com atraso no envio: uma rajada de baixas (venda com 10 linhas)
 * vira um envio só, já com o estoque final.
 */
class EnviarEstoqueWooJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $uniqueFor = 600;

    public function __construct(public int $businessId, public int $productId)
    {
    }

    public function uniqueId(): string
    {
        return $this->businessId.':'.$this->productId;
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [60, 300];
    }

    /**
     * Estoque que vai para a Woo, ou null quando este produto não deve ser enviado.
     * Público para o teste conferir o valor por um segundo caminho.
     */
    public static function quantidadeParaWoo(int $businessId, int $productId): ?string
    {
        // Empresa sem o pacote WooCommerce não envia, mesmo com configuração antiga gravada.
        if (! app(ModuleUtil::class)->hasThePermissionInSubscription($businessId, 'woocommerce_module', 'superadmin_package')) {
            return null;
        }
        $config = app(WoocommerceUtil::class)->get_api_settings($businessId);
        if (empty($config) || empty($config->location_id) || (($config->manage_stock_for_update ?? null) === 'false')) {
            return null;
        }

        $produto = Product::query()
            ->where('business_id', $businessId)
            ->where('id', $productId)
            ->where('type', 'single')
            ->where('enable_stock', 1)
            ->where('woocommerce_disable_sync', 0)
            ->whereNotNull('woocommerce_product_id')
            ->first();
        if ($produto === null) {
            return null;
        }

        $qtd = VariationLocationDetails::query()
            ->where('product_id', $produto->id)
            ->where('location_id', (int) $config->location_id)
            ->value('qty_available');

        $casas = (int) (Business::query()->where('id', $businessId)->value('quantity_precision') ?: 2);

        return number_format((float) ($qtd ?? 0), $casas, '.', '');
    }

    public function handle(WoocommerceUtil $util): void
    {
        $qtd = self::quantidadeParaWoo($this->businessId, $this->productId);
        if ($qtd === null) {
            return;
        }

        $wooId = (int) Product::query()->where('business_id', $this->businessId)->where('id', $this->productId)->value('woocommerce_product_id');

        $util->woo_client($this->businessId)->put('products/'.$wooId, [
            'manage_stock' => true,
            'stock_quantity' => $qtd,
        ]);
    }
}
