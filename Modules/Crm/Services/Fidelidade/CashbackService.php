<?php

declare(strict_types=1);

namespace Modules\Crm\Services\Fidelidade;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Cashback por regra (US-CRM-093): a venda paga gera um crédito no saldo de Adiantamento do
 * cliente, o mesmo saldo que o caixa já aceita como forma de pagamento (vale-troca, #9867).
 *
 * Regra da empresa (`crm_cashback_regras`, uma por empresa): percentual, valor mínimo da venda e
 * validade em dias. Sem regra ativa, ou com a flag `crm.cashback.enabled` desligada, nada acontece.
 *
 * Quando credita: venda `sell`, `final`, `paid`, com cliente cadastrado (o "Cliente padrão" é de
 * todos). Uma vez por venda (chave única business_id + transaction_id).
 *
 * Valor (regra mestre), contado em DECIMAL no MySQL:
 *   base     = final_total − o que foi pago com Adiantamento nesta venda
 *              (o cashback não rende cashback, nem o vale-troca)
 *   cashback = ROUND(base × percentual / 100, 2), se base ≥ valor_minimo
 *
 * Tier 0 (ADR 0093): tudo lido e gravado com o `business_id` recebido.
 */
final class CashbackService
{
    /**
     * @return array{credito_id:int, valor:string, expira_em:string}|null  null = não gerou crédito
     */
    public function concederParaVenda(int $businessId, int $vendaId): ?array
    {
        if (! (bool) config('crm.cashback.enabled', false)) {
            return null;
        }
        $regra = DB::table('crm_cashback_regras')
            ->where('business_id', $businessId)->where('ativo', true)->first();
        if ($regra === null || (float) $regra->percentual <= 0) {
            return null;
        }

        return DB::transaction(function () use ($businessId, $vendaId, $regra) {
            $venda = DB::table('transactions')
                ->where('business_id', $businessId)->where('id', $vendaId)
                ->where('type', 'sell')->where('status', 'final')->where('payment_status', 'paid')
                ->lockForUpdate()->first(['id', 'contact_id']);
            if ($venda === null || empty($venda->contact_id)) {
                return null;
            }
            $padrao = (bool) DB::table('contacts')
                ->where('business_id', $businessId)->where('id', $venda->contact_id)->value('is_default');
            if ($padrao) {
                return null;
            }
            if (DB::table('crm_cashback_creditos')->where('business_id', $businessId)->where('transaction_id', $vendaId)->exists()) {
                return null;
            }

            $conta = DB::selectOne(
                'SELECT CAST(b.base AS DECIMAL(22,4)) AS base,
                        CAST(ROUND(b.base * CAST(? AS DECIMAL(5,2)) / 100, 2) AS DECIMAL(22,4)) AS valor
                 FROM (SELECT t.final_total - COALESCE((SELECT SUM(IF(tp.is_return = 0, tp.amount, -tp.amount))
                          FROM transaction_payments tp
                          WHERE tp.transaction_id = t.id AND tp.business_id = t.business_id AND tp.method = ?), 0) AS base
                       FROM transactions t WHERE t.id = ? AND t.business_id = ?) b',
                [(string) $regra->percentual, 'advance', $vendaId, $businessId],
            );
            if ($conta === null || (float) $conta->base < (float) $regra->valor_minimo || (float) $conta->valor <= 0) {
                return null;
            }

            $expira = now()->addDays((int) $regra->validade_dias)->toDateString();
            try {
                $id = (int) DB::table('crm_cashback_creditos')->insertGetId([
                    'business_id' => $businessId, 'contact_id' => (int) $venda->contact_id,
                    'transaction_id' => $vendaId, 'base' => $conta->base, 'percentual' => $regra->percentual,
                    'valor' => $conta->valor, 'expira_em' => $expira, 'situacao' => 'ativo',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            } catch (QueryException) {
                return null; // outra gravação da mesma venda chegou antes
            }
            DB::update(
                'UPDATE contacts SET balance = balance + CAST(? AS DECIMAL(22,4)) WHERE id = ? AND business_id = ?',
                [(string) $conta->valor, (int) $venda->contact_id, $businessId],
            );

            return ['credito_id' => $id, 'valor' => (string) $conta->valor, 'expira_em' => $expira];
        });
    }
}
