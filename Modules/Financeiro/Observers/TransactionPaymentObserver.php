<?php

namespace Modules\Financeiro\Observers;

use App\TransactionPayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Financeiro\Services\TituloAutoService;

/**
 * Observer no TransactionPayment core do UltimatePOS.
 *
 * Onda 2 (2026-04-25): fix BUG-1 — pagamento via TransactionPayment
 * agora cria automaticamente TituloBaixa + CaixaMovimento e atualiza
 * valor_aberto/status do Titulo correspondente.
 *
 * Idempotência via idempotency_key='tp_<id>' (UNIQUE em fin_titulo_baixas).
 *
 * Fluxo:
 *   created → registrarPagamento (cria baixa + movimento)
 *   updated → se mudou amount, estorna anterior e cria nova
 *   deleted → cancelarPagamento (estorno via row negativa, append-only)
 *
 * US-FIN-059 (2026-11-05): mesma proteção do TransactionObserver — a falha no Financeiro
 * vai para `report()` e o pagamento é gravado. No `updated`, estorno e nova baixa rodam
 * no MESMO `DB::transaction`: se a nova baixa falha, o estorno é desfeito junto, e o
 * título não fica com a baixa antiga estornada e nenhuma nova no lugar.
 */
class TransactionPaymentObserver
{
    public function __construct(private TituloAutoService $service)
    {
    }

    public function created(TransactionPayment $tp): void
    {
        $this->semDerrubarOPagamento($tp, 'created', function () use ($tp): void {
            $this->service->registrarPagamento($tp);
        });
    }

    public function updated(TransactionPayment $tp): void
    {
        // Só estorna+recria se algo financeiramente relevante mudou.
        if (! $tp->wasChanged(['amount', 'method', 'paid_on', 'account_id'])) {
            return;
        }

        // Estratégia: estorno da baixa antiga + cria nova com valores atuais.
        $this->semDerrubarOPagamento($tp, 'updated', function () use ($tp): void {
            $this->service->cancelarPagamento($tp);
            $this->service->registrarPagamento($tp);
        });
    }

    public function deleted(TransactionPayment $tp): void
    {
        $this->semDerrubarOPagamento($tp, 'deleted', function () use ($tp): void {
            $this->service->cancelarPagamento($tp);
        });
    }

    private function semDerrubarOPagamento(TransactionPayment $tp, string $evento, callable $trabalho): void
    {
        try {
            DB::transaction(static function () use ($trabalho): void {
                $trabalho();
            });
        } catch (\Throwable $e) {
            Log::warning('financeiro.observer.payment.falhou', [
                'business_id' => $tp->business_id,
                'transaction_payment_id' => $tp->id,
                'transaction_id' => $tp->transaction_id,
                'evento' => $evento,
                'erro' => $e->getMessage(),
            ]);
            report($e);
        }
    }
}
