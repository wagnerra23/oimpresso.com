<?php

declare(strict_types=1);

namespace Modules\Woocommerce\Listeners;

use App\VariationLocationDetails;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Woocommerce\Jobs\EnviarEstoqueWooJob;
use Throwable;

/**
 * Estoque mudou → agenda o envio para a loja WooCommerce (US-WOO-007).
 *
 * Chamado pelos eventos `created`/`updated` de `VariationLocationDetails` (o `decrement` da
 * baixa de venda dispara `updated`). Custa uma consulta e só agenda quando o produto está ligado
 * à Woo. `variation_location_details` não tem `business_id`: a empresa vem do produto.
 *
 * Roda dentro de venda, compra e ajuste: qualquer falha é registrada e engolida — aviso para a
 * loja nunca derruba a operação do ERP.
 */
class EstoqueMudouParaWoo
{
    public static function aoSalvar(VariationLocationDetails $vld): void
    {
        if (! $vld->wasRecentlyCreated && ! $vld->wasChanged('qty_available')) {
            return;
        }

        try {
            $produto = DB::table('products')
                ->where('id', $vld->product_id)
                ->whereNotNull('woocommerce_product_id')
                ->where('woocommerce_disable_sync', 0)
                ->first(['id', 'business_id']);
            if ($produto === null) {
                return;
            }

            EnviarEstoqueWooJob::dispatch((int) $produto->business_id, (int) $produto->id)
                ->delay(now()->addMinute())
                ->afterCommit();
        } catch (Throwable $e) {
            Log::warning('woocommerce: falha ao agendar envio de estoque', [
                'product_id' => (int) $vld->product_id,
                'erro' => class_basename($e),
            ]);
        }
    }
}
