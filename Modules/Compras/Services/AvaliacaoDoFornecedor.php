<?php

declare(strict_types=1);

namespace Modules\Compras\Services;

use Illuminate\Support\Facades\DB;

/**
 * Avaliação automática do fornecedor — US-COM-033 (decisão coordenação 2026-10-26).
 *
 * Calculada das entregas registradas, sem nota digitada. Três números por fornecedor, na janela
 * de `$dias` (padrão 180), e nenhum deles é combinado num índice único: a ponderação seria
 * inventada, e o comprador lê os três lado a lado na cotação.
 *
 *   no_prazo    entregas no prazo / entregas com prazo. Entrega = compra recebida ligada a linha
 *               de pedido (`purchase_lines.purchase_order_line_id`, US-COM-013); no prazo = data da
 *               compra ≤ `delivery_date` do pedido. Pedido sem data prevista não conta.
 *   completas   Σ recebido / Σ pedido nas linhas dos pedidos já encerrados (`completed`) ou com a
 *               data prevista vencida. Pedido ainda dentro do prazo não pesa contra o fornecedor.
 *   reprovacao  Σ reprovado / Σ inspecionado na inspeção do recebimento (US-COM-034).
 *
 * Fornecedor sem dado num eixo recebe `null` naquele eixo, nunca 0 nem 100.
 * Só lê: não mexe em valor nem estoque. Tier 0 (ADR 0093): tudo filtrado por `$businessId`.
 */
final class AvaliacaoDoFornecedor
{
    /**
     * @param  list<int>  $fornecedores  contacts.id
     * @return array<int, array{entregas:int, no_prazo:?float, completas:?float, inspecionado:string, reprovacao:?float}>
     */
    public function porFornecedor(int $businessId, array $fornecedores, int $dias = 180): array
    {
        $fornecedores = array_values(array_unique(array_map('intval', $fornecedores)));
        if ($fornecedores === []) {
            return [];
        }
        $desde = now()->subDays($dias)->startOfDay();
        $saida = [];
        foreach ($fornecedores as $c) {
            $saida[$c] = ['entregas' => 0, 'no_prazo' => null, 'completas' => null, 'inspecionado' => '0', 'reprovacao' => null];
        }

        // Uma linha por (compra, pedido): a entrega daquele pedido.
        $entregas = DB::table('transactions as cp')
            ->join('purchase_lines as lc', 'lc.transaction_id', '=', 'cp.id')
            ->join('purchase_lines as lp', 'lp.id', '=', 'lc.purchase_order_line_id')
            ->join('transactions as po', 'po.id', '=', 'lp.transaction_id')
            ->where('cp.business_id', $businessId)->where('cp.type', 'purchase')->where('cp.status', 'received')
            ->where('po.business_id', $businessId)->where('po.type', 'purchase_order')
            ->whereIn('cp.contact_id', $fornecedores)->where('cp.transaction_date', '>=', $desde)
            ->groupBy('cp.id', 'po.id', 'cp.contact_id', 'cp.transaction_date', 'po.delivery_date')
            ->get(['cp.contact_id', 'cp.transaction_date', 'po.delivery_date']);
        foreach ($entregas->groupBy('contact_id') as $c => $lista) {
            $comPrazo = $lista->filter(fn ($e) => $e->delivery_date !== null);
            $saida[(int) $c]['entregas'] = $lista->count();
            if ($comPrazo->isNotEmpty()) {
                $noPrazo = $comPrazo->filter(fn ($e) => substr((string) $e->transaction_date, 0, 10) <= substr((string) $e->delivery_date, 0, 10))->count();
                $saida[(int) $c]['no_prazo'] = round($noPrazo / $comPrazo->count() * 100, 1);
            }
        }

        $completas = DB::table('transactions as po')->join('purchase_lines as lp', 'lp.transaction_id', '=', 'po.id')
            ->where('po.business_id', $businessId)->where('po.type', 'purchase_order')
            ->whereIn('po.contact_id', $fornecedores)->where('po.transaction_date', '>=', $desde)
            ->where(fn ($q) => $q->where('po.status', 'completed')->orWhere('po.delivery_date', '<', now()->startOfDay()))
            ->groupBy('po.contact_id')
            ->get(['po.contact_id', DB::raw('SUM(LEAST(lp.po_quantity_purchased, lp.quantity)) as recebido'), DB::raw('SUM(lp.quantity) as pedido')]);
        foreach ($completas as $r) {
            if ((float) $r->pedido > 0) {
                $saida[(int) $r->contact_id]['completas'] = round((float) $r->recebido / (float) $r->pedido * 100, 1);
            }
        }

        $inspecoes = DB::table('compras_inspecoes')->where('business_id', $businessId)
            ->whereIn('contact_id', $fornecedores)->where('created_at', '>=', $desde)->groupBy('contact_id')
            ->get(['contact_id', DB::raw('SUM(quantidade_inspecionada) as insp'), DB::raw('SUM(quantidade_reprovada) as rep')]);
        foreach ($inspecoes as $r) {
            $saida[(int) $r->contact_id]['inspecionado'] = (string) $r->insp;
            if ((float) $r->insp > 0) {
                $saida[(int) $r->contact_id]['reprovacao'] = round((float) $r->rep / (float) $r->insp * 100, 1);
            }
        }

        return $saida;
    }
}
