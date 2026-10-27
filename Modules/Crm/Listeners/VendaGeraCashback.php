<?php

declare(strict_types=1);

namespace Modules\Crm\Listeners;

use App\Events\SellCreatedOrModified;
use App\Events\TransactionPaymentAdded;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Crm\Services\Fidelidade\CashbackService;
use Throwable;

/**
 * Venda paga → crédito de cashback (US-CRM-093).
 *
 * Escuta a gravação da venda (venda paga na hora) e o pagamento adicionado depois (venda a prazo
 * quitada mais tarde). O serviço só credita venda final e paga, uma vez por venda.
 *
 * Roda dentro da gravação: falha aqui é registrada e engolida — cashback nunca derruba uma venda.
 */
class VendaGeraCashback
{
    public function __construct(private CashbackService $cashback)
    {
    }

    public function handle(SellCreatedOrModified|TransactionPaymentAdded $event): void
    {
        if (! (bool) config('crm.cashback.enabled', false)) {
            return;
        }
        [$businessId, $vendaId] = $event instanceof SellCreatedOrModified
            ? [(int) $event->transaction->business_id, (int) $event->transaction->id]
            : [(int) $event->transactionPayment->business_id, (int) $event->transactionPayment->transaction_id];
        if ($businessId <= 0 || $vendaId <= 0) {
            return;
        }
        if ($event instanceof TransactionPaymentAdded
            && DB::table('transactions')->where('business_id', $businessId)->where('id', $vendaId)->value('type') !== 'sell') {
            return;
        }

        try {
            $this->cashback->concederParaVenda($businessId, $vendaId);
        } catch (Throwable $e) {
            Log::warning('cashback: falha ao conceder crédito da venda', [
                'business_id' => $businessId, 'venda_id' => $vendaId, 'erro' => class_basename($e),
            ]);
        }
    }
}
