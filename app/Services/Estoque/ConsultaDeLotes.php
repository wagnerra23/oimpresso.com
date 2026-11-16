<?php

declare(strict_types=1);

namespace App\Services\Estoque;

use App\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Lotes: estoque, vendido e ajustado por lote (variação × número do lote), pelas compras com número de
 * lote e pelo que saiu de cada uma (`transaction_sell_lines_purchase_lines`).
 *
 * É a consulta do relatório de Lotes (ReportController::getLotReport, DataTable da Blade e tela nova),
 * movida para cá sem mudança de regra para o painel do produto ler os lotes pela mesma conta
 * (UC-PIDX-49 · CV3). Respeita os locais que o usuário logado pode ver.
 *
 * Tier 0 (ADR 0093): tudo sai pelo `products.business_id` recebido.
 */
final class ConsultaDeLotes
{
    /** Filtros aceitos — os mesmos nomes que o report.js manda. */
    public const FILTROS = ['location_id', 'category_id', 'sub_category_id', 'brand_id', 'unit_id', 'only_mfg_products', 'product_id'];

    /**
     * @param  array<string, mixed>  $filtros  FILTROS
     * @return Builder<Product>
     */
    public function consulta(int $business_id, array $filtros): Builder
    {
        $query = Product::where('products.business_id', $business_id)
                ->leftjoin('units', 'products.unit_id', '=', 'units.id')
                ->join('variations as v', 'products.id', '=', 'v.product_id')
                ->join('purchase_lines as pl', 'v.id', '=', 'pl.variation_id')
                ->leftjoin(
                    'transaction_sell_lines_purchase_lines as tspl',
                    'pl.id',
                    '=',
                    'tspl.purchase_line_id'
                )
                ->join('transactions as t', 'pl.transaction_id', '=', 't.id');

        $permitted_locations = auth()->user()->permitted_locations();
        $location_filter = 'WHERE ';

        if ($permitted_locations != 'all') {
            $query->whereIn('t.location_id', $permitted_locations);

            // `permitted_locations()` devolve 'all' ou a lista de ids, mas o docblock dela diz só string:
            // o (array) não muda a lista e deixa o PHPStan ver o tipo.
            $locations_imploded = implode(', ', (array) $permitted_locations);
            $location_filter = " LEFT JOIN transactions as t2 on pls.transaction_id=t2.id WHERE t2.location_id IN ($locations_imploded) AND ";
        }

        if (! empty($filtros['location_id'] ?? null)) {
            $location_id = (int) $filtros['location_id']; // vai cru no SQL abaixo: só inteiro
            $query->where('t.location_id', $location_id)
                //If filter by location then hide products not available in that location
                ->ForLocation($location_id);

            $location_filter = "LEFT JOIN transactions as t2 on pls.transaction_id=t2.id WHERE t2.location_id=$location_id AND ";
        }

        if (! empty($filtros['category_id'] ?? null)) {
            $query->where('products.category_id', $filtros['category_id']);
        }

        // UC-RLT-07 (CV3): os lotes de um produto, vindo do painel da consulta de produto. A empresa já está
        // no `products.business_id` acima: o id de outra empresa só esvazia a lista.
        if (! empty($filtros['product_id'] ?? null)) {
            $query->where('products.id', (int) $filtros['product_id']);
        }

        if (! empty($filtros['sub_category_id'] ?? null)) {
            $query->where('products.sub_category_id', $filtros['sub_category_id']);
        }

        if (! empty($filtros['brand_id'] ?? null)) {
            $query->where('products.brand_id', $filtros['brand_id']);
        }

        if (! empty($filtros['unit_id'] ?? null)) {
            $query->where('products.unit_id', $filtros['unit_id']);
        }

        $only_mfg_products = ($filtros['only_mfg_products'] ?? 0);
        if (! empty($only_mfg_products)) {
            $query->where('t.type', 'production_purchase');
        }

        $products = $query->select(
            'products.name as product',
            'v.name as variation_name',
            'sub_sku',
            'pl.lot_number',
            'pl.exp_date as exp_date',
            DB::raw("( COALESCE((SELECT SUM(quantity - quantity_returned) from purchase_lines as pls $location_filter variation_id = v.id AND lot_number = pl.lot_number), 0) - 
                SUM(COALESCE((tspl.quantity - tspl.qty_returned), 0))) as stock"),
            // DB::raw("(SELECT SUM(IF(transactions.type='sell', TSL.quantity, -1* TPL.quantity) ) FROM transactions
            //         LEFT JOIN transaction_sell_lines AS TSL ON transactions.id=TSL.transaction_id

            //         LEFT JOIN purchase_lines AS TPL ON transactions.id=TPL.transaction_id

            //         WHERE transactions.status='final' AND transactions.type IN ('sell', 'sell_return') $location_filter
            //         AND (TSL.product_id=products.id OR TPL.product_id=products.id)) as total_sold"),

            DB::raw('COALESCE(SUM(IF(tspl.sell_line_id IS NULL, 0, (tspl.quantity - tspl.qty_returned)) ), 0) as total_sold'),
            DB::raw('COALESCE(SUM(IF(tspl.stock_adjustment_line_id IS NULL, 0, tspl.quantity ) ), 0) as total_adjusted'),
            'products.type',
            'units.short_name as unit'
        )
        ->whereNotNull('pl.lot_number')
        ->groupBy('v.id')
        ->groupBy('pl.lot_number');

        return $products;
    }
}
