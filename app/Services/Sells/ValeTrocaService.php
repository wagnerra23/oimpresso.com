<?php

declare(strict_types=1);

namespace App\Services\Sells;

use App\Events\TransactionPaymentAdded;
use App\TransactionPayment;
use App\Utils\TransactionUtil;
use Illuminate\Support\Facades\DB;

/**
 * Vale-troca (US-VEST-021 · decisão [W] 2026-10-26): o valor de uma devolução vira saldo de
 * Adiantamento do cliente, o mesmo saldo que o caixa já aceita como forma de pagamento.
 *
 * Como fica registrado:
 * - um pagamento na DEVOLUÇÃO com `method = advance`, `transaction_no = VT-<id da devolução>`;
 * - o listener `AddAccountTransaction` lê esse pagamento como crédito e SOBE o saldo do cliente
 *   (`contacts.balance`); apagar o pagamento desce o saldo de novo (`DeleteAccountTransaction`);
 * - nenhum dinheiro sai do caixa nem do banco: não há conta nem baixa no Financeiro (o observer
 *   do Financeiro só cuida de venda e compra).
 *
 * O crédito do Vestuário (`vestuario_creditos_cliente`) fica sem uso: um saldo só, o que o caixa lê.
 *
 * Valor (regra mestre): credita o que falta pagar da devolução, contado em DECIMAL no MySQL.
 * Devolução já paga (em dinheiro ou já virada vale) não credita de novo.
 *
 * Tier 0 (ADR 0093): a devolução é lida com o `business_id` recebido e o cliente vem dela.
 */
final class ValeTrocaService
{
    public const PREFIXO = 'VT-';

    public function __construct(private readonly TransactionUtil $transactionUtil) {}

    /** O pagamento é um vale-troca? (Adiantamento lançado numa devolução.) */
    public static function ehVale(TransactionPayment $pagamento): bool
    {
        if ($pagamento->method !== 'advance' || empty($pagamento->transaction_id)) {
            return false;
        }

        return DB::table('transactions')->where('id', $pagamento->transaction_id)->value('type') === 'sell_return';
    }

    /**
     * Credita no saldo do cliente o valor em aberto da devolução.
     *
     * @return array{pagamento_id:int, valor:string, saldo_cliente:string}
     */
    public function creditar(int $businessId, int $devolucaoId, int $userId): array
    {
        return DB::transaction(function () use ($businessId, $devolucaoId, $userId) {
            $dev = DB::table('transactions')
                ->where('business_id', $businessId)->where('id', $devolucaoId)->where('type', 'sell_return')
                ->lockForUpdate()->first(['id', 'contact_id', 'final_total']);
            if ($dev === null) {
                throw new ValeTrocaException('nao_encontrada', 'Devolução não encontrada.');
            }
            if (empty($dev->contact_id)) {
                throw new ValeTrocaException('sem_cliente', 'Devolução sem cliente: o vale precisa de um cliente para guardar o saldo.');
            }
            // O "Cliente padrão" (consumidor sem cadastro) é um só para todos: o saldo seria de qualquer um.
            $padrao = (bool) DB::table('contacts')->where('business_id', $businessId)->where('id', $dev->contact_id)->value('is_default');
            if ($padrao) {
                throw new ValeTrocaException('cliente_padrao', 'Cadastre o cliente antes: o vale fica no saldo dele, e o Cliente padrão é de todos.');
            }

            $valor = (string) DB::selectOne(
                'SELECT CAST(t.final_total - COALESCE((SELECT SUM(IF(tp.is_return = 0, tp.amount, -tp.amount))
                    FROM transaction_payments tp WHERE tp.transaction_id = t.id AND tp.business_id = t.business_id), 0) AS DECIMAL(22,4)) AS v
                 FROM transactions t WHERE t.id = ? AND t.business_id = ?',
                [$dev->id, $businessId],
            )->v;
            if ((float) $valor <= 0) {
                throw new ValeTrocaException('sem_saldo', 'Esta devolução já foi paga ao cliente.');
            }

            $ref = $this->transactionUtil->setAndGetReferenceCount('sell_payment', $businessId);
            $pagamento = TransactionPayment::create([
                'business_id' => $businessId,
                'transaction_id' => $dev->id,
                'amount' => $valor,
                'is_return' => 0,
                'method' => 'advance',
                'transaction_no' => self::PREFIXO . $dev->id,
                'paid_on' => now()->format('Y-m-d H:i:s'),
                'created_by' => $userId,
                'payment_for' => $dev->contact_id,
                'note' => 'Vale-troca: valor da devolução virou saldo do cliente',
                'payment_ref_no' => $this->transactionUtil->generateReferenceNumber('sell_payment', $ref, $businessId),
            ]);
            event(new TransactionPaymentAdded($pagamento, ['transaction_type' => 'sell_return', 'amount' => $valor, 'method' => 'advance']));
            $this->transactionUtil->updatePaymentStatus($dev->id, $dev->final_total);

            $saldo = (string) DB::table('contacts')->where('business_id', $businessId)->where('id', $dev->contact_id)->value('balance');

            return ['pagamento_id' => (int) $pagamento->id, 'valor' => $valor, 'saldo_cliente' => $saldo];
        });
    }
}
