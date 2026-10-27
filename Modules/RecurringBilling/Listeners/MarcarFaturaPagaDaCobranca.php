<?php

declare(strict_types=1);

namespace Modules\RecurringBilling\Listeners;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\PaymentGateway\Events\CobrancaPaga;
use Modules\PaymentGateway\Models\Cobranca;
use Modules\RecurringBilling\Events\InvoicePaid;
use Modules\RecurringBilling\Models\Invoice;

/**
 * Cobrança paga pelo PaymentGateway → fatura da assinatura paga (US-RB-057).
 *
 * O `CobrancaPaga` declara `RecurringBilling\Listeners\MarkInvoicePaid` como 1º listener (ADR
 * 0170 CONTRACTS §3), e ele nunca existiu: a cobrança `origem_type = 'invoice'` paga no banco
 * deixava a `rb_invoices` em aberto e o `InvoicePaid` (que a NF-e ao receber escuta) não saía.
 *
 * VALOR (regra mestre) — o que ele grava e o que ele recusa:
 * - Só marca `paid` se o valor pago (`valor_pago_centavos`, senão `valor_centavos`) cobre a
 *   fatura. A comparação é em centavos inteiros, sem float. Pagou menos → não marca, só loga.
 * - Não cria lançamento, título nem saldo: muda o status da fatura e grava `pago_em`.
 * - Idempotente: fatura já `paid` não muda e não dispara o evento de novo.
 * - Desligado por padrão (`recurringbilling.marcar_fatura_paga_por_cobranca`). Ligar é decisão
 *   [W] — com o `nfebrasil.auto_emission_on_invoice_paid` ligado, ele passa a emitir NF-e.
 *
 * Tier 0 (ADR 0093): cobrança e fatura são lidas com o `business_id` do evento. Falha vai para o
 * log: o webhook do banco nunca cai por isto.
 */
final class MarcarFaturaPagaDaCobranca
{
    public function handle(CobrancaPaga $event): void
    {
        if (! (bool) config('recurringbilling.marcar_fatura_paga_por_cobranca', false)) {
            return;
        }

        try {
            $this->marcar($event);
        } catch (\Throwable $e) {
            Log::error('recurringbilling.fatura: falha ao marcar a fatura paga pela cobrança', [
                'cobranca_id' => $event->cobrancaId, 'business_id' => $event->businessId, 'erro' => $e->getMessage(),
            ]);
        }
    }

    /** @return string o que aconteceu: paga | ja_paga | valor_insuficiente | ignorado */
    public function marcar(CobrancaPaga $event): string
    {
        $biz = $event->businessId;
        // SUPERADMIN: roda no webhook, sem sessão; o business vem do evento e filtra a busca.
        $cob = Cobranca::withoutGlobalScopes()->where('business_id', $biz)->find($event->cobrancaId);
        if ($cob === null || $cob->origem_type !== 'invoice' || ! $cob->origem_id || $cob->status !== 'paga') {
            return 'ignorado';
        }
        $pagoCentavos = (int) ($cob->valor_pago_centavos ?? $cob->valor_centavos);

        $resultado = DB::transaction(function () use ($biz, $cob, $pagoCentavos, $event) {
            // SUPERADMIN: idem — a fatura é lida no business do evento.
            $fatura = Invoice::withoutGlobalScopes()->where('business_id', $biz)
                ->lockForUpdate()->find((int) $cob->origem_id);
            if ($fatura === null) {
                return ['ignorado', null];
            }
            if ($fatura->status === 'paid') {
                return ['ja_paga', null];
            }
            // `valor` é decimal(…,2) e chega como texto "123.45": centavos sem passar por float.
            [$inteiro, $fracao] = array_pad(explode('.', (string) $fatura->valor, 2), 2, '0');
            $faturaCentavos = (int) $inteiro * 100 + (int) str_pad(substr($fracao, 0, 2), 2, '0');
            if ($pagoCentavos < $faturaCentavos) {
                return ['valor_insuficiente', null];
            }

            $fatura->forceFill([
                'status' => 'paid',
                'pago_em' => $event->pagaEm->format('Y-m-d H:i:s'),
            ])->save();

            return ['paga', $fatura];
        });

        [$status, $fatura] = $resultado;
        if ($status === 'valor_insuficiente') {
            Log::warning('recurringbilling.fatura: pagamento menor que a fatura — não marcada paga', [
                'cobranca_id' => $cob->id, 'business_id' => $biz, 'fatura_id' => $cob->origem_id,
            ]);
        }
        if ($status === 'paga' && $fatura !== null) {
            event(new InvoicePaid(
                businessId: $biz,
                invoiceRef: (string) $fatura->numero_documento,
                valor: $pagoCentavos / 100,
                paidAt: $event->pagaEm->format('Y-m-d'),
            ));
        }

        return $status;
    }
}
