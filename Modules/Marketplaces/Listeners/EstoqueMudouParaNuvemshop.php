<?php

declare(strict_types=1);

namespace Modules\Marketplaces\Listeners;

use App\VariationLocationDetails;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Marketplaces\Entities\Marketplace;
use Modules\Marketplaces\Jobs\EnviarEstoqueNuvemshopJob;
use Throwable;

/**
 * Estoque mudou → agenda o envio para a loja Nuvemshop (ADR 0443, fatia d). Mesmo modelo do
 * `EstoqueMudouParaWoo` (US-WOO-007).
 *
 * Chamado nos eventos `created`/`updated` de `VariationLocationDetails`. Só agenda quando a flag
 * geral está ligada E a variação tem anúncio ativo numa conta Nuvemshop com `enviar_estoque` e cujo
 * local de venda é o local que mudou. A empresa vem da conta (a tabela de saldo não tem business_id).
 * Roda dentro de venda, compra e ajuste: qualquer falha é registrada e engolida.
 */
class EstoqueMudouParaNuvemshop
{
    public static function aoSalvar(VariationLocationDetails $vld): void
    {
        if (! config('marketplaces.nuvemshop.ativo', false) || (! $vld->wasRecentlyCreated && ! $vld->wasChanged('qty_available'))) {
            return;
        }

        try {
            $anuncios = DB::table('mkt_listings as l')
                ->join('mkt_account_credentials as c', fn ($j) => $j->on('c.id', '=', 'l.account_id')->on('c.business_id', '=', 'l.business_id'))
                ->join('mkt_marketplaces as m', 'm.id', '=', 'l.marketplace_id')
                ->where('m.slug', Marketplace::NUVEMSHOP)
                ->where('l.variation_id', $vld->variation_id)
                ->where('l.status', 'active')
                ->where('c.status', 'active')
                ->where('c.enviar_estoque', 1)
                ->where('c.location_id', $vld->location_id)
                ->get(['l.id', 'l.business_id']);

            foreach ($anuncios as $a) {
                EnviarEstoqueNuvemshopJob::dispatch((int) $a->business_id, (int) $a->id)->delay(now()->addMinute())->afterCommit();
            }
        } catch (Throwable $e) {
            Log::warning('marketplaces: falha ao agendar estoque para a Nuvemshop', [
                'variation_id' => (int) $vld->variation_id,
                'erro' => class_basename($e),
            ]);
        }
    }
}
