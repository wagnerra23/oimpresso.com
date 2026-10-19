<?php

declare(strict_types=1);

namespace App\Observers;

use App\TransactionSellLine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Compras\Services\CustoDoInsumoService;

/**
 * Congela o custo previsto da linha de orçamento no dia (UC-FLX-33 · US-SELL-067).
 *
 * Só age em venda em rascunho (`type = sell`, `status = draft`): é o orçamento aberto, com
 * ou sem `is_quotation`. Venda finalizada não precisa: o custo real dela já fica amarrado
 * ao lote de compra por TransactionUtil::mapPurchaseSell.
 *
 * - Ao criar a linha, grava o custo do insumo na data do orçamento (CustoDoInsumoService,
 *   método da empresa). Depois disso o valor não muda: compra nova não reescreve orçamento.
 * - Ao editar, só recalcula se a linha trocou de produto (`variation_id`).
 * - Converter o orçamento em venda não mexe na linha: o custo do dia segue o mesmo.
 *
 * Nunca derruba a venda: se o cálculo falhar, a linha é gravada sem custo e o erro vai para o
 * log. Não toca preço, total nem estoque.
 *
 * Tier 0 (ADR 0093): o business vem da própria transação da linha.
 */
final class TransactionSellLineCustoObserver
{
    public function creating(TransactionSellLine $linha): void
    {
        if ($linha->custo_previsto === null) {
            $this->congelar($linha);
        }
    }

    public function updating(TransactionSellLine $linha): void
    {
        if ($linha->isDirty('variation_id')) {
            $linha->custo_previsto = null;
            $linha->custo_previsto_em = null;
            $linha->custo_previsto_metodo = null;
            $this->congelar($linha);
        }
    }

    private function congelar(TransactionSellLine $linha): void
    {
        if (empty($linha->variation_id) || empty($linha->transaction_id)) {
            return;
        }

        try {
            $tx = DB::table('transactions')->where('id', $linha->transaction_id)
                ->first(['business_id', 'type', 'status', 'transaction_date']);
            if (! $tx || $tx->type !== 'sell' || $tx->status !== 'draft') {
                return;
            }

            // Resolvido aqui dentro, e não no construtor: se o container falhar ao montar o
            // serviço, a falha cai no catch e a venda segue.
            $r = app(CustoDoInsumoService::class)->naData((int) $tx->business_id, (int) $linha->variation_id, (string) $tx->transaction_date);
            if ($r['custo'] === null) {
                return;
            }

            $linha->custo_previsto = $r['custo'];
            $linha->custo_previsto_em = $r['data'];
            $linha->custo_previsto_metodo = $r['metodo'];
        } catch (\Throwable $e) {
            Log::warning('custo previsto da linha de orçamento não calculado', [
                'transaction_id' => $linha->transaction_id,
                'variation_id' => $linha->variation_id,
                'erro' => $e->getMessage(),
            ]);
        }
    }
}
