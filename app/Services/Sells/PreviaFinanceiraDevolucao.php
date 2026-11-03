<?php

namespace App\Services\Sells;

use App\Transaction;
use App\Utils\ProductUtil;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Financeiro\Services\DevolucaoVendaTituloService;

/**
 * Prévia, ANTES de salvar, do que a devolução faz no Financeiro (SellReturn/Add · UC-SRADD-09).
 *
 * Só leitura. Nenhuma regra nova: o total é o de `ProductUtil::calculateInvoiceTotal`, chamado
 * do mesmo jeito que `TransactionUtil::addSellReturn` chama ao gravar; o abatimento é o de
 * `DevolucaoVendaTituloService::valorAAbater` (#10249), aplicado a uma devolução montada em
 * memória com esse total. O que a tela mostra é o número que a gravação vai produzir.
 *
 * - Pagar depois: abate do título da venda até o que está em aberto; a sobra é crédito do
 *   cliente, que fica na própria devolução ("A pagar").
 * - Vale-troca: o vale é um pagamento na devolução, e devolução paga ao cliente não abate
 *   (UC-FDEV-04). A venda segue devida pelo aberto de antes.
 *
 * Tier 0: a venda é lida no business da sessão; de outra empresa → null (404).
 */
class PreviaFinanceiraDevolucao
{
    public function __construct(private ProductUtil $productUtil)
    {
    }

    /**
     * @param  array<int, array<string, mixed>>  $produtos  `products[]` do form (texto no formato da empresa)
     */
    public function calcular(int $businessId, int $vendaId, array $produtos, ?string $descontoTipo, ?string $descontoValor): ?array
    {
        $venda = Transaction::where('business_id', $businessId)->where('type', 'sell')->where('id', $vendaId)->first(['id', 'tax_id']);
        if ($venda === null) {
            return null;
        }

        $linhas = [];
        foreach ($produtos as $p) {
            if (is_array($p)) {
                $linhas[] = ['quantity' => $p['quantity'] ?? 0, 'unit_price_inc_tax' => $p['unit_price_inc_tax'] ?? 0];
            }
        }
        // Mesma chamada de addSellReturn: desconto ausente vira 'fixed' 0; imposto é o da venda.
        $totais = $linhas === [] ? false : $this->productUtil->calculateInvoiceTotal(
            $linhas,
            $venda->tax_id,
            ['discount_type' => $descontoTipo ?? 'fixed', 'discount_amount' => $descontoValor ?? 0],
            true,
        );
        $total = $totais === false ? 0.0 : (float) $totais['final_total'];

        $devolucaoId = (int) Transaction::where('business_id', $businessId)->where('type', 'sell_return')
            ->where('return_parent_id', $vendaId)->value('id');
        $pagoAoCliente = $devolucaoId === 0 ? 0.0 : (float) DB::table('transaction_payments')
            ->where('business_id', $businessId)->where('transaction_id', $devolucaoId)->sum('amount');
        $devido = max(0.0, $total - $pagoAoCliente);

        return [
            'total' => round($total, 2),
            'ja_pago_ao_cliente' => round($pagoAoCliente, 2),
            'devido_ao_cliente' => round($devido, 2),
            'financeiro' => $this->financeiro($businessId, $vendaId, $devolucaoId, $total, $devido),
        ];
    }

    private function financeiro(int $businessId, int $vendaId, int $devolucaoId, float $total, float $devido): ?array
    {
        if (! class_exists(DevolucaoVendaTituloService::class) || ! Schema::hasTable('fin_titulos')) {
            return null;
        }

        $servico = app(DevolucaoVendaTituloService::class);
        // A devolução como ficará depois de salvar. id 0 quando ainda não existe: nenhum
        // pagamento nem baixa tem transaction_id/idempotency_key com esse id.
        $simulada = new Transaction();
        $simulada->forceFill([
            'id' => $devolucaoId,
            'business_id' => $businessId,
            'type' => 'sell_return',
            'return_parent_id' => $vendaId,
            'final_total' => $total,
        ]);

        return DB::transaction(function () use ($servico, $simulada, $devido) {
            $titulo = $servico->tituloDaVenda($simulada);
            if ($titulo === null || $titulo->status === 'cancelado') {
                return ['tem_titulo' => false, 'em_aberto_na_venda' => 0.0, 'ja_abatido' => 0.0, 'abate' => 0.0, 'credito' => round($devido, 2)];
            }

            $ativa = $servico->baixaAtiva($simulada);
            $jaAbatido = (float) ($ativa?->valor_baixa ?? 0);
            $abate = $servico->valorAAbater($simulada, $titulo, $ativa);
            // Aberto sem contar o abatimento desta devolução — o mesmo teto de valorAAbater().
            $emAberto = app(\Modules\Financeiro\Services\TituloAutoService::class)
                ->abertoPorBaixas((int) $titulo->id, (float) $titulo->valor_total) + $jaAbatido;

            return [
                'tem_titulo' => true,
                'em_aberto_na_venda' => round($emAberto, 2),
                'ja_abatido' => round($jaAbatido, 2),
                'abate' => $abate,
                'credito' => round(max(0.0, $devido - $abate), 2),
            ];
        });
    }
}
