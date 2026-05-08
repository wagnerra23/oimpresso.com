<?php

declare(strict_types=1);

namespace Modules\RecurringBilling\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Modules\Financeiro\Models\ContaBancaria;
use Modules\RecurringBilling\Events\InvoicePaid;

/**
 * Processa um PIX recebido (webhook Inter).
 *
 * - Atualiza `rb_invoices` se há `txid` mapeando pra invoice do tenant
 * - Cria `account_transactions` (credit) na conta Inter (saldo incremental)
 * - Dispara `InvoicePaid` event pra NfeBrasil emitir NFe55 automaticamente
 *
 * Idempotente por (`provider='inter'`, `event_id=endToEndId`) — controller
 * já marcou na tabela `pg_webhook_events` antes de dispatch.
 *
 * @see US-RB-047
 */
class ProcessInterWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        private readonly int    $businessId,
        private readonly string $endToEndId,
        private readonly array  $pix,
    ) {}

    public function handle(): void
    {
        $valor    = (float) ($this->pix['valor'] ?? 0);
        $txid     = $this->pix['txid'] ?? null;
        $horario  = $this->pix['horario'] ?? now()->toIso8601String();
        $paidAt   = substr((string) $horario, 0, 10);

        if ($txid) {
            DB::table('rb_invoices')
                ->where('business_id', $this->businessId)
                ->where('gateway_ref', $txid)
                ->where('status', '!=', 'paid')
                ->update(['status' => 'paid', 'updated_at' => now()]);
        }

        $contaInter = ContaBancaria::where('business_id', $this->businessId)
            ->where('banco_codigo', '077')
            ->first();

        if ($contaInter?->account_id) {
            DB::table('account_transactions')->insertOrIgnore([
                'account_id'       => $contaInter->account_id,
                'amount'           => $valor,
                'type'             => 'credit',
                'sub_type'         => 'deposit',
                'note'             => 'PIX recebido Inter — '.$this->endToEndId,
                'transaction_date' => $paidAt,
                'created_by'       => 1,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);

            $contaInter->increment('saldo_cached', $valor);
            $contaInter->update(['saldo_atualizado_em' => now()]);
        }

        if ($txid) {
            event(new InvoicePaid(
                businessId: $this->businessId,
                invoiceRef: $txid,
                valor:      $valor,
                paidAt:     $paidAt,
            ));
        }

        DB::table('pg_webhook_events')
            ->where('provider', 'inter')
            ->where('event_id', $this->endToEndId)
            ->update(['processed' => true, 'updated_at' => now()]);
    }
}
