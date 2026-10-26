<?php

declare(strict_types=1);

namespace App\Services\Sells;

use App\Transaction;
use App\TransactionSellLine;
use App\Utils\ProductUtil;
use App\Utils\TransactionUtil;
use App\VariationLocationDetails;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Cancelamento da venda — estoque, status e título (decisões [W] 2026-10-26).
 *
 * Chamado pelo `CancelarVendaCascade` (ação FSM `cancelar_venda`), dentro da transação do
 * `ExecuteStageActionService` e ANTES da troca de etapa: se este serviço recusar, nada muda.
 *
 * 1. **Venda paga não cancela.** Com dinheiro recebido (pagamentos líquidos > 0), lança
 *    `VendaNaoCancelavelException` dizendo quanto foi recebido e o que fazer para liberar.
 *    O caminho é a própria venda (remover/devolver o pagamento): o Financeiro recusa estornar
 *    baixa que veio de pagamento de venda (`FinanceiroLancamentoAcoesController::estornar`).
 *    Esta checagem é o que garante que o `cancelarSeExistir` só roda sem pagamento.
 * 2. **Estoque volta exatamente pelo caminho que saiu:**
 *    - venda `final`: cada linha devolve `quantidade − já devolvida` (a devolução já repôs o
 *      resto) e libera o lote de compra pelo mesmo caminho da devolução
 *      (`updateQuantitySoldFromSellLine`), para a conferência de estoque fechar;
 *    - reserva consumida pela FSM (`ConsumirEstoque`): devolve a `qty_reserved` e a reserva
 *      passa a `released`.
 * 3. **Status `cancelled` + `cancelled_at`.** A venda sai dos relatórios que filtram
 *    `status = 'final'`. Idempotente: venda já `cancelled` não devolve estoque de novo.
 * 4. **Título:** sem pagamento, o título do Financeiro é cancelado (`cancelarSeExistir`, sem
 *    apagar — append-only).
 *
 * Vendas antigas já canceladas pela FSM e ainda `final` NÃO são tocadas aqui: só com dry-run
 * aprovado pelo [W].
 *
 * Tier 0 (ADR 0093): tudo pelo `business_id` da venda recebida.
 */
final class CancelamentoDaVenda
{
    public function __construct(
        private readonly ProductUtil $productUtil,
        private readonly TransactionUtil $transactionUtil,
    ) {}

    /**
     * O que impede cancelar. Lista vazia = pode cancelar.
     *
     * @return list<string>
     */
    public function pendencias(int $businessId, int $transactionId): array
    {
        $r = DB::selectOne(
            'SELECT CAST(COALESCE(SUM(IF(is_return = 0, amount, -amount)), 0) AS DECIMAL(22,2)) AS recebido, COUNT(*) AS n
             FROM transaction_payments WHERE transaction_id = ? AND business_id = ?',
            [$transactionId, $businessId],
        );
        if ((float) $r->recebido > 0) {
            $valor = number_format((float) $r->recebido, 2, ',', '.');

            return ["A venda tem R$ {$valor} recebidos ({$r->n} pagamento(s)). Devolva ou remova os pagamentos na própria venda e cancele de novo."];
        }

        return [];
    }

    /** @return array{estoque_linhas:int, reservas:int, titulo_cancelado:bool, ja_estava_cancelada:bool} */
    public function aplicar(int $businessId, int $transactionId, string $motivo): array
    {
        $venda = Transaction::where('business_id', $businessId)->where('id', $transactionId)
            ->whereIn('type', ['sell', 'sales_order'])->lockForUpdate()->first();
        if ($venda === null) {
            throw new VendaNaoCancelavelException(['Venda não encontrada.']);
        }
        if ($venda->status === 'cancelled') {
            return ['estoque_linhas' => 0, 'reservas' => 0, 'titulo_cancelado' => false, 'ja_estava_cancelada' => true];
        }

        $pendencias = $this->pendencias($businessId, $transactionId);
        if ($pendencias !== []) {
            throw new VendaNaoCancelavelException($pendencias);
        }

        $linhas = 0;
        if ($venda->status === 'final') {
            $sellLines = TransactionSellLine::where('transaction_id', $venda->id)->get();
            foreach ($sellLines as $linha) {
                $resta = (float) $linha->quantity - (float) $linha->quantity_returned;
                if ($resta <= 0) {
                    continue;
                }
                // Mesmo caminho da devolução: libera o lote de compra da parte que ainda estava vendida.
                // O docblock legado tipa os parâmetros como `obj`/`decimal`, tipos que não existem.
                /** @phpstan-ignore argument.type */
                $this->transactionUtil->updateQuantitySoldFromSellLine($linha, $linha->quantity, $linha->quantity_returned, false);
                $this->productUtil->updateProductQuantity((int) $venda->location_id, $linha->product_id, $linha->variation_id, $resta, 0, null, false);
                $linhas++;
            }
        }

        $reservas = $this->devolverReservasConsumidas($businessId, $venda);

        // forceFill: o Larastan tipa `status` pelo enum da migration original, sem 'cancelled'.
        $venda->forceFill(['status' => 'cancelled', 'cancelled_at' => now()])->save();

        $titulo = false;
        $servico = '\\Modules\\Financeiro\\Services\\TituloAutoService';
        if (class_exists($servico)) {
            $titulo = app($servico)->cancelarSeExistir($venda, motivo: 'venda cancelada: ' . $motivo) !== null;
        }

        Log::info('CancelamentoDaVenda: venda cancelada', [
            'business_id' => $businessId, 'transaction_id' => $transactionId,
            'linhas_estoque' => $linhas, 'reservas' => $reservas, 'titulo' => $titulo,
        ]);

        return ['estoque_linhas' => $linhas, 'reservas' => $reservas, 'titulo_cancelado' => $titulo, 'ja_estava_cancelada' => false];
    }

    /** Reserva consumida pela FSM: a baixa foi pela reserva, então a volta também é. */
    private function devolverReservasConsumidas(int $businessId, Transaction $venda): int
    {
        if (! Schema::hasTable('stock_reservations')) {
            return 0;
        }
        $q = DB::table('stock_reservations')
            ->where('business_id', $businessId)->where('transaction_id', $venda->id)->where('status', 'consumed');
        if (Schema::hasColumn('stock_reservations', 'pcp_ordem_id')) {
            $q->whereNull('pcp_ordem_id'); // insumo da OS é baixado pelo apontamento, não pela reserva
        }
        $n = 0;
        foreach ($q->lockForUpdate()->get() as $r) {
            $enable = DB::table('products')->where('id', $r->product_id)->value('enable_stock');
            if ($enable !== null && (int) $enable === 1) {
                $vld = VariationLocationDetails::where('variation_id', $r->variation_id)->where('location_id', $r->location_id)->first();
                if ($vld !== null) {
                    $vld->qty_available = (float) $vld->qty_available + (float) $r->qty_reserved;
                    $vld->save(); // trilha inventory.stock (INV-1)
                }
            }
            DB::table('stock_reservations')->where('id', $r->id)->update(['status' => 'released', 'updated_at' => now()]);
            $n++;
        }

        return $n;
    }
}
