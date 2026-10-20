<?php

declare(strict_types=1);

namespace Modules\Producao\Services;

use Illuminate\Support\Facades\DB;
use Modules\Producao\Exceptions\ApontamentoRecusado;

/**
 * Rastreio do rolo (US-COM-025 · UC-FLX-18): dado um lote recebido (a linha da compra), em que OS cada
 * fração dele foi usada e quanto sobra. Só leitura.
 *
 * Mora no Producao porque as frações por OS vêm de `pcp_consumos` (a baixa da OS → `transaction_sell_line_id`),
 * tabela deste módulo; o núcleo não lê tabela de módulo. O vínculo fração × lote é o do `mapPurchaseSell`
 * (`transaction_sell_lines_purchase_lines`), o mesmo que dá o custo da baixa.
 *
 * Saldo = recebido − devolvido − vendido − ajustado − usado em produção, pelas colunas da linha da compra
 * (as mesmas que o `mapPurchaseSell` confere antes de baixar). `usado_em_producao` inclui o Manufacturing,
 * se a empresa o usa; `usado_em_os` é só o que veio de OS do PCP. Tier 0: `$businessId` em toda consulta;
 * lote de outra empresa é "não encontrado" (404).
 */
final class RastreioDoRolo
{
    /** @return array<string, mixed> */
    public function doLote(int $businessId, int $purchaseLineId): array
    {
        $lote = DB::table('purchase_lines as pl')->join('transactions as t', 't.id', '=', 'pl.transaction_id')
            ->join('products as p', 'p.id', '=', 'pl.product_id')
            ->where('t.business_id', $businessId)->where('p.business_id', $businessId)->where('pl.id', $purchaseLineId)
            ->whereIn('t.type', ['purchase', 'purchase_transfer', 'opening_stock', 'production_purchase'])
            ->selectRaw('pl.id, pl.lot_number, pl.product_id, pl.variation_id, p.name as produto, t.location_id, t.transaction_date as recebido_em')
            ->selectRaw('pl.quantity as recebido, pl.quantity_returned as devolvido, pl.quantity_sold as vendido')
            ->selectRaw('pl.quantity_adjusted as ajustado, pl.mfg_quantity_used as usado_em_producao')
            ->selectRaw('(pl.quantity - pl.quantity_returned - pl.quantity_sold - pl.quantity_adjusted - pl.mfg_quantity_used) as saldo')
            ->first()
            ?? throw new ApontamentoRecusado(ApontamentoRecusado::NAO_ENCONTRADO, 'Lote não encontrado.');

        $daOs = fn () => DB::table('transaction_sell_lines_purchase_lines as m')
            ->join('pcp_consumos as c', function ($j) use ($businessId) {
                $j->on('c.transaction_sell_line_id', '=', 'm.sell_line_id')->where('c.business_id', '=', $businessId);
            })
            ->where('m.purchase_line_id', $purchaseLineId);

        $fracoes = $daOs()
            ->join('pcp_ordens as o', function ($j) use ($businessId) {
                $j->on('o.id', '=', 'c.ordem_id')->where('o.business_id', '=', $businessId);
            })
            ->join('pcp_programacoes as e', 'e.id', '=', 'c.programacao_id')
            ->leftJoin('transactions as v', function ($j) use ($businessId) {
                $j->on('v.id', '=', 'o.transaction_id')->where('v.business_id', '=', $businessId);
            })
            ->leftJoin('users as u', 'u.id', '=', 'c.user_id')
            ->orderBy('c.momento')->orderBy('c.id')
            ->get(['c.id as consumo_id', 'o.id as ordem_id', 'v.invoice_no as venda', 'e.id as etapa_id', 'e.nome as etapa',
                'm.quantity as quantidade', 'c.momento', 'c.user_id',
                DB::raw("TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) as apontado_por")]);

        $usadoEmOs = (string) $daOs()->selectRaw('COALESCE(SUM(m.quantity), 0) as q')->value('q');

        return [
            'lote' => ['id' => (int) $lote->id, 'lot_number' => $lote->lot_number, 'product_id' => (int) $lote->product_id,
                'variation_id' => (int) $lote->variation_id, 'produto' => $lote->produto, 'location_id' => (int) $lote->location_id,
                'recebido_em' => (string) $lote->recebido_em],
            'quantidades' => ['recebido' => (string) $lote->recebido, 'devolvido' => (string) $lote->devolvido,
                'vendido' => (string) $lote->vendido, 'ajustado' => (string) $lote->ajustado,
                'usado_em_producao' => (string) $lote->usado_em_producao, 'usado_em_os' => $usadoEmOs, 'saldo' => (string) $lote->saldo],
            'fracoes' => $fracoes->map(fn ($f) => ['consumo_id' => (int) $f->consumo_id, 'ordem_id' => (int) $f->ordem_id, 'venda' => $f->venda,
                'etapa_id' => (int) $f->etapa_id, 'etapa' => $f->etapa, 'quantidade' => (string) $f->quantidade,
                'momento' => (string) $f->momento, 'user_id' => (int) $f->user_id, 'apontado_por' => $f->apontado_por])->all(),
        ];
    }
}
