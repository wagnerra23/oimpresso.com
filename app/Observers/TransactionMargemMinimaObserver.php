<?php

declare(strict_types=1);

namespace App\Observers;

use App\Domain\Fsm\Modelos\RegrasDaEmpresa;
use App\Exceptions\MargemAbaixoDoMinimo;
use App\Transaction;
use Illuminate\Support\Facades\DB;

/**
 * Bloqueia a conversão de orçamento em venda abaixo da margem mínima da empresa
 * (UC-DRAFT-05 · US-SELL-069 · UC-FLX-35). Decisão [W] 2026-10-19: bloqueia; só quem tem
 * `sell.aprovar_margem_baixa` converte.
 *
 * Age só na transição `sell` de `draft` para `final`, qualquer que seja o caminho (editar o
 * rascunho, "converter em fatura"): é no Model que todos se encontram.
 *
 * Margem = (receita líquida − custo guardado) / receita líquida, sobre as linhas com custo guardado
 * (US-SELL-067). Receita líquida = Σ preço × quantidade das linhas, menos o desconto do pedido
 * proporcional a elas. Não bloqueia quando:
 *  - a empresa não configurou margem mínima (regra desligada);
 *  - nenhuma linha tem custo guardado (orçamento anterior à US-SELL-067);
 *  - não há usuário autenticado (fila, CLI): não há quem aprove, e travar um job é pior.
 *
 * Limite conhecido: no caminho de edição, as linhas são gravadas depois do cabeçalho. Se a
 * conversão também mudar o preço das linhas, a conta usa os preços de antes da edição; o desconto
 * do pedido já entra com o valor novo.
 */
final class TransactionMargemMinimaObserver
{
    public const PERMISSAO = 'sell.aprovar_margem_baixa';

    public function updating(Transaction $tx): void
    {
        if ($tx->type !== 'sell' || $tx->getOriginal('status') !== 'draft' || $tx->status !== 'final') {
            return;
        }

        $minima = app(RegrasDaEmpresa::class)->margemMinima((int) $tx->business_id);
        $user = auth()->user();
        if ($minima === null || $user === null || $user->can(self::PERMISSAO)) {
            return;
        }

        $margem = self::margem($tx);
        if ($margem !== null && $margem < $minima) {
            throw new MargemAbaixoDoMinimo($margem, $minima);
        }
    }

    /** Margem em % pelo custo guardado; nula quando não há como calcular. */
    public static function margem(Transaction $tx): ?float
    {
        $linhas = DB::table('transaction_sell_lines')
            ->where('transaction_id', $tx->id)
            ->whereNotNull('custo_previsto')
            ->get(['quantity', 'unit_price', 'custo_previsto']);
        if ($linhas->isEmpty()) {
            return null;
        }

        $receita = $linhas->sum(fn ($l) => (float) $l->unit_price * (float) $l->quantity);
        $custo = $linhas->sum(fn ($l) => (float) $l->custo_previsto * (float) $l->quantity);

        $todas = (float) DB::table('transaction_sell_lines')->where('transaction_id', $tx->id)
            ->sum(DB::raw('unit_price * quantity'));
        $desconto = $tx->discount_type === 'percentage'
            ? $receita * (float) $tx->discount_amount / 100
            : ($todas > 0 ? (float) $tx->discount_amount * $receita / $todas : 0.0);
        $receita -= $desconto;

        return $receita > 0 ? round(($receita - $custo) / $receita * 100, 1) : null;
    }
}
