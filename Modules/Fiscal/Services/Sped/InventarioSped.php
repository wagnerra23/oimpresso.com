<?php

declare(strict_types=1);

namespace Modules\Fiscal\Services\Sped;

use App\Utils\TransactionUtil;
use Illuminate\Support\Facades\DB;

/**
 * US-FISCAL-028 fatia 3 — posição do estoque numa data, por item, para o Bloco H.
 *
 * Não é cálculo novo de estoque: é a MESMA conta do `TransactionUtil::getOpeningClosingStock`
 * (o "estoque final" do relatório de lucro do sistema), só que agrupada por variação em vez de
 * somada. Mesmos filtros (`purchase.type != purchase_order`, `transaction_date <= data`), mesma
 * quantidade restante (comprado − devolvido − ajustado − vendido até a data, via
 * `transaction_sell_lines_purchase_lines`) e mesmo custo (`purchase_price + item_tax`).
 *
 * Padrão adotado por decisão [W] 2026-10-27 ("use os padrões mais comuns"): custo de aquisição,
 * imposto da linha incluso — correto para quem não toma crédito de ICMS (Simples); quem toma
 * crédito deveria excluí-lo. Registrado como pergunta ao contador na US-FISCAL-028.
 *
 * Herda uma limitação da função canônica, declarada: `quantity_adjusted` não tem data, então um
 * ajuste de estoque feito DEPOIS da data do inventário também é descontado.
 */
final class InventarioSped
{
    public function __construct(private readonly TransactionUtil $transactionUtil)
    {
    }

    /**
     * `soma_bruta` soma TODAS as variações (inclusive saldo negativo) e é o que se compara com o
     * total canônico; `itens` só traz saldo positivo, porque o H010 discrimina o que existe.
     *
     * @return array{itens: list<array{variation_id:int, cod:string, descr:string, unid:string, ncm:string, cest:string, qtd:float, valor:float}>, soma_bruta: float, negativos: int}
     */
    public function posicao(int $businessId, string $data): array
    {
        $vendidoAteAData = "(SELECT COALESCE(SUM(tspl.quantity - tspl.qty_returned), 0)
            FROM transaction_sell_lines_purchase_lines AS tspl
            JOIN transaction_sell_lines AS tsl ON tspl.sell_line_id = tsl.id
            JOIN transactions AS sale ON tsl.transaction_id = sale.id
            WHERE tspl.purchase_line_id = purchase_lines.id AND date(sale.transaction_date) <= ?)";
        $restante = "(purchase_lines.quantity - purchase_lines.quantity_returned - purchase_lines.quantity_adjusted - {$vendidoAteAData})";

        $linhas = DB::table('purchase_lines')
            ->join('transactions as purchase', 'purchase_lines.transaction_id', '=', 'purchase.id')
            ->leftJoin('variations as v', 'v.id', '=', 'purchase_lines.variation_id')
            ->leftJoin('products as p', 'p.id', '=', 'purchase_lines.product_id')
            ->leftJoin('units as u', 'u.id', '=', 'p.unit_id')
            ->where('purchase.type', '!=', 'purchase_order')
            ->where('purchase.business_id', $businessId)
            ->whereRaw('date(purchase.transaction_date) <= ?', [$data])
            ->groupBy('purchase_lines.variation_id', 'v.sub_sku', 'p.name', 'v.name', 'p.ncm', 'p.cest', 'u.short_name')
            ->selectRaw(
                "purchase_lines.variation_id, v.sub_sku, p.name AS produto, v.name AS variacao, p.ncm, p.cest,
                 u.short_name AS unidade,
                 SUM({$restante}) AS qtd,
                 SUM({$restante} * (purchase_lines.purchase_price + COALESCE(purchase_lines.item_tax, 0))) AS valor",
                [$data, $data]
            )
            ->get();

        $itens = [];
        $somaBruta = 0.0;
        $negativos = 0;
        foreach ($linhas as $l) {
            $somaBruta += (float) $l->valor;
            $qtd = round((float) $l->qtd, 3);
            if ($qtd <= 0) {
                $negativos += $qtd < 0 ? 1 : 0;
                continue; // H010 discrimina o que EXISTE no estoque
            }
            $variacao = trim((string) $l->variacao);
            $descr = trim((string) $l->produto) . (($variacao !== '' && strtoupper($variacao) !== 'DUMMY') ? ' ' . $variacao : '');
            $sku = trim((string) $l->sub_sku);

            $itens[] = [
                'variation_id' => (int) $l->variation_id,
                'cod'          => $sku !== '' ? mb_substr($sku, 0, 60) : 'VAR-' . (int) $l->variation_id,
                'descr'        => mb_substr($descr, 0, 120),
                'unid'         => mb_strtoupper(mb_substr(trim((string) ($l->unidade ?? '')) ?: 'UN', 0, 6)),
                'ncm'          => preg_match('/^\d{8}$/', (string) $l->ncm) ? (string) $l->ncm : '',
                'cest'         => preg_match('/^\d{7}$/', (string) $l->cest) ? (string) $l->cest : '',
                'qtd'          => $qtd,
                'valor'        => (float) $l->valor,
            ];
        }

        return ['itens' => $itens, 'soma_bruta' => $somaBruta, 'negativos' => $negativos];
    }

    /** O total pela função canônica do sistema — a segunda prova do VL_INV. */
    public function totalCanonico(int $businessId, string $data): float
    {
        return (float) $this->transactionUtil->getOpeningClosingStock($businessId, $data, null);
    }
}
