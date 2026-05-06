<?php

namespace Modules\RecurringBilling\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Financeiro\Models\ContaBancaria;
use Modules\RecurringBilling\Models\BoletoCredential;

class ProcessAsaasWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        private readonly int    $businessId,
        private readonly string $eventId,
        private readonly string $event,
        private readonly array  $payment,
    ) {}

    public function handle(): void
    {
        match ($this->event) {
            'PAYMENT_RECEIVED', 'PAYMENT_CONFIRMED' => $this->onPaymentReceived(),
            'PAYMENT_OVERDUE'                        => $this->onPaymentOverdue(),
            'BALANCE_UPDATED'                        => $this->onBalanceUpdated(),
            default                                  => null,
        };

        // Marca como processado
        DB::table('pg_webhook_events')
            ->where('provider', 'asaas')
            ->where('event_id', $this->eventId)
            ->update(['processed' => true, 'updated_at' => now()]);
    }

    private function onPaymentReceived(): void
    {
        $externalRef = $this->payment['externalReference'] ?? null;
        $valor = (float) ($this->payment['value'] ?? 0);
        $paidAt = $this->payment['paymentDate'] ?? now()->toDateString();

        // Atualiza rb_invoices se existir
        if ($externalRef) {
            DB::table('rb_invoices')
                ->where('business_id', $this->businessId)
                ->where('numero_documento', $externalRef)
                ->where('status', '!=', 'paid')
                ->update(['status' => 'paid', 'updated_at' => now()]);
        }

        // Cria account_transaction (credit) na conta Asaas do tenant
        $contaAsaas = ContaBancaria::where('business_id', $this->businessId)
            ->where('banco_codigo', '274') // Asaas
            ->first();

        if ($contaAsaas?->account_id) {
            DB::table('account_transactions')->insertOrIgnore([
                'account_id'   => $contaAsaas->account_id,
                'amount'       => $valor,
                'type'         => 'credit',
                'sub_type'     => 'deposit',
                'note'         => 'Boleto recebido via Asaas — ' . ($externalRef ?? $this->payment['id'] ?? ''),
                'transaction_date' => $paidAt,
                'created_by'   => 1,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);

            // Atualiza saldo_cached incrementalmente
            $contaAsaas->increment('saldo_cached', $valor);
            $contaAsaas->update(['saldo_atualizado_em' => now()]);
        }

        // Dispara InvoicePaid para NFe escutar
        if ($externalRef) {
            event(new \Modules\RecurringBilling\Events\InvoicePaid(
                businessId: $this->businessId,
                invoiceRef: $externalRef,
                valor: $valor,
                paidAt: $paidAt,
            ));
        }
    }

    private function onPaymentOverdue(): void
    {
        $externalRef = $this->payment['externalReference'] ?? null;
        if ($externalRef) {
            DB::table('rb_invoices')
                ->where('business_id', $this->businessId)
                ->where('numero_documento', $externalRef)
                ->where('status', 'open')
                ->update(['status' => 'overdue', 'updated_at' => now()]);
        }
    }

    private function onBalanceUpdated(): void
    {
        $saldo = (float) ($this->payment['balance'] ?? 0);
        ContaBancaria::where('business_id', $this->businessId)
            ->where('banco_codigo', '274')
            ->update(['saldo_cached' => $saldo, 'saldo_atualizado_em' => now()]);
    }
}
