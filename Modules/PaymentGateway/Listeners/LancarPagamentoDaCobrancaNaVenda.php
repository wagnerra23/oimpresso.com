<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Listeners;

use App\Events\TransactionPaymentAdded;
use App\TransactionPayment;
use App\Utils\TransactionUtil;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\PaymentGateway\Events\CobrancaPaga;
use Modules\PaymentGateway\Models\Cobranca;

/**
 * Retorno do banco → pagamento na venda (US-PCP-025 · UC-FLX-28 "Retorno do banco → Pago").
 *
 * Até aqui a cobrança paga de uma venda (`cobrancas.origem_type = 'sale'`) só mudava o status da
 * cobrança: a venda seguia "a receber" e o app pedia para registrar o pagamento à mão. Este
 * listener grava o `transaction_payments` da venda, atualiza o `payment_status` e, pelo
 * `TransactionPayment::saved` da Produção (#9639), libera a OS que esperava o sinal.
 *
 * VALOR (regra mestre) — o que ele grava e o que ele recusa:
 * - Valor = `valor_pago_centavos` (ou `valor_centavos`) convertido a texto decimal sem float.
 * - Só lança se o valor couber no saldo em aberto da venda (conta em DECIMAL no MySQL). Não
 *   couber = alguém já registrou à mão ou o cliente pagou a mais: não lança e deixa no log,
 *   para nunca contar o mesmo dinheiro duas vezes.
 * - Idempotente: `transaction_no = PG-COB-<id>` sob trava da venda; retorno repetido não duplica.
 * - Desligado por padrão (`paymentgateway.lancar_pagamento_na_venda`). Ligar é decisão [W],
 *   depois de conferir quem hoje registra esses pagamentos à mão.
 * - O business dono do SaaS fica de fora: lá o `OnCobrancaPagaCreateFinanceiroTitulo` já cria um
 *   título quitado da cobrança, e o pagamento na venda somaria o recebimento outra vez.
 *
 * Tier 0 (ADR 0093): a cobrança e a venda são lidas com o `business_id` do evento, e o pagamento
 * nasce nesse mesmo business. Falha só vai para o log: o webhook do banco nunca cai por isto.
 */
final class LancarPagamentoDaCobrancaNaVenda
{
    public const PREFIXO = 'PG-COB-';

    public function __construct(private readonly TransactionUtil $transactionUtil) {}

    public function handle(CobrancaPaga $event): void
    {
        if (! (bool) config('paymentgateway.lancar_pagamento_na_venda', false)) {
            return;
        }
        if ($event->businessId === (int) config('app.saas_owner_business_id')) {
            return;
        }

        try {
            $this->lancar($event);
        } catch (\Throwable $e) {
            Log::error('paymentgateway.venda: falha ao lançar o pagamento da cobrança na venda', [
                'cobranca_id' => $event->cobrancaId, 'business_id' => $event->businessId, 'erro' => $e->getMessage(),
            ]);
        }
    }

    /** @return string o que aconteceu: lancado | ja_lancado | sem_saldo | ignorado */
    public function lancar(CobrancaPaga $event): string
    {
        $biz = $event->businessId;
        // SUPERADMIN: roda no webhook, sem sessão; o business vem do evento e filtra a busca.
        $cob = Cobranca::withoutGlobalScopes()->where('business_id', $biz)->find($event->cobrancaId);
        if ($cob === null || $cob->origem_type !== 'sale' || ! $cob->origem_id || $cob->status !== 'paga') {
            return 'ignorado';
        }
        $centavos = (int) ($cob->valor_pago_centavos ?? $cob->valor_centavos);
        if ($centavos <= 0) {
            return 'ignorado';
        }
        $valor = sprintf('%d.%02d', intdiv($centavos, 100), $centavos % 100);
        $marca = self::PREFIXO . $cob->id;

        $resultado = DB::transaction(function () use ($biz, $cob, $valor, $marca, $event) {
            $venda = DB::table('transactions')->where('id', (int) $cob->origem_id)->where('business_id', $biz)
                ->where('type', 'sell')->lockForUpdate()->first(['id', 'final_total', 'contact_id', 'created_by']);
            if ($venda === null) {
                return ['ignorado', null];
            }
            $jaLancado = DB::table('transaction_payments')->where('business_id', $biz)
                ->where('transaction_id', $venda->id)->where('transaction_no', $marca)->exists();
            if ($jaLancado) {
                return ['ja_lancado', null];
            }
            $cabe = (bool) DB::selectOne(
                'SELECT (t.final_total - COALESCE((SELECT SUM(IF(tp.is_return = 0, tp.amount, -tp.amount)) FROM transaction_payments tp
                    WHERE tp.transaction_id = t.id AND tp.business_id = t.business_id), 0)) >= CAST(? AS DECIMAL(22,4)) AS cabe
                 FROM transactions t WHERE t.id = ? AND t.business_id = ?',
                [$valor, $venda->id, $biz],
            )->cabe;
            if (! $cabe) {
                return ['sem_saldo', $venda];
            }

            $refCount = $this->transactionUtil->setAndGetReferenceCount('sell_payment', $biz);
            $tp = TransactionPayment::create([
                'business_id' => $biz,
                'transaction_id' => $venda->id,
                'amount' => $valor,
                'is_return' => 0,
                'method' => 'bank_transfer',
                'transaction_no' => $marca,
                'paid_on' => $event->pagaEm->format('Y-m-d H:i:s'),
                'created_by' => $venda->created_by,
                'payment_for' => $venda->contact_id,
                'note' => "Retorno do banco: cobrança #{$cob->id} ({$event->formaPagamento})",
                'payment_ref_no' => $this->transactionUtil->generateReferenceNumber('sell_payment', $refCount, $biz),
            ]);
            event(new TransactionPaymentAdded($tp, ['transaction_type' => 'sell', 'amount' => $valor, 'method' => 'bank_transfer']));
            $this->transactionUtil->updatePaymentStatus($venda->id, $venda->final_total);

            return ['lancado', $venda];
        });

        if ($resultado[0] === 'sem_saldo') {
            Log::warning('paymentgateway.venda: cobrança paga maior que o saldo da venda — não lançada, conferir à mão', [
                'cobranca_id' => $cob->id, 'business_id' => $biz, 'transaction_id' => $resultado[1]->id, 'valor' => $valor,
            ]);
        }

        return $resultado[0];
    }
}
